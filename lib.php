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
//TODO -- minus 2 / plus 1 ACADEMIC YEAR for Grade Transfer Report Logs
//TODO -- Also allow them to transfer for previous academic year(s) as long as the lookup is still valid
// TODO -- check for unenrolled students in SAMIS ( Ask Martin ).
//TODO -- plugin_extend_coursemodule_edit_post_actions use this to extend later?
//TODO -- What happens when the data changes but the mapping doesn't ?

/**
 * Class local_bath_grades_transfer
 */
const MAX_GRADE = 100;
const TRANSFER_SUCCESS = 1;
const GRADE_MISSING = 2;
const TRANSFER_FAILURE = 3;
const GRADE_ALREADY_EXISTS = 4;
const GRADE_NOT_IN_MOODLE_COURSE = 5;
const GRADE_NOT_OUT_OF_100 = 6;
const GRADE_NOT_IN_STRUCTURE = 7;
const GRADE_QUEUED = 8;
const GRADE_NOT_WHOLE_NUMBER = 9;
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
        $this->samis_data = new \local_bath_grades_transfer_external_data();
        $this->allowedmods = explode(',', get_config('local_bath_grades_transfer', 'bath_grades_transfer_use'));
        $this->local_grades_transfer_log = new \local_bath_grades_transfer_log();
        $this->local_grades_transfer_error = new \local_bath_grades_transfer_error();
        $this->date = new DateTime();
        $this->assessmentmapping = new \local_bath_grades_transfer_assessment_mapping();
        //SET DUMMY TESTING ACADEMIC YEAR
        $this->currentacademicyear = '2016/7';  //TODO - COMMENT THIS OUT - IT'S FOR TESTING.
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
        //Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);

        // Check that config is set.
        if (!in_array($modulename, $this->allowedmods) || !($this->is_admin_config_present())) {
            return true;
        }
        // Render the header.
        $mform->addElement('header', 'local_bath_grades_transfer_header', 'Grades Transfer');


        ////// BUILD CONTROLS /////////////
        // Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {
            /****** FETCH (ANY) NEW REMOTE ASSESSMENTS AND DO HOUSEKEEPING. ******/

            try {
                // TODO Do we need to query the samis API on every refresh ?
                // TODO Think about when a lookup comes back ( un-expires?)

                $this->sync_remote_assessments($COURSE->id);
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
            $mform->addElement('html', "<p class=\"alert alert-warning\"><i class=\"fa fa-ban\" aria-hidden=\"true\"></i> " . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</p>");
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
            if ($assessmentmapping->samisassessmentenddate != '0') {
                $samisassessmentenddate = userdate($assessmentmapping->samisassessmentenddate);
            } else {
                $samisassessmentenddate = 'Not Set';
            }
            $locked = $assessmentmapping->get_locked();

            if ($locked) {
                $context = context_module::instance($cmid);
                if (has_capability('local/bath_grades_transfer:unlock_assessment_mapping', $context)) {
                    $mform->addElement('checkbox', 'bath_grade_transfer_samis_unlock_assessment', '',
                        get_string('bath_grade_transfer_samis_unlock_assessment', 'local_bath_grades_transfer'));
                    $mform->addElement('html',
                        "<div id = 'unlock-msg' style='display: none;'><p class=\"alert-warning alert\">" .
                        get_string('unlock_warning', 'local_bath_grades_transfer') . "</p></div>");
                    $mform->addHelpButton('bath_grade_transfer_samis_unlock_assessment',
                        'bath_grade_transfer_samis_unlock_assessment', 'local_bath_grades_transfer');
                }
                $mform->addElement('html', "<p class=\"alert-warning alert\"><i class=\"fa fa-lock\" aria-hidden=\"true\"></i> " .
                    get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</p>");
                $dropdownattributes['disabled'] = 'disabled';
                $select = $mform->addElement('select',
                    'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $select->addOption("None", 0, $dropdownattributes);
                foreach ($lookuprecords as $lrecord) {
                    if ($lrecord->id == $assessmentmapping->assessmentlookupid) {
                        // Something is mapped.
                        $this->select_option_format($lrecord->mabname .
                            " ( Wt: " . $lrecord->mabperc . "% )", $lrecord->id, $dropdownattributes, $select);
                        $select->setSelected($lrecord->id);
                        if ($lrecord->is_expired()) {
                            // LOCKED AND EXPIRED.
                            $mform->addElement('html',
                                "<p class=\"alert-danger alert\">
$lrecord->mabname exists but the lookup has now expired !!! </p>");
                        }
                    } else {
                        $this->select_option_format(
                            $lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )",
                            $lrecord->id, $dropdownattributes, $select);
                    }
                }
                $mform->addHelpButton('bath_grade_transfer_samis_lookup_id', 'bath_grade_transfer_samis_lookup_id',
                    'local_bath_grades_transfer');
                $mform->addElement('static', 'bath_grade_transfer_time_start_locked', 'Transfer grades from',
                    $samisassessmentenddate);
                $mform->addHelpButton('bath_grade_transfer_time_start_locked', 'bath_grade_transfer_time_start',
                    'local_bath_grades_transfer');
                $mform->addElement('hidden', 'bath_grade_transfer_time_start', $assessmentmapping->samisassessmentenddate);
                $mform->setType('bath_grade_transfer_time_start', PARAM_INT);
            } else {
                // MAPPING IS NOT LOCKED.
                $select = $mform->addElement('select',
                    'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $mform->disabledIf('bath_grade_transfer_samis_lookup_id', 'grade[modgrade_point]', 'neq', 100);
                $mform->addHelpButton('bath_grade_transfer_samis_lookup_id', 'bath_grade_transfer_samis_lookup_id',
                    'local_bath_grades_transfer');


                // ADD BLANK OPTION.
                $select->addOption("None", 0, $dropdownattributes);

                foreach ($lookuprecords as $lrecord) {
                    if ($lrecord->is_expired()) {
                        $mform->addElement('html', "<p class=\"alert-danger alert\">" .
                            get_string('bath_grade_transfer_samis_assessment_expired', 'local_bath_grades_transfer', $lrecord) .
                            "</p>");
                        continue;
                    }
                    $this->display_option($lrecord, $assessmentmapping, $dropdownattributes, $select, $cmid);
                }
                /******** DATE CONTROL ******/
                $this->transfer_date_control($mform, $assessmentmapping->samisassessmentenddate, $datetimeselectoroptions);
            }
        } else {
            if (!empty($lookuprecords)) {
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to',
                    [], []);
                $select->addOption("None", 0, $dropdownattributes);
                foreach ($lookuprecords as $lrecord) {
                    if ($lrecord->is_expired()) {
                        continue;
                    }
                    $this->display_option($lrecord, $assessmentmapping, $dropdownattributes, $select, $cmid);
                }
                /******** DATE CONTROL ******/
                $this->transfer_date_control($mform, null, $datetimeselectoroptions);
            } else {
                $mform->addElement('html', "<p class=\"alert-info alert\">No lookup records were found </p>");
            }
        }
    }

    /** Get the transfer form control
     * @param $mform
     * @param $date
     * @param $datetimeselectoroptions
     */
    protected function transfer_date_control(&$mform, $date, $datetimeselectoroptions) {
        if (!is_null($date)) {
            $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from',
                $datetimeselectoroptions, []);
            $mform->setDefault('bath_grade_transfer_time_start', $date);
        } else {
            $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from',
                $datetimeselectoroptions, []);
        }
        $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
        $mform->disabledIf('bath_grade_transfer_time_start', 'bath_grade_transfer_samisassessmentid', 'eq', 0);
    }

    private function display_option($lrecord, $assessmentmapping, $attributes, &$select, $cmid) {
        if (!empty($assessmentmapping) && $lrecord->id == $assessmentmapping->assessmentlookupid) {
            $select->setSelected($lrecord->id);
            $select->addOption($lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )", $lrecord->id, $attributes, $select);
        } else {
            // Other lookups.
            $mappingbylookup = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($lrecord->id);
            if (!empty($mappingbylookup)) {
                if ($cmid != $mappingbylookup->coursemodule) {
                    $select->addOption($lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )
                    is in use", $lrecord->id, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' .
                        $mappingbylookup->coursemodule . ' AND TYPE : ' . $mappingbylookup->activitytype], $select);
                }
            } else {
                $select->addOption($lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )", $lrecord->id, $attributes, $select);

            }

        }
    }

    /**
     * @param $title
     * @param $value
     * @param $attributes
     * @param $select
     */
    public function select_option_format($title, $value, $attributes, &$select, $cmid = null) {
        // Check that the record_id is mapped to an assessment mapping.
        if (\local_bath_grades_transfer_assessment_mapping::exists_by_lookup_id($value)) {
            // Fetch the mapping.
            $assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($value);
            if (!empty($assessmentmapping)) {
                if ($cmid != $assessmentmapping->coursemodule) {
                    // It is not mapped to the current course module.
                    $select->addOption($title . " is in use", $value, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' .
                        $assessmentmapping->coursemodule . ' AND TYPE : ' . $assessmentmapping->activitytype]);
                } else {
                    // Lookup is not mapped, show as is.
                    $select->addOption($title, $value, $attributes);
                }
            }


        }
    }

    /**
     * @param $lrecord
     * @param $select
     */
    protected function display_select_options($lrecord, &$select) {

        if ($this->assessmentmapping->exists_by_lookup_id($lrecord->id)) {
            // Get the mapping to get the course ID.
            $assessmentmapping = $this->assessmentmapping->get_by_lookup_id($lrecord->id);
            if (!empty($assessmentmapping)) {
                $this->select_option_format($lrecord->mabname . " is in use", $lrecord->id,
                    ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' .
                        $assessmentmapping->coursemodule . ' AND TYPE : ' . $assessmentmapping->activitytype], $select);
            }

        } else {
            $this->select_option_format($lrecord->mabname . " ( Wt: " . $lrecord->mabperc . "% )", $lrecord->id, [], $select);
            $select->setSelected($lrecord->id);
        }
    }
    
    /**
     * All-in-one method that deals with fetching new and expiring old lookups
     * @param null $moodlecourseid
     * @return bool
     * @throws Exception
     * @author John Illsley
     */
    public function sync_remote_assessments($moodlecourseid = null) {
        global $DB;

        if (is_null($moodlecourseid)) {
            $samisattributeslist = local_bath_grades_transfer_samis_attributes::attributes_list($this->currentacademicyear);
        } else {
            $samisattributeslist = $this->get_samis_mapping_attributes($moodlecourseid);
        }

        if (!empty($samisattributeslist)) {
            try {
                foreach ($samisattributeslist as $samisattributes) {
                    // We don't need to deal with empty arrays.
                    if ($samisattributes instanceof \local_bath_grades_transfer_samis_attributes == false) {
                        return false;
                    }
                    $remotedata = $this->samis_data->get_remote_assessment_details_rest($samisattributes);
                    $remotedata = array_pop($remotedata);
                    $localdata = $this->get_local_assessment_details($samisattributes);
                    $remoteassessments = array_map("self::lookup_transform", $remotedata); // Key fields for comparison.
                    $localassessments = array_map("self::lookup_transform", $localdata); // Key fields for comparison.

                    // Expire obsolete lookups.
                    $update = array();
                    $update['expired'] = time();
                    $expirelookups = array_diff($localassessments, $remoteassessments); // Assessments in local but not in remote.
                    foreach ($expirelookups as $k => $v) {
                        $update['id'] = $k;
                        $DB->update_record('local_bath_grades_lookup', $update);
                    }

                    // Add new lookups.
                    $addlookups = array_diff($remoteassessments, $localassessments); // Assessments in remote but not in local.
                    foreach ($addlookups as $k => $addlookup) {
                        $lookup = array_merge($remotedata[$k], (array)$samisattributes);
                        $lookup["samisassessmentid"] = $lookup["mapcode"] . '_' . $lookup["mabseq"];
                        $lookup["timecreated"] = time();
                        $lookup["occurrenceid"] = 0;
                        $DB->insert_record('local_bath_grades_lookup', $lookup);
                    }

                    // Check for updates in lookups.
                    $checkupdates = array_intersect($localassessments, $remoteassessments);
                    foreach ($checkupdates as $localkey => $checkupdate) {
                        $remotekey = array_search($checkupdate, $remoteassessments);
                        if ($localdata[$localkey] != $remotedata[$remotekey]) {
                            // At least one field has changed so update.
                            $localdata[$localkey] = $remotedata[$remotekey];
                            $localdata[$localkey]["id"] = $localkey;
                            $DB->update_record('local_bath_grades_lookup', $localdata[$localkey]);
                        }
                    }
                }
                return true;
            } catch (Exception $e) {
                echo "Throwing Exception #4";
                var_dump($e->getMessage());
                throw new Exception($e->getMessage());
            }
        }
    }

    /** Serialises the unique key fields of a mapping for easy comparison
     * @param $mapping array
     * @return string
     */
    private static function lookup_transform($mapping) {
        $mapping = (array)$mapping;
        $a = array();
        $a["mapcode"] = $mapping["mapcode"];
        $a["mabseq"] = $mapping["mabseq"];
        return serialize($a);
    }

    public function get_local_assessment_details($samisattributes) {
        global $DB;
        $conditions = array();
        $conditions['expired'] = 0;
        $conditions['samisunitcode'] = $samisattributes->samisunitcode;
        $conditions['occurrence'] = $samisattributes->occurrence;
        $conditions['academicyear'] = $samisattributes->academicyear;
        $conditions['periodslotcode'] = $samisattributes->periodslotcode;

        $localassessments = $DB->get_records('local_bath_grades_lookup',
            $conditions, '', 'id, mapcode, mabseq, astcode, mabperc, mabname');

        foreach ($localassessments as $k => $v) {
            unset($localassessments[$k]->id);
        }
        return $localassessments;
    }

    /** This is the main function that handles transferring of data via web or cron
     * @param $grades
     * @return \gradereport_transfer\output\transfer_status $status
     */
    public function do_transfer($mappingid, $grades, $web = false) {
        global $DB;
        $status = null;
        if (!empty($grades)) {
            foreach ($grades as $key => $gradeArray) {
                $userid = $key;
                $objgrade = $gradeArray['assessment'];
                var_dump($objgrade);
                $this->local_grades_transfer_log->timetransferred = time();
                $this->local_grades_transfer_log->userid = $userid;
                try {
                    echo "++++++Passing Grade for $userid ....++++++";
                    if ($this->samis_data->set_export_grade($objgrade)) {
                        // Log it.
                        $this->local_grades_transfer_log->outcomeid = TRANSFER_SUCCESS;
                        $this->local_grades_transfer_log->gradetransferred = $objgrade->mark;
                        $this->local_grades_transfer_log->save();

                        // Lock the mapping.
                        echo "++++Lock mapping++++";
                        self::lock_mapping($mappingid);
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
                    echo "logging failure";
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
        }
        $assessmentmapping->update();

    }

    /**
     * Return default samis mapping for a Moodle course
     * @param $moodlecourseid Moodle Course ID
     * @return $defaultmapping
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
     * Cron that processes any automated transfers
     */
    public function cron_transfer($lasttaskruntime) {
        global $CFG;
        $userstotransfer = null;
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
                if (!$moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule)) {
                    //throw new \Exception("Moodle course module no longer exists for id=" . $assessmentmapping->coursemodule);
                }
                $defaultsamismapping = $this->default_samis_mapping($moodlecourseid, $assessmentmapping->lookup->attributes);
                if (!is_null($defaultsamismapping)) {
                    if ($userstotransfer = $this->get_users_readyto_transfer($mappingid)) {
                        foreach ($userstotransfer as $user) {
                            $userids[] = $user->userid;
                            $this->transfer_mapping2($mappingid, $userids);
                        }
                    } else {
                        echo "++++NO USERS TO TRANSFER++++";
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


    public function transfer_mapping2($mappingid, $userids = array(), $source = 'web') {
        global $DB;
        $singleusertransfer = array();
        // CAN THESE ALL BE PUT INTO ONE TRY?????
        // Get all mapping and course data and check all ok.
        if (!$assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true)) {
            throw new \Exception("Assessment mapping could not be found with id=" . $mappingid);
        }
        if ($assessmentmapping->get_expired() != 0) {
            throw new \Exception("Assessment mapping has expired, id=" . $mappingid);
        }
        if ($assessmentmapping->lookup->expired != 0) {
            throw new \Exception("Assessment lookup has expired, lookup id=" . $assessmentmapping->lookup->id);
        }
        if (!$moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule)) {
            throw new \Exception("Moodle course module no longer exists for id=" . $assessmentmapping->coursemodule);
        }
        try {
            $context = \context_module::instance($assessmentmapping->coursemodule);
            $gradestructure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis(
                $assessmentmapping->lookup);
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
        $spr_list = array();
        var_dump($userids);
        if (!empty($userids)) {
            foreach ($userids as $userid) {

                $this->local_grades_transfer_log->errormessage = "";

                // Get grade.
                $grade = $this->get_moodle_grade($userid, $assessmentmapping->coursemodule);
                var_dump($grade);

                // Pre transfer check (local).
                if ($this->local_precheck_conditions($userid, $grade, $assessmentmapping)) {
                    echo "LOCAL PRECHECK PASSED";

                    // Get SPR code.
                    $bucsusername = $DB->get_field('user', 'username', array('id' => $userid));
                    try {
                        $spr_code = $this->samis_data->get_spr_from_bucs_id_rest($bucsusername);
                        var_dump($spr_code);
                        $spr_list[$spr_code] = 1; // For checking if any are missing at the end.
                    } catch (\Exception $e) {
                        $this->local_grades_transfer_log->outcomeid = COULD_NOT_GET_SPR_CODE;
                        $this->local_grades_transfer_log->userid = $userid;
                        $this->local_grades_transfer_log->timetransferred = time();
                        $this->local_grades_transfer_log->errormessage = $e->getMessage();
                        $this->local_grades_transfer_log->save();
                        throw $e;
                    }

                    // Pre transfer check (remote).
                    echo "+++ NOW DOING REMOTE PRECHECKS";
                    if ($this->remote_precheck_conditions($userid, $spr_code, $gradestructure)) {
                        echo "IVE PASSED REMOTE PRECHECK";
                        $gradestructure[$spr_code]['assessment']->mark = $grade->finalgrade;
                        echo "NEW GR STR";
                        $singleusertransfer[$userid] = $gradestructure[$spr_code];
                        if (!empty($singleusertransfer)) {
                            $this->do_transfer($mappingid, $singleusertransfer);
                        }
                    }
                }
            }
        }


        foreach ($gradestructure as $k => $v) {
            // Check if student exists in course???
        }
    }

    public function transfer_mapping($mappingid, $userids = array(), $source = 'web') {
        // Get the mapping object for the ID.
        global $DB;
        $transferstatuses = null;
        $usergrades = $samusers = array();
        $assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true);
        $this->local_grades_transfer_log->coursemoduleid = $assessmentmapping->coursemodule;
        $this->local_grades_transfer_log->gradetransfermappingid = $assessmentmapping->id;
        if (isset($assessmentmapping->lookup) && $objlookup = $assessmentmapping->lookup) {
            $moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule);
            // Check that the lookup exists in SAMIS.
            $lookup = \local_bath_grades_transfer_assessment_lookup::get($objlookup->id);
            $gradestructure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);
            $this->local_grades_transfer_log->assessmentlookupid = $lookup->id;
            if ($gradestructure = $this->get_grade_structure_from_samis($objlookup, $moodlecourseid)) {
                if (isset($moodlecourseid)) {
                    $defaultsamismapping = $this->default_samis_mapping($moodlecourseid, $objlookup->attributes);
                    if (!is_null($defaultsamismapping)) {
                        // Get SAMIS USERS FOR THIS MAPPING.
                        $samisusers = $this->get_samis_users($defaultsamismapping->id);
                        foreach ($samisusers as $key => $objuser) {
                            $samusers[] = $objuser->userid;
                        }
                        if (!empty($userids)) {
                            foreach ($userids as $userid) {
                                if (empty($gradestructure)) {
                                    mtrace("NO GRADE STRUCTURE BBYYYEEEEE!!!!");
                                    // There is no point going forward.
                                    $this->local_grades_transfer_log->userid = $userid;
                                    $this->local_grades_transfer_log->outcomeid = GRADE_STRUCTURE_EMPTY;
                                    $this->local_grades_transfer_log->save();
                                    return true;
                                }
                                // Start the time.
                                // Check that the user is part of the SAMIS users group.
                                if (in_array($userid, $samusers)) {
                                    $bucsusername = $DB->get_field('user', 'username', array('id' => $userid));
                                    $bucsusername = $bucsusername . 'x'; //TODO -- DEV TESTING
                                    $usergrades[$userid] = $this->get_moodle_grade($userid, $assessmentmapping->coursemodule);
                                    if (!empty($usergrades[$userid]->finalgrade)) {
                                        try {
                                            echo $bucsusername;
                                            $usergrades[$userid]->spr_code = $this->samis_data->get_spr_from_bucs_id_rest(
                                                $bucsusername
                                            );
                                        } catch (\Exception $e) {
                                            echo "Could not get SPR CODE for $bucsusername";
                                            /*$transferstatuses = new \gradereport_transfer\output\transfer_status(
                                                $userid,
                                                'failure',
                                                null,
                                                "Could not get SPR CODE for $bucsusername"
                                            );*/
                                            echo "++++moving on to the next user ++++++";
                                            unset($usergrades[$userid]);
                                            continue;
                                        }
                                    } else {
                                        // Log it as no grade in Moodle.
                                        // Not dealing with empty grades.
                                        $this->local_grades_transfer_log->userid = $userid;
                                        $this->local_grades_transfer_log->outcomeid = GRADE_NOT_IN_MOODLE_COURSE;
                                        $this->local_grades_transfer_log->save();
                                        // Remove them from the list.
                                        unset($usergrades[$userid]);
                                        $transferstatuses = new \gradereport_transfer\output\transfer_status(
                                            $userid,
                                            'failure',
                                            null,
                                            'No grade to transfer'
                                        );
                                    }
                                    // var_dump($usergrades);

                                }

                            }
                            if (!empty($usergrades)) {
                                $gradestopass = $this->precheck_conditions($usergrades, $gradestructure, $assessmentmapping, false);
                                var_dump($gradestopass);
                                if (!empty($gradestopass)) {
                                    $transferstatuses = $this->do_transfer($gradestopass);
                                    /*if (array_key_exists('statuses', $gradestopass)) {
                                        if (!empty($gradestopass['statuses'])) {
                                            $transferstatuses = $gradestopass['statuses'];
                                        } else {
                                            $transferstatuses = $this->do_transfer($gradestopass);

                                        }
                                    }*/

                                }
                            }
                        }
                        return $transferstatuses;
                    }
                }
            }

        } else {
            // Continue.
            mtrace("no lookup for this mapping.skipping");
        }
    }

    protected function get_users_readyto_transfer_2($samismappingid, $assessmentmapping) {
        // Get the samis students.
        $samisusers = $this->get_samis_users($samismappingid);
        // For each user make sure get the grade transfer log and make sure log.outcomeid is NOT grade trans
        // ferred or already in queue.
        foreach ($samisusers as $key => $objuser) {
            $this->get_transfer_log(3699, $assessmentmapping->id);
            die;
        }
        //var_dump($usergrades);
        die;

        // Join this to the grade transfer logs to get their current outcome.


    }

    protected function get_transfer_log($userid, $mappingid) {
        global $DB;
        //$DB->set_debug(true);
        $where = " userid = ? AND gradetransfermappingid = 
        ?
         AND (outcomeid NOT IN (" . TRANSFER_SUCCESS . "," . GRADE_QUEUED . ") 
               OR outcomeid IS NULL) -- already transferred or queued";
        //$transferstatus = $DB->get_records_select_menu('local_bath_grades_log',$where,array('userid'=> $userid,
        //  'mappingid'=>$mappingid),'','userid,MAX(timetransferred)');
        $sql = "SELECT MAX(timetransferred) FROM {local_bath_grades_log} 
WHERE userid = ? AND gradetransfermappingid = 
        ?  AND (outcomeid NOT IN (" . TRANSFER_SUCCESS . "," . GRADE_QUEUED . ") 
               OR outcomeid IS NULL) -- already transferred or queued";
        //$transferstatus = $DB->get_records('local_bath_grades_log',array('userid'=> $userid,
        //'gradetransfermappingid'=>$mappingid));
        $transferstatus = $DB->get_record_sql($sql, array('userid' => $userid,
            'gradetransfermappingid' => $mappingid));
        var_dump($transferstatus);
        //$DB->set_debug(false);

    }

    protected function get_users_readyto_transfer($samismappingid) {
        global $DB;
        $users = array();
        $DB->set_debug(true);
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
            echo "Getting username for $moodleuserid";
            // Get username.
            $username = $DB->get_field('user', 'username', ['id' => $moodleuserid]);
            // Pass username to SAMIS to get SPR code.
            $sprcode = $this->samis_data->get_spr_from_bucs_id($username);
        }
        return $sprcode;
    }

    /** Checks done after student SPR code has been retrieved (needed to compare local grades with external SAS export)
     * @param $moodleusergrades
     * @param $remotegradestructure
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

        // Check if already queued or already transfered.
        /*if ($current->outcomeid == GRADE_QUEUED || $current->outcomeid == TRANSFER_SUCCESS) {
            // Do nothing!
            echo "DO NOTHING";
            die;
            return false;
        }*/

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
     * @param $moodleusergrades
     * @param $remotegradestructure
     * @return mixed
     */
    public function remote_precheck_conditions($userid, $spr_code, $gradestructure) {

        $outcomeid = null;

        // SPR code missing.
        if (empty($spr_code)) {
            $outcomeid = COULD_NOT_GET_SPR_CODE;
        }

        // Student not in SAMIS grade structure.
        if (!array_key_exists($spr_code, $gradestructure)) {
            $outcomeid = GRADE_NOT_IN_STRUCTURE;
        }

        // Grade already in SAMIS grade structure.
        if (!empty($gradestructure[$spr_code]['assessment']->mark)) {
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

    /**
     * @param $moodleusergrades
     * @param $remotegradestructure
     * @return mixed
     */
    public function precheck_conditions($moodleusergrades, $remotegradestructure, $assessmentmapping, $web = false) {
        $oktotransfer = false;
        $status = null;
        $finalgradestruct = array();
        //TODO Change this when going LIVE -- DEV TESTING
        //echo "+++++++++++ PRECHECKING CONDITIONS+++++++++++++++ \n\n";
        // ##################  EMPTY MOODLE GRADES OR EMPTY STRUCTURE
        if (empty($moodleusergrades) || empty($remotegradestructure)) {
            echo "EMPTY MOODLE GRADES OR EMPTY STRUCTURE";
            return true;
        }
        var_dump($moodleusergrades);
        // 1. Check against Moodle grades.
        foreach ($moodleusergrades as $moodleuserid => $objmoodlegrade) {
            $oktotransfer = true;
            $this->local_grades_transfer_log->userid = $moodleuserid;
            //echo "CHECKING CONDITIONS FOR $moodleuserid \n\n";
            // ##################  CONDITION 1 : EMPTY MOODLE GRADE// ##################  CONDITION 2 : MAX GRADE NOT OUT OF 100
            if ($objmoodlegrade->rawgrademax != MAX_GRADE) {
                // Max grade not satisfied.
                echo "Setting OUTCOME to 6";
                $this->local_grades_transfer_log->outcomeid = GRADE_NOT_OUT_OF_100;
                echo "Not out of 100";
                $this->local_grades_transfer_log->timetransferred = time();
                $this->local_grades_transfer_log->save();
                if ($web) {
                    $status = new \gradereport_transfer\output\transfer_status(
                        $objmoodlegrade->userid, 'failure', null, 'Grade not OUT of 100'
                    );
                }
                continue;
            }

            if (array_key_exists($objmoodlegrade->spr_code, $remotegradestructure)) {
                // OK, Student found in the RGS
                // See if that user has a grade.
                $samisgrade = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getMark();
                // CONDITION 4 : GRADE ALREADY EXISTS IN SAMIS.
                if (!empty($samisgrade)) {
                    $this->local_grades_transfer_log->outcomeid = GRADE_ALREADY_EXISTS;
                    $this->local_grades_transfer_log->timetransferred = time();
                    $this->local_grades_transfer_log->save();
                    if ($web) {
                        $status = new \gradereport_transfer\output\transfer_status($moodleuserid, 'failure', null,
                            'Grade Already Exists in SAMIS');
                    }

                    // echo "Grade already exists in SAMIS . skipping \n\n";
                    continue;
                }
                if ($oktotransfer == false) {
                    echo "UNSETTING GRADE FOR $objmoodlegrade->spr_code \n \n";
                    unset($remotegradestructure[$objmoodlegrade->spr_code]);
                } else {
                    //ADD TO BAG
                    /*echo "Adding to the bag. " . $objmoodlegrade->finalgrade;*/
                    $passgradeobject = new local_bath_grades_transfer_assessment_grades();
                    $passgradeobject->student = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getStudent();
                    $passgradeobject->name = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getName();
                    $passgradeobject->assess_pattern = $remotegradestructure[$objmoodlegrade->spr_code]
                    ['assessment']->getAssessPattern();
                    $passgradeobject->assess_item = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getAssessItem();
                    $passgradeobject->attempt = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getAttempt();
                    $passgradeobject->grade = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getGrade();
                    $passgradeobject->setMark($objmoodlegrade->finalgrade);
                    $passgradeobject->year = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getYear();
                    $passgradeobject->period = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getPeriod();
                    $passgradeobject->module = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getModule();
                    $passgradeobject->occurrence = $remotegradestructure[$objmoodlegrade->spr_code]['assessment']->getOccurrence();
                    $passgradeobject->userid = $moodleuserid;
                    $passgradeobject->mappingid = $assessmentmapping->id;
                    $finalgradestruct[$objmoodlegrade->spr_code]['assessment'] = $passgradeobject;
                }
            } else {
                // The user is probably a manual user in MOODLE ?
                //The grade str bit for that user doesnt exist in SAMIS
                // ##################  CONDITION 3 : STUDENT NOT IN GRADE STRUCTURE.
                echo "Something else $objmoodlegrade->spr_code . skipping\n\n";
                continue;
            }
        }
        if ($web) {
            $finalgradestruct['statuses'] = $status;
        }
        return $finalgradestruct;
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

    /**
     * @param $samismappingid
     * @return array
     */
    private function get_users_samis_mapping($samismappingid) {
        global $DB;
        $users = array();
        // TODO Change this to samisv1 when going live !!!!.
        $sql = "SELECT DISTINCT (u.id) FROM {samis_mapping} AS sm JOIN {samis_mapping_enrolments} AS me ON me.mapping_id = $samismappingid
                                        JOIN {user_enrolments} AS ue ON ue.id = me.user_enrolment_id -- PROBLEM WITH user_enrolments BEING REMOVED!!!
                                        JOIN {user} AS u ON u.id = ue.userid";
        $rs = $DB->get_recordset_sql($sql);
        if ($rs->valid()) {
            // The recordset contains records.
            foreach ($rs as $record) {
                $users[] = $record->id;
            }
        }
        return $users;
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
     * Fetch global config settings for the plugins
     * @param $modulename
     * @return mixed
     */
    public static function get_config_settings($modulename) {
        $pluginconfig = get_config('local_bath_grades_transfer', 'bath_grades_transfer_use_' . $modulename);
        return $pluginconfig;
    }

    /**
     *
     * @param $samisassessmentid
     * @param $remoteassessments
     * @return bool
     */
    public function samisassessmentid_valid($samisassessmentid, $remoteassessments) {

        // Fetch samis attributes.
        $valid = false;
        foreach ($remoteassessments as $mapcode => $arrassessment) {
            foreach ($arrassessment as $key => $objassessment) {
                $remotesamisassessmentid = $objassessment->mapcode . '_' . $objassessment->mabseq;
                if ($remotesamisassessmentid == $samisassessmentid) {
                    // Assessment ID is still valid.
                    $valid = true;
                }
            }
        }
        return $valid;
    }


    /**
     * Action to perform when module settings a saved in modedit.php form page
     * @param $data
     */
    public function save_form_elements($data) {
        // Get time start.
        global $USER;
        $mappingchanged = false;
        if ($data->modulename !== 'assign') {
            return true;
        }
        // Get the assessment lookup id from the posted form data.
        $formsamisassessmentlookupid = $data->bath_grade_transfer_samis_lookup_id;

        // Previous mapping.
        if ($currentassessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($data->coursemodule)) {
            $currentassessmentlookupid = $currentassessmentmapping->assessmentlookupid;
            $current_assessment_end_date = $currentassessmentmapping->samisassessmentenddate;
            if ($formsamisassessmentlookupid != $currentassessmentlookupid || $current_assessment_end_date
                != $data->bath_grade_transfer_time_start
            ) {
                $mappingchanged = true;
            }
            if ($mappingchanged) {
                $this->update_assessment_mapping($data, $currentassessmentmapping);
                // Trigger an event for assessment mapping created.
                $lookup_name = $formsamisassessmentlookupid == 0 ? ' None' :
                    \local_bath_grades_transfer_assessment_lookup::get_assessment_name_by_id
                    (
                        $formsamisassessmentlookupid
                    );
                if ($formsamisassessmentlookupid != $currentassessmentlookupid) {
                    $event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
                        array(
                            'context' => \context_module::instance($data->coursemodule),
                            'courseid' => $data->course,
                            'relateduserid' => null,
                            'other' => array(
                                'lookup_name' => '\'' . $lookup_name . '\''
                            )
                        )
                    );
                    $event->trigger();
                }

            } else {
                // Mapping has not been changed.
                // Check that if the unlock button is checked.
                if (isset($data->bath_grade_transfer_samis_unlock_assessment)) {
                    if ($currentassessmentmapping->get_locked() == 1 &&
                        $data->bath_grade_transfer_samis_unlock_assessment == 1
                    ) {
                        // Expire the old one .
                        $currentassessmentmapping->expire_mapping(1);
                        // Save mapping settings.
                        $this->update_assessment_mapping($data, $currentassessmentmapping);
                        // Trigger an event for assessment mapping unlocked.
                        $event = \local_bath_grades_transfer\event\assessment_mapping_unlocked::create(
                            array(
                                'context' => \context_module::instance($data->coursemodule),
                                'courseid' => $data->course,
                                'relateduserid' => null,
                            )
                        );
                        $event->trigger();
                    }
                }
            }
        } else {
            //No previous mapping.
            if ($formsamisassessmentlookupid != 0 || $data->bath_grade_transfer_time_start !== 0) {
                // Mapping has never been defined, create it.
                $this->create_new_mapping($data);
                $lookup_name = $formsamisassessmentlookupid == 0 ? ' Not Set' :
                    \local_bath_grades_transfer_assessment_lookup::get_assessment_name_by_id
                    (
                        $formsamisassessmentlookupid
                    );
                // Trigger an event for assessment mapping created.
                $event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
                    array(
                        'context' => \context_module::instance($data->coursemodule),
                        'courseid' => $data->course,
                        'relateduserid' => null,
                        'other' => array(
                            'lookup_name' => '\'' . $lookup_name . '\''

                        )
                    )
                );
                $event->trigger();
            }
        }

    }

    private function create_new_mapping($data) {
        global $USER;
        $newassessmentmappingdata = new stdClass();
        $newassessmentmappingdata->modifierid = $USER->id;
        $newassessmentmappingdata->coursemodule = $data->coursemodule;
        $newassessmentmappingdata->activitytype = $data->modulename;
        $newassessmentmappingdata->samisassessmentenddate = $data->bath_grade_transfer_time_start;
        $newassessmentmappingdata->assessmentlookupid = $data->bath_grade_transfer_samis_lookup_id;
        // SET.
        $this->assessmentmapping->set_data($newassessmentmappingdata);
        // SAVE.
        try {
            $this->assessmentmapping->save();
        } catch (\Exception $e) {

        }


    }

    private function update_assessment_mapping($formdata, $currentmapping) {
        global $USER;
        // UPDATE.
        $newassessmentmappingdata = new stdClass();
        $newassessmentmappingdata->id = $currentmapping->id;
        $newassessmentmappingdata->modifierid = $USER->id;
        $newassessmentmappingdata->coursemodule = $formdata->coursemodule;
        $newassessmentmappingdata->activitytype = $formdata->modulename;
        $newassessmentmappingdata->expired = $currentmapping->get_expired();
        if (isset($formdata->bath_grade_transfer_time_start)) {
            $newassessmentmappingdata->samisassessmentenddate = $formdata->bath_grade_transfer_time_start;
        }
        $newassessmentmappingdata->assessmentlookupid = $formdata->bath_grade_transfer_samis_lookup_id;

        // SET.
        $this->assessmentmapping = new local_bath_grades_transfer_assessment_mapping();
        $this->assessmentmapping->set_locked($currentmapping->get_locked());
        // Update existing mapping.
        $this->assessmentmapping->set_data($newassessmentmappingdata);
        $this->assessmentmapping->update();
    }

    /**
     * Return SAMIS attributes for a Moodle Coursse from mdl_sits_mapping table
     * @param $moodlecourseid
     * @return local_bath_grades_transfer_samis_attributes
     */
    private function get_samis_mapping_attributes($moodlecourseid) {
        $samisattributes = array();
        global $DB;
        if (isset($moodlecourseid)) {
            // Check if course exists.
            if ($DB->record_exists('course', ['id' => $moodlecourseid])) {
                // Check if mapping exists ( should be default only).
                if ($this->samis_mapping_exists($moodlecourseid)) {
                    // Fetch the mapping for current year.
                    $records = $DB->get_records('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1, 'acyear' => $this->currentacademicyear]);
                    if ($records) {
                        foreach ($records as $record) {
                            // Return Samis attributes object.
                            $samisattributes[] = new local_bath_grades_transfer_samis_attributes(
                                $record->sits_code,
                                $record->acyear,
                                $record->period_code,
                                'A');
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
        $date_array = explode('-', $this->date->format('m-Y'));
        if (intval($date_array[0]) > 7) { //TODO change academic year end to 7 (end of July)
            $this->currentacademicyear = strval(intval($date_array[1])) . '/' . substr(strval(intval($date_array[1]) + 1), -1);
            $this->currentacademicyear_start = new DateTime($date_array[1] . '-07-31 00:00:00');
            $this->academicyear_end = new DateTime($date_array[1] + 1 . '-07-31 00:00:00');
        } else {
            $this->currentacademicyear = strval(intval($date_array[1]) - 1) . '/' . substr(strval(intval($date_array[1])), -1);
            $this->currentacademicyear_start = new DateTime($date_array[1] - 1 . '-07-31 00:00:00');
            $this->currentacademicyear_end = new DateTime($date_array[1] . '-07-31 00:00:00');
        }
    }

    /**
     * @param $moodlecourseid
     * @return bool
     */
    public function samis_mapping_exists($moodlecourseid) {
        global $DB;
        return $DB->record_exists('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1, 'acyear' => $this->currentacademicyear]);
        //return $DB->record_exists('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
    }

    /**
     * @param $form
     * @param $repeated
     */
    protected function add_more_mappings($form, $repeated) {

        $form->repeat_elements($repeated, 0, [],
            'numhints', 'addhint', 1, 'Add another Mapping', true);
    }

    /**
     * @return string
     */
    protected function add_more_mappings_string() {
        return get_string('addmoreanswerblanks', 'qtype_shortanswer');
    }

    /**
     * Test Connection SAMIS API
     * @param bool $testing
     */
    public function test_samis_connection($testing = false) {
        //Contact the API client
        $this->api_client->authenticate();
        return;
    }
}