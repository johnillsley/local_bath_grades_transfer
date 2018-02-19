<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();
/**
 * Grade transfer class
 * This class provides top level functions including generating additional form elements for moodle core hacks
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//TODO -- minus 2 / plus 1 ACADEMIC YEAR for Grade Transfer Report Logs
//TODO -- Also allow them to transfer for previous academic year(s) as long as the lookup is still valid
// TODO -- check for unenrolled students in SAMIS ( Ask Martin ).
//TODO -- plugin_extend_coursemodule_edit_post_actions use this to extend later?

/**
 * Class local_bath_grades_transfer constants
 */
const MAX_GRADE = 100;
// Transfer outcome codes.
/**
 * Transfer is successful
 */
const TRANSFER_SUCCESS = 1;
/**
 * Grade / Student missing in SAMIS
 */
const GRADE_MISSING = 2;
/**
 * Transfer failed with errors
 */
const TRANSFER_FAILURE = 3;
/**
 * Grade already exists in SAMIS
 */
const GRADE_ALREADY_EXISTS = 4;
/**
 * User present in XML structure but missing in Moodle
 */
const GRADE_NOT_IN_MOODLE_COURSE = 5;
/**
 * Grade not out of 100
 */
const GRADE_NOT_OUT_OF_100 = 6;
/**
 * Grade not in SAMIS grade structure ( ASSESSMENT)
 */
const GRADE_NOT_IN_STRUCTURE = 7;
/**
 *  Grade queued
 */
const GRADE_QUEUED = 8;
/**
 * Grade not a whole number in Moodle
 */
const GRADE_NOT_WHOLE_NUMBER = 9;
/**
 * Could not get SPR Code
 */
const COULD_NOT_GET_SPR_CODE = 10;

/**
 * Class local_bath_grades_transfer
 */
class local_bath_grades_transfer
{
    /**
     * @var local_bath_grades_transfer_assessment_mapping
     */
    public $assessmentmapping;
    /**
     * @var string
     */
    public $currentacademicyear;
    /**
     * Moodle Course ID
     * @var
     */
    public $moodlecourseid;
    /**
     * List of Modules allowed to be used for Grades Transfer
     * @var array
     */
    public $allowedmods = array();
    /**
     * @var
     */
    public $enrolsitsplugin;

    /**
     * local_bath_grades_transfer constructor.
     */
    public function __construct() {
        $this->samisdata = new \local_bath_grades_transfer_external_data();
        $this->allowedmods = explode(',', get_config('local_bath_grades_transfer', 'bath_grades_transfer_use'));
        $this->local_grades_transfer_log = new \local_bath_grades_transfer_log();
        $this->local_grades_transfer_error = new \local_bath_grades_transfer_error();
        $this->date = new DateTime();
        $this->assessmentmapping = new \local_bath_grades_transfer_assessment_mapping();
        if (!$this->currentacademicyear) {
            $this->set_currentacademicyear();
        }
    }

    /**
     * Do not show users the Grades Transfer settings part if the plugin is not completely setup
     * @return bool true | false
     */
    public function is_admin_config_present() {
        $config = get_config('local_bath_grades_transfer'); // Get config vars from mdl_config.
        if (!empty($config->samis_api_key) ||
            !empty($config->samis_api_url) || !empty($config->samis_api_user)
            || !empty($config->samis_api_password)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Form API method to display under module settings
     * @param $mform
     * @param $context
     * @param string $modulename
     * @return true if no config set
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {
        global $COURSE, $CFG, $PAGE;
        $PAGE->requires->js_call_amd('local_bath_grades_transfer/grades_transfer', 'init', []);
        require($CFG->dirroot . '/enrol/sits/lib.php');
        // Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);

        // Check that config is set.
        if (!in_array($modulename, $this->allowedmods) || !($this->is_admin_config_present())) {
            return true;
        }
        // Render the header.
        $mform->addElement('header', 'local_bath_grades_transfer_header', 'Grade transfer');

        ////// BUILD CONTROLS /////////////
        // Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {
            /****** FETCH (ANY) NEW REMOTE ASSESSMENTS AND DO HOUSEKEEPING. ******/

            try {
                $assessmentlookup = new local_bath_grades_transfer_assessment_lookup();
                $assessmentlookup->sync_remote_assessments($COURSE->id);
                $mform->addElement('html', "<div style =\"position:absolute;top:40px;width: 500px\" id=\"fetched_new_assessments_notif\"
class=\"alert-info alert \">
 Fetched any new assessments from SAMIS</div>");
            } catch (Exception $e) {
                $mform->addElement('html', "<p class=\"alert-danger alert\">" . $e->getMessage() . "</p>");
                // Show error to the user but continue with the rest of the page.
            }
            // GET SAMIS MAPPING ATTRIBUTES.
            $samisattributelist = $this->get_samis_mapping_attributes($COURSE->id);
            // Get all the records associated with the samis mapping attributes fom Moodle table.
            $lookuprecords = array();
            foreach ($samisattributelist as $samisattributes) {
                $lookuprecords = array_merge($lookuprecords,
                    \local_bath_grades_transfer_assessment_lookup::get_by_samis_details($samisattributes));
            }
            $this->show_transfer_controls($lookuprecords, $cmid, $mform);
        } else {
            // No samis mapping defined for this course..
            $mform->addElement('html', "<p class=\"alert alert-warning\"><i class=\"fa fa-ban\" aria-hidden=\"true\"></i> " .
                get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</p>");
        }
    }

    /** Display transfer controls to the user
     * @param $lookuprecords
     * @param $cmid
     * @param $mform
     */
    public function show_transfer_controls($lookuprecords, $cmid, $mform) {
        $dropdownattributes = array();
        $datetimeselectoroptions = array('optional' => true);
        if ($assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($cmid)) {
            $samisassessmentenddate = $assessmentmapping->samisassessmentenddate;
            $locked = $assessmentmapping->get_locked();
            if ($locked) {
                $dropdownattributes['disabled'] = 'disabled';
                if ($assessmentmapping->samisassessmentenddate != '0') {
                    $samisassessmentenddate = userdate($assessmentmapping->samisassessmentenddate);
                } else {
                    $samisassessmentenddate = 'Not Set';
                }
                // ASSESSMENT MAPPING IS LOCKED.
                $context = context_module::instance($cmid);

                // If user has the capability give them the option to unlock the mapping.
                if (has_capability('local/bath_grades_transfer:unlock_assessment_mapping', $context)) {
                    $mform->addElement('checkbox', 'bath_grade_transfer_samis_unlock_assessment', '',
                        get_string('bath_grade_transfer_samis_unlock_assessment', 'local_bath_grades_transfer'));
                    $mform->addElement('html',
                        "<div id = 'unlock-msg' style='display: none;'><p class=\"alert-warning alert\">" .
                        get_string('unlock_warning', 'local_bath_grades_transfer') . "</p></div>");
                    $mform->addHelpButton('bath_grade_transfer_samis_unlock_assessment',
                        'bath_grade_transfer_samis_unlock_assessment', 'local_bath_grades_transfer');
                }

                // Warn user that mapping is locked and prevent any further changes.

                $mform->addElement('html', "<p class=\"alert-warning alert\"><i class=\"fa fa-lock\" aria-hidden=\"true\"></i> " .
                    get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</p>");
                $this->transfer_mapping_control($lookuprecords, $cmid, $mform, $assessmentmapping, $dropdownattributes);
                $mform->addElement('static', 'bath_grade_transfer_time_start_locked', 'Transfer grades from',
                    $samisassessmentenddate);
                $mform->addHelpButton('bath_grade_transfer_time_start_locked', 'bath_grade_transfer_time_start',
                    'local_bath_grades_transfer');
                $mform->addElement('hidden', 'bath_grade_transfer_time_start', $assessmentmapping->samisassessmentenddate);
                $mform->setType('bath_grade_transfer_time_start', PARAM_INT);

            } else {
                // Not locked.
                /******** MAPPING CONTROL ******/
                $this->transfer_mapping_control($lookuprecords, $cmid, $mform, $assessmentmapping, $dropdownattributes);
                /******** DATE CONTROL ******/
                $this->transfer_date_control($mform, $samisassessmentenddate, $datetimeselectoroptions);
            }
        } else {
            // No Assessment mapping.
            $samisassessmentenddate = null;
            /******** MAPPING CONTROL ******/
            $this->transfer_mapping_control($lookuprecords, $cmid, $mform, $assessmentmapping, $dropdownattributes);
            /******** DATE CONTROL ******/
            $this->transfer_date_control($mform, $samisassessmentenddate, $datetimeselectoroptions);
        }

    }

    /** Get the transfer form mapping control
     * @param $lookuprecords
     * @param $cmid
     * @param $mform
     * @param $assessmentmapping
     * @param $dropdownattributes
     * @return void
     */
    protected function transfer_mapping_control($lookuprecords, $cmid, &$mform, $assessmentmapping, $dropdownattributes) {
        global $PAGE;
        $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id',
            'Select assessment to link to', [], []);
        $select->addOption("None", 0, $dropdownattributes);
        if (!empty($lookuprecords)) {
            foreach ($lookuprecords as $lrecord) {
                $this->display_option($lrecord, $assessmentmapping, $dropdownattributes, $select, $cmid);
            }
        } else {
            $mform->addElement('html', "<p class=\"alert-info alert\">No lookup records were found </p>");
        }
        // Help button for transfer mapping select element.
        $mform->addHelpButton('bath_grade_transfer_samis_lookup_id', 'bath_grade_transfer_samis_lookup_id',
            'local_bath_grades_transfer');
        // Disable select element if grading is not out of 100.
        //$mform->disabledIf('bath_grade_transfer_samis_lookup_id', 'grade[modgrade_point]', 'neq', 100);
        // Display an individual box for each of them with mapping details.
        foreach ($lookuprecords as $lrecord) {
            $mform->addElement('html', "<div id =\"mapping-box-$lrecord->samisassessmentid\" 
    class=\"mapping-box-details\">

    <table class='generaltable'>
    <thead>
    <tr>
    <th>MAP Code</th>
    <th>MAB Seq</th>
    <th>AST Code</th>
    <th>MAB Perc</th>
    <th>Print Name</th>
</tr>
</thead>
<tbody>
<tr>

<td>$lrecord->mapcode</td>
<td>$lrecord->mabseq</td>
<td>$lrecord->astcode</td>
<td>$lrecord->mabperc</td>
<td>$lrecord->mabpnam</td>
</tr>
</tbody>
</table>

    </div>");
        }
    }

    /** Creates a single option for the assessment transfer mapping menu
     * @param $lrecord
     * @param $assessmentmapping
     * @param $attributes
     * @param $select
     * @param $cmid
     * @return void
     */
    private function display_option($lrecord, $assessmentmapping, $attributes, &$select, $cmid) {
        $optiontext = $lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )";
        if (!empty($assessmentmapping) && $lrecord->id == $assessmentmapping->assessmentlookupid) {
            // The lookup is mapped to this course module so set selected.
            $select->setSelected($lrecord->id);
        } else {
            $mappingbylookup = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($lrecord->id);
            if (!empty($mappingbylookup)) {
                if ($cmid != $mappingbylookup->coursemodule) {
                    // The lookup is mapped to another course module so disable and set warning.
                    $attributes = array(
                        'disabled' => 'disabled',
                        'title' => 'ACTIVITY ID :' .
                            $mappingbylookup->coursemodule . ' AND TYPE : ' . $mappingbylookup->activitytype
                    );
                    // Add extra option text.
                    $optiontext .= ' is in use';
                }
            } else if ($lrecord->is_expired()) {
                // The select option shouldn't be displayed.
                return;
            }
        }
        $attributes['data-samisassessmentid'] = $lrecord->samisassessmentid;
        $select->addOption($optiontext, $lrecord->id, $attributes, $select);
    }

    /** Get the transfer form date control
     * @param $mform
     * @param $date
     * @param $datetimeselectoroptions
     */
    protected function transfer_date_control(&$mform, $date, $datetimeselectoroptions) {
        $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from',
            $datetimeselectoroptions, []);
        if (isset($date)) {
            $mform->setDefault('bath_grade_transfer_time_start', $date);
        }
        $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
        $mform->disabledIf('bath_grade_transfer_time_start', 'bath_grade_transfer_samisassessmentid', 'eq', 0);
    }

    /** This is the main function that handles transferring of data via web or cron
     * @param $mappingid
     * @param $grades
     * @return \gradereport_transfer\output\transfer_status $status
     */
    public function do_transfer($mappingid, $grades, $web = false) {
        global $DB;
        $status = null;
        if (!empty($grades)) {
            foreach ($grades as $key => $gradearray) {
                $userid = $key;
                $objgrade = $gradearray['assessment'];
                $this->local_grades_transfer_log->timetransferred = time();
                $this->local_grades_transfer_log->userid = $userid;
                try {
                    if ($this->samisdata->set_export_grade($objgrade)) {
                        // Log it.
                        $this->local_grades_transfer_log->outcomeid = TRANSFER_SUCCESS;
                        $this->local_grades_transfer_log->gradetransferred = $objgrade->mark;
                        $this->local_grades_transfer_log->save();

                        // Lock the mapping.
                        self::lock_mapping($mappingid);
                        return true;
                        if ($web) {
                            // Display result to the user.
                            $status = new \gradereport_transfer\output\transfer_status(
                                $userid,
                                'success',
                                $objgrade->mark);
                        }
                    }

                } catch (\Exception $e) {
                    // Log failure.
                    $this->local_grades_transfer_log->outcomeid = TRANSFER_FAILURE;
                    // Get error id.
                    $this->local_grades_transfer_log->errormessage = $e->getMessage();
                    $this->local_grades_transfer_log->save();

                    if ($web) {
                        // Display result to the user.
                        $status = new \gradereport_transfer\output\transfer_status(
                            $userid,
                            'failure', $objgrade->mark,
                            'SYSTEM FAILURE');
                    }
                }
            }
            return $status;
        }
    }

    /**
     * Lock a given mapping
     * @param $mappingid
     */
    public static function lock_mapping($mappingid) {
        $assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, false);
        if ($assessmentmapping) {
            $assessmentmapping->set_locked(true);
            $assessmentmapping->update();
        }
    }

    /**
     * Return default samis mapping for a Moodle course
     * @param int $moodlecourseid Moodle Course ID
     * @return object $defaultmapping
     */
    private function default_samis_mapping($moodlecourseid, \local_bath_grades_transfer_samis_attributes $attributes) {
        $defaultmapping = null;
        global $DB;
        $sql = "SELECT * FROM {sits_mappings} WHERE courseid = :courseid  AND active = 1 and default_map = 1
        AND acyear = :academicyear AND period_code= :periodslotcode ;";
        $samismapping = $DB->get_record_sql($sql, array(
            'courseid' => $moodlecourseid,
            'academicyear' => $attributes->academicyear,
            'periodslotcode' => $attributes->periodslotcode));
        if (!is_null($samismapping) && $samismapping->active = 1 && $samismapping->default = 1) {
            $defaultmapping = $samismapping;
        }
        return $defaultmapping;
    }

    /**
     * @param $context
     * @param $eventmessage
     */
    protected function raise_custom_error_event($context, $eventmessage) {
        // Origin is always CLI.

        $event = \local_bath_grades_transfer\event\grades_transfer_custom_error::create(
            array(
                'context' => $context,
                'other' => array('message' => $eventmessage)
            )
        );
        $event->trigger();
    }

    /**
     * Cron that processes any automated transfers
     */
    public function cron_transfer($lasttaskruntime) {
        $userstotransfer = null;
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // CRON RUN.
        // Get all mappings .
        // See the ones that are set to auto transfer - done
        // Get me all mapping whose transfer time is null ( they've never been transferred ).
        //$lasttaskruntime = 1507298798;// TODO - DEV TESTING.
        $assessmentmappingids = \local_bath_grades_transfer_assessment_mapping::getAll($lasttaskruntime, true);
        if (!empty($assessmentmappingids)) {
            foreach ($assessmentmappingids as $mappingid) {
                if (!$assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true)) {
                    //throw new \Exception("Assessment mapping could not be found with id=" . $mappingid);
                    return false;
                }
                $context = \context_module::instance($assessmentmapping->coursemodule);
                if (!$moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule)) {
                    //throw new \Exception("Moodle course module no longer exists for id=" . $assessmentmapping->coursemodule);
                }
                // Check that blind marking is not enabled / identities have been revealed.
                list($course, $cm) = get_course_and_cm_from_cmid($assessmentmapping->coursemodule);
                if ($cm->modname == 'assign') {
                    $assign = new \assign(null, $cm, $course);
                    if ($assign->is_blind_marking()) {
                        // Raise an event.
                        $event = \local_bath_grades_transfer\event\assignment_blind_marking_turned_on::create(
                            array(
                                'contextid' => $context->id,
                                'courseid' => $course->id
                            )
                        );
                        $event->trigger();
                        continue;
                    }
                }
                $defaultsamismapping = $this->default_samis_mapping($moodlecourseid, $assessmentmapping->lookup->attributes);
                if (!is_null($defaultsamismapping)) {
                    $userids = array();
                    if ($userstotransfer = $this->get_users_readyto_transfer($mappingid, $moodlecourseid)) {
                        $assessmentgrades = new \local_bath_grades_transfer_assessment_grades();
                        $userids = array_keys($userstotransfer);
                        /*foreach ($userstotransfer as $user) {
                            $userids[] = $user->userid;
                        }*/
                        if (!empty($userids)) {
                            try {
                                $this->transfer_mapping2($mappingid, $userids, $assessmentgrades);
                            } catch (\Exception $e) {
                                $this->raise_custom_error_event($context, $e->getMessage());
                            }
                        } else {
                            echo "++++NO USERS TO TRANSFER++++";
                        }
                    }
                }
                // Transfer mapping.

                // You've done this once, set a lasttrransfertime so it is not picked up again.
                $assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, false);
                if (!isset($assessmentmapping->lasttransfertime)) {
                    $assessmentmapping->lasttransfertime = time();
                    $assessmentmapping->update();
                }
            }
        } else {
            mtrace("NO ASSESSMENT MAPPINGS TO PROCESS");
        }
    }

    /**
     * @param $mappingid
     * @param array $userids
     * @param object $assessmentgrades
     * @return bool
     * @throws Exception
     */
    public function transfer_mapping2($mappingid, $userids = array(), $assessmentgrades) {
        global $DB;
        $singleusertransfer = array();
        // CAN THESE ALL BE PUT INTO ONE TRY?????
        // Get all mapping and course data and check all ok.
        if (!$assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true)) {
            mtrace("Could not get mapping object for id = $mappingid");
            return false;
        }
        $modulecontext = \context_module::instance($assessmentmapping->coursemodule);

        if ($assessmentmapping->get_expired() != 0) {
            $this->raise_custom_error_event($modulecontext,
                "Assessment mapping has expired, id=" . $mappingid);
        }
        if ($assessmentmapping->lookup->is_expired() != 0) {
            $this->raise_custom_error_event($modulecontext,
                "Assessment lookup has expired, lookup id=" . $assessmentmapping->lookup->id);
        }
        if (!$moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule)) {
            throw new \Exception("Moodle course module no longer exists for id=" . $assessmentmapping->coursemodule);
        }
        try {
            $context = \context_module::instance($assessmentmapping->coursemodule);
            $gradestructure = $assessmentgrades->get_grade_strucuture_samis($assessmentmapping->lookup);
            if (empty($gradestructure)) {
                // Trigger an event.
                $event = \local_bath_grades_transfer\event\missing_samis_grade_structure::create(
                    array(
                        'context' => $context,
                        'courseid' => $moodlecourseid,
                    )
                );
                $event->trigger();

                // Throw an exception.
                throw new \Exception("Grade structure missing");
            }
        } catch (\Exception $e) {
            throw $e;
        }

        // All OK, ready to go.
        // Set up static log parameters - common to all transfers for the mapping.
        $this->local_grades_transfer_log->coursemoduleid = $assessmentmapping->coursemodule;
        $this->local_grades_transfer_log->gradetransfermappingid = $assessmentmapping->id;
        $this->local_grades_transfer_log->assessmentlookupid = $assessmentmapping->assessmentlookupid;
        $sprlist = array();
        if (!empty($userids)) {
            foreach ($userids as $userid) {

                $this->local_grades_transfer_log->errormessage = "";

                // Get grade.
                $grade = $this->get_moodle_grade($userid, $assessmentmapping->coursemodule);

                // Pre transfer check (local).
                if ($this->local_precheck_conditions($userid, $grade, $assessmentmapping)) {

                    // Get SPR code and Candidate Number.
                    try {
                        $bucsusername = $DB->get_field('user', 'username', array('id' => $userid));
                        $studentidenfiers = $this->samisdata->get_spr_from_bucs_id_rest($bucsusername);
                    } catch (\Exception $e) {
                        $this->local_grades_transfer_log->outcomeid = COULD_NOT_GET_SPR_CODE;
                        $this->local_grades_transfer_log->userid = $userid;
                        $this->local_grades_transfer_log->timetransferred = time();
                        $this->local_grades_transfer_log->errormessage = $e->getMessage();
                        $this->local_grades_transfer_log->save();
                    }

                    // Pre transfer check (remote).
                    if ($assessmentmapping->lookup->mabpnam === 'N') {
                        $studenidentifier = $studentidenfiers->candidatenumber;
                    } else {
                        $studenidentifier = $studentidenfiers->sprcode;

                    }
                    if ($this->remote_precheck_conditions($userid, $studenidentifier, $gradestructure)) {
                        $gradestructure[$studenidentifier]['assessment']->mark = $grade->finalgrade;
                        $singleusertransfer[$userid] = $gradestructure[$studenidentifier];
                        if (!empty($singleusertransfer)) {
                            $this->do_transfer($mappingid, $singleusertransfer);
                            unset($singleusertransfer[$userid]);

                        }
                    }
                }
            }
        }
    }

    /**
     * @param $samismappingid
     * @param $moodlecourseid
     * @return array|bool
     */
    protected function get_users_readyto_transfer($samismappingid, $moodlecourseid) {
        global $DB;
        $users = array();
        $context = context_course::instance($moodlecourseid);

        $sqlfrom = "
        /***** get the grade transfer mapping *****/
        FROM {local_bath_grades_mapping} gm
        JOIN {local_bath_grades_lookup} gl
            ON gl.id = gm.assessmentlookupid
            -- AND gl.expired IS NULL  -- need to show transfer history if mapping becomes expired

       /***** join students that have equivalent sits mapping *****/
        JOIN {sits_mappings} sm
            ON sm.acyear = gl.academicyear
            AND sm.period_code = gl.periodslotcode
            AND sm.sits_code = gl.samisunitcode
            AND sm.active = 1
            AND sm.default_map = 1
        JOIN {sits_mappings_enrols} me ON me.map_id = sm.id
        JOIN {user_enrolments} ue ON ue.id = me.u_enrol_id -- PROBLEM WITH user_enrolments BEING REMOVED!!!
        JOIN {user} u ON u.id = ue.userid
        JOIN {role_assignments} ra
            ON ra.userid = u.id
            AND contextid = " . $context->id . "
            AND roleid = 5 /* student role */
            AND ra.id = me.ra_id
        /***** join moodle activity information relating to mapping including current grade *****/
        JOIN {course_modules} cm ON cm.id = gm.coursemodule
        JOIN {modules} mo ON mo.id = cm.module
        LEFT JOIN {grade_items} gi
            ON gi.itemmodule = mo.name
            AND gi.iteminstance = cm.instance
        LEFT JOIN {grade_grades} gg
            ON gg.itemid = gi.id
            AND gg.userid = ue.userid

        /***** get time of latest transfer log entry for each student enrolment *****/
        LEFT JOIN
        (
            SELECT
                userid
                , gradetransfermappingid
                , MAX( timetransferred ) AS timetransferred
            FROM {local_bath_grades_log}
            -- WHERE outcomeid <> " . GRADE_ALREADY_EXISTS . "
            GROUP BY userid, gradetransfermappingid
        ) AS last_log
            ON last_log.userid = gg.userid
            AND last_log.gradetransfermappingid = gm.id

        /***** join outcome status   *****/
         LEFT JOIN {local_bath_grades_log} log
            ON log.gradetransfermappingid = last_log.gradetransfermappingid
            AND log.userid = last_log.userid
            AND log.timetransferred = last_log.timetransferred
        LEFT JOIN {local_bath_grades_outcome} oc ON log.outcomeid = oc.id

        WHERE gm.id = $samismappingid
               AND (log.outcomeid NOT IN (" . TRANSFER_SUCCESS . "," . GRADE_QUEUED . ")
               OR log.outcomeid IS NULL) -- already transferred or queued
        AND gg.finalgrade IS NOT NULL
        AND CEIL(gg.finalgrade) = gg.finalgrade
        AND gg.rawgrademax=" . MAX_GRADE;
        try {
            $rs = $DB->get_recordset_sql(" SELECT
              ue.userid
            , gg.finalgrade
            , gg.rawgrademax
            , gg.timemodified AS 'timegraded'
            , log.outcomeid
            " . $sqlfrom);
            if ($rs->valid()) {
                foreach ($rs as $record) {
                    $users[$record->userid] = $record;
                }
            }

        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
        $DB->set_debug(false);
        return $users;

    }

    /** Return the SAMIS users in moodle for a given samis mapping id
     * @param $samismappingid
     * @return array|null
     * @throws Exception
     */
    protected function get_samis_users($samismappingid) {
        global $DB;
        $users = null;
        $sql = " SELECT ue.userid,u.username FROM {sits_mappings} sm
        JOIN {sits_mappings_enrols} me ON me.map_id = sm.id
        JOIN {user_enrolments} ue ON ue.id = me.u_enrol_id -- PROBLEM WITH user_enrolments BEING REMOVED!!!
        JOIN {user} u ON u.id = ue.userid
        WHERE sm.active = 1 AND sm.default_map = 1 AND sm.id = :sits_mapping_id";
        try {
            $usersrecordset = $DB->get_recordset_sql($sql, ['sits_mapping_id' => $samismappingid]);
            foreach ($usersrecordset as $userrecord) {
                $users[] = $userrecord;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return $users;
    }

    /**
     * @param $moodleuserid
     * @return null|SimpleXMLElement
     */
    public function get_spr_from_bucs_id($moodleuserid) {
        global $DB;
        $sprcode = null;
        if (isset($moodleuserid)) {
            // Get username.
            $username = $DB->get_field('user', 'username', ['id' => $moodleuserid]);
            // Pass username to SAMIS to get SPR code.
            $sprcode = $this->samisdata->get_spr_from_bucs_id($username);
        }
        return $sprcode;
    }

    /** Checks done after student SPR code has been retrieved (needed to compare local grades with external SAS export)
     * @param int $userid
     * @param object $grade
     * @param object $assessmentmapping
     * @return mixed
     */
    public function local_precheck_conditions($userid, $grade, $assessmentmapping) {
        global $DB;
        $outcomeid = null;

        // Get last transfer outcome for student...
        $params = array();
        $params["userid"] = $userid;
        $params["coursemoduleid"] = $assessmentmapping->coursemodule;
        $params["gradetransfermappingid"] = $assessmentmapping->id;
        $params["assessmentlookupid"] = $assessmentmapping->assessmentlookupid;

        $sql = "SELECT outcomeid 
                FROM {local_bath_grades_log}
                WHERE userid = ?
                AND coursemoduleid = ?
                AND gradetransfermappingid = ?
                AND assessmentlookupid = ?
                ORDER BY timetransferred DESC
                LIMIT 1";
        $current = $DB->get_record_sql($sql, $params);

        // No grade recorded in Moodle.
        if (empty($grade->finalgrade)) {
            $outcomeid = GRADE_MISSING;
        }

        // Grade not out of 100.
        if ($grade->rawgrademax != MAX_GRADE) {
            $outcomeid = GRADE_NOT_OUT_OF_100;
        }

        // Grade not a whole number is_int.
        if ((float)$grade->finalgrade != round((float)$grade->finalgrade)) {
            $outcomeid = GRADE_NOT_WHOLE_NUMBER;
        }

        if (isset ($outcomeid)) {
            // Create the grade transfer log entry.
            $this->local_grades_transfer_log->outcomeid = $outcomeid;
            $this->local_grades_transfer_log->userid = $userid;
            $this->local_grades_transfer_log->timetransferred = time();
            $this->local_grades_transfer_log->save();
            return false;
        }
        return true;
    }

    /** Checks done after student SPR code has been retrieved (needed to compare local grades with external SAS export)
     * @param int $userid
     * @param string $studentidentifer
     * @param object $gradestructure
     * @return mixed
     */
    public function remote_precheck_conditions($userid, $studentidentifer, $gradestructure) {

        $outcomeid = null;
        // SPR code missing.
        if (empty($studentidentifer)) {
            $outcomeid = COULD_NOT_GET_SPR_CODE;
        } else if (!array_key_exists($studentidentifer, $gradestructure)) {
            // Student not in SAMIS grade structure.
            $outcomeid = GRADE_NOT_IN_STRUCTURE;
        } else if (!empty($gradestructure[$studentidentifer]['assessment']->mark)) {
            // Grade already in SAMIS grade structure.
            $outcomeid = GRADE_ALREADY_EXISTS;
        }

        if (isset ($outcomeid)) {
            // Create the grade transfer log entry.
            $this->local_grades_transfer_log->outcomeid = $outcomeid;
            $this->local_grades_transfer_log->userid = $userid;
            $this->local_grades_transfer_log->timetransferred = time();
            $this->local_grades_transfer_log->save();
            return false;
        }
        return true;
    }

    /** Retrieve Moodle grade for a user on a course module
     * @param $userid
     * @param $coursemodule
     * @return mixed|null
     */
    private function get_moodle_grade($userid, $coursemodule) {
        global $DB;
        $grade = null;
        $params = array();
        $params["userid"] = $userid;
        $params["cm"] = $coursemodule;
        $grade = $DB->get_record_sql("
        SELECT 
          ROUND(gg.finalgrade) AS 'finalgrade' 
        , ROUND(gg.rawgrademax) AS 'rawgrademax' 
        FROM {course_modules} AS cm
        JOIN {modules} AS mo ON mo.id = cm.module
        LEFT JOIN {grade_items} AS gi
            ON gi.itemmodule = mo.name
        AND gi.iteminstance = cm.instance
        LEFT JOIN {grade_grades} AS gg
            ON gg.itemid = gi.id
        AND gg.userid = :userid
        WHERE cm.id = :cm
        ", $params);

        return $grade;
    }

    /***
     * Get Moodle COURSE ID from Coursemodule ID
     * @param $coursemoduleid
     * @return mixed|null
     */
    private function get_moodle_course_id_coursemodule($coursemoduleid) {
        global $DB;
        $moodlecourseid = null;
        $moodlecourseid = $DB->get_field('course_modules', 'course', ['id' => $coursemoduleid]);
        return $moodlecourseid;
    }

    /**
     * Action to perform when module settings a saved in modedit.php form page
     * @param object $formdata
     * @return null
     */
    public function save_form_elements($formdata) {
        // Default actions - do nothing.
        $savemapping = false;
        $createevent = false;

        if (!in_array('mod_' . $formdata->modulename, $this->allowedmods)) {
            return false;
        }
        $formsamisassessmentlookupid = null;

        // Get the assessment lookup id from the posted form data.
        if (isset($formdata->bath_grade_transfer_samis_lookup_id)) {
            $formsamisassessmentlookupid = $formdata->bath_grade_transfer_samis_lookup_id;
        }
        $mapping = new stdClass();
        $mapping->coursemodule = $formdata->coursemodule;
        $mapping->assessmentlookupid = $formsamisassessmentlookupid;
        $mapping->samisassessmentenddate = $formdata->bath_grade_transfer_time_start;
        $mapping->activitytype = $formdata->modulename;

        // Evaluate what actions to take.
        if ($currentassessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($formdata->coursemodule)) {

            $currentassessmentlookupid = $currentassessmentmapping->assessmentlookupid;
            $currentassessmentenddate = $currentassessmentmapping->samisassessmentenddate;
            $mapping->id = $currentassessmentmapping->id;

            if ($formsamisassessmentlookupid != $currentassessmentlookupid) {
                // Mapping has changed.
                $savemapping = true;
                $createevent = true;
            } else if ($currentassessmentenddate != $formdata->bath_grade_transfer_time_start) {
                // Transfer date has changed.
                $savemapping = true;
            }
        } else if ($formsamisassessmentlookupid > 0) {
            // New mapping.
            $savemapping = true;
            $createevent = true;
        }

        if ($savemapping) {
            $newmapping = \local_bath_grades_transfer_assessment_mapping::save_mapping($mapping);
            if ($createevent) {
                // Trigger an event for assessment mapping created.
                $lookupname = $formsamisassessmentlookupid == 0 ? ' None' :
                    \local_bath_grades_transfer_assessment_lookup::get_assessment_name_by_id($formsamisassessmentlookupid);
                $event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
                    array(
                        'context' => \context_module::instance($formdata->coursemodule),
                        'courseid' => $formdata->course,
                        'relateduserid' => null,
                        'other' => array(
                            'lookup_name' => '\'' . $lookupname . '\''
                        )
                    )
                );
                $event->trigger();
            }
        } else {
            // Mapping has not been changed.
            // Check that if the unlock button is checked.
            if (isset($formdata->bath_grade_transfer_samis_unlock_assessment)) {
                if ($currentassessmentmapping->get_locked() == 1 &&
                    $formdata->bath_grade_transfer_samis_unlock_assessment == 1
                ) {
                    // Expire the old one .
                    $mapping->expired = time();
                    // Save mapping settings.
                    $mapping->locked = 0;
                    $newmapping = \local_bath_grades_transfer_assessment_mapping::save_mapping($mapping);
                    // Trigger an event for assessment mapping unlocked.
                    $event = \local_bath_grades_transfer\event\assessment_mapping_unlocked::create(
                        array(
                            'context' => \context_module::instance($formdata->coursemodule),
                            'courseid' => $formdata->course,
                            'relateduserid' => null,
                        )
                    );
                    $event->trigger();
                }
            }
        }
    }

    /**
     * Return SAMIS attributes for a Moodle Coursse from mdl_sits_mapping table
     * @param $moodlecourseid
     * @return array $samisattributes
     */
    public function get_samis_mapping_attributes($moodlecourseid) {
        $samisattributes = array();
        global $DB;
        if (isset($moodlecourseid)) {
            // Check if course exists.
            if ($DB->record_exists('course', ['id' => $moodlecourseid])) {
                // Check if mapping exists ( should be default only).
                if ($this->samis_mapping_exists($moodlecourseid)) {
                    // Fetch the mapping for current year.
                    $records = $DB->get_records('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1,
                        'acyear' => $this->currentacademicyear]);
                    if ($records) {
                        foreach ($records as $record) {
                            // Return Samis attributes object.
                            $samisattributes[] = new local_bath_grades_transfer_samis_attributes(
                                $record->sits_code,
                                $record->acyear,
                                $record->period_code
                            );
                        }
                    }
                }
            }
        }
        return $samisattributes;
    }

    /**
     * sets current academic year in the format 'yyyy/+1' style, such as 2010/1, 2011/2 and the lke
     */
    protected function set_currentacademicyear() {
        $datearray = explode('-', $this->date->format('m-Y'));
        if (intval($datearray[0]) > 7) {
            $this->currentacademicyear = strval(intval($datearray[1])) . '/' . substr(strval(intval($datearray[1]) + 1), -1);
            $this->currentacademicyearstart = new DateTime($datearray[1] . '-07-31 00:00:00');
            $this->academicyearend = new DateTime($datearray[1] + 1 . '-07-31 00:00:00');
        } else {
            $this->currentacademicyear = strval(intval($datearray[1]) - 1) . '/' . substr(strval(intval($datearray[1])), -1);
            $this->currentacademicyearstart = new DateTime($datearray[1] - 1 . '-07-31 00:00:00');
            $this->currentacademicyearend = new DateTime($datearray[1] . '-07-31 00:00:00');
        }
    }

    /**
     * @param $moodlecourseid
     * @return bool
     */
    public function samis_mapping_exists($moodlecourseid) {
        global $DB;
        return $DB->record_exists('sits_mappings', ['courseid' => $moodlecourseid,
            'default_map' => 1,
            'acyear' => $this->currentacademicyear]);
    }
}