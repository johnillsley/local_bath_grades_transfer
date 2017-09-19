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
//TODO -- Show unlock button to teacher once the mappings are locked
//TODO - check for assessment title changes in housekeep()
//TODO -- MAB is now obsolete ( how do we know ?) - Ask Martin
// TODO -- check for unenrolled students in SAMIS ( Ask Martin ).

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
const GRADE_STRUCTURE_EMPTY = 7;
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
        $this->currentacademicyear = '2016/7';
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

        /////////////////// FETCH (ANY) NEW REMOTE ASSESSMENTS AND DO HOUSEKEEPING. ///////////////////

        try {
            // TODO Do we need to query the samis API on every refresh ?
            // TODO Think about when a lookup comes back ( un-expires?)

            $this->sync_remote_assessments($COURSE->id);
            $mform->addElement('html', "<p class=\"alert-info alert alert-dismissable \">
<a href=\"#\" class=\"close\" data-dismiss=\"alert\" aria-label=\"close\">&times;</a>
 Fetched any new assessments from SAMIS</p>");
        } catch (Exception $e) {
            $mform->addElement('html', "<p class=\"alert-danger alert\">" . $e->getMessage() . "</p>");
            // Show error to the user but continue with the rest of the page.
        }

        ////// BUILD CONTROLS /////////////
        // Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {

            // GET SAMIS MAPPING ATTRIBUTES.
            $samisattributes = $this->get_samis_mapping_attributes($COURSE->id);
            // Get all the records associated with the samis mapping attributes fom Moodle table.
            $lookuprecords = \local_bath_grades_transfer_assessment_lookup::get_by_samis_details($samisattributes);
            // First housekeep them.
            //TODO Think if you need to this later ??
            /*if (!empty($lookuprecords)) {
                foreach ($lookuprecords as $lookup_record) {
                    //housekeep
                    $this->housekeep_lookup($lookup_record);
                }
            }*/

            ///////////////// GET MAPPINGS ( LOCALLY ) //////
            $this->show_transfer_controls($lookuprecords, $cmid, $mform);
        } else {
            // No samis mapping defined for this course..
            $mform->addElement('html', "<span class=\"alert alert-warning\">" . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</span>");
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
            $samisassessmentenddate = userdate(
                $assessmentmapping->samisassessmentenddate == null ? 'Not Set' : $assessmentmapping->samisassessmentenddate);
            $locked = $assessmentmapping->is_locked();
            if ($locked) {
                // TODO only admins can unlock mappings for now.
                $mform->addElement('checkbox', 'unlock_assessment', '', get_string('unlock', 'local_bath_grades_transfer'));
                $mform->addElement('html',
                    "<div id = 'unlock-msg' style='display: none;'><p class=\"alert-warning alert\">" .
                    get_string('unlock_warning', 'local_bath_grades_transfer') . "</p></div>");
                $mform->addElement('html', "<p class=\"alert-warning alert\">" .
                    get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</p>");
                $dropdownattributes['disabled'] = 'disabled';
                $select = $mform->addElement('select',
                    'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $select->addOption("None", 0, $dropdownattributes);
                foreach ($lookuprecords as $lrecord) {
                    if ($lrecord->id == $assessmentmapping->assessmentlookupid) {
                        //Something is mapped
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
                $mform->addElement('static', 'bath_grade_transfer_time_start_locked', 'Transfer grades from',
                    $samisassessmentenddate);
            } else {
                // MAPPING IS NOT LOCKED.
                $select = $mform->addElement('select',
                    'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $mform->disabledIf('bath_grade_transfer_samis_lookup_id', 'grade[modgrade_point]', 'neq', 100);

                // ADD BLANK OPTION.
                $select->addOption("None", 0, $dropdownattributes);

                foreach ($lookuprecords as $lrecord) {
                    if ($lrecord->is_expired()) {
                        echo "  AND ITS EXPIRED !!";
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
                        $mappingbylookup->coursemodule . ' AND TYPE : ' . $mappingbylookup->coursemodule], $select);
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
     * @return mixed
     */
    public function select_option_format($title, $value, $attributes, &$select, $cmid = null) {
        // Check that the record_id is mapped to an assessment mapping.
        echo "CMID: " . $cmid . "\n\n";
        echo "VALUE: " . $value . "\n\n";
        if (\local_bath_grades_transfer_assessment_mapping::exists_by_lookup_id($value)) {
            // Fetch the mapping.
            $assessmentmappings = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($value);
            var_dump($assessmentmappings);
            foreach ($assessmentmappings as $assessmentmapping) {
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

    /** Fetches remote assessments from SAMIS
     * @param $moodlecourseid
     * @return array
     */
    /*
    protected function fetch_remote_assessments($moodlecourseid) {
        $remoteassessments_ids = array();
        $samisattributes = $this->get_samis_mapping_attributes($moodlecourseid);
        //$samisattributes = new \local_bath_grades_transfer_samis_attributes();
        //$samisattributes = \local_bath_grades_transfer_samis_attributes::set($moodlecourseid);
        if (!empty($samisattributes)) {
            try {
                $remote_assessment_data = $this->samis_data->get_remote_assessment_details_rest($samisattributes);
                //With the data,create a new lookup object
                //var_dump($remote_assessment_data);
                foreach ($remote_assessment_data as $mapcode => $arrayassessments) {
                    foreach ($arrayassessments as $key => $arrayassessment) {
                        $assessment_lookup = new local_bath_grades_transfer_assessment_lookup();
                        $assessment_lookup->set_attributes($samisattributes);
                        //if lookup exists, housekeep
                        // var_dump($assessment_lookup);
                        if ($assessment_lookup->lookup_exists($arrayassessment['mapcode'], $arrayassessment['mabseq']) == false) {
                            echo "adding new lookup as it doesnt exist in MOODLE ";
                            //die();
                            $assessment_lookup->mapcode = $arrayassessment['mapcode']; //also known as assess_pattern
                            $assessment_lookup->mabseq = $arrayassessment['mabseq']; //also known as assess_item
                            $assessment_lookup->ast_code = $arrayassessment['ast_code'];
                            $assessment_lookup->mabperc = $arrayassessment['mabperc'];
                            $assessment_lookup->mab_name = $arrayassessment['mab_name'];
                            $assessment_lookup->set_attributes($samisattributes);
                            //var_dump($assessment_lookup);
                            //die();
                            $assessment_lookup->add();
                        }
                    }
                }
                return $remoteassessments_ids;
            } catch (Exception $e) {
                echo "Throwing Exception #4";
                //var_dump($e->getMessage());
                throw new Exception($e->getMessage());
            }
        }
    }
    */
    public function sync_remote_assessments($moodlecourseid = null) {
        global $DB;

        if (is_null($moodlecourseid)) {
            $samisattributeslist = local_bath_grades_transfer_samis_attributes::attributes_list($this->currentacademicyear);
        } else {
            $samisattributeslist = array($this->get_samis_mapping_attributes($moodlecourseid));
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
                    $remoteassessments = array_map("self::lookup_transform", $remotedata);
                    $localassessments = array_map("self::lookup_transform", $localdata);
                    // Expire obsolete lookups.
                    $update = array();
                    $update['expired'] = time();
                    $expirelookups = array_diff($localassessments, $remoteassessments);
                    foreach ($expirelookups as $k => $v) {
                        $update['id'] = $k;
                        $DB->update_record('local_bath_grades_lookup', $update);
                    }

                    // Add new lookups.
                    $addlookups = array_diff($remoteassessments, $localassessments);
                    foreach ($addlookups as $k => $addlookup) {
                        $lookup = array_merge($remotedata[$k], (array)$samisattributes);
                        $lookup["samisassessmentid"] = $lookup["mapcode"] . '_' . $lookup["mabseq"];
                        $lookup["timecreated"] = time();
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
     * @param $gradetransferid
     * @param $users
     */
    public function do_transfer($grades, $web = false) {
        global $DB;
        $status = null;
        if (!empty($grades)) {
            var_dump($grades);
            die();
            foreach ($grades as $sprcode => $arrayassessment) {
                foreach ($arrayassessment as $key => $obj) {
                    if ($key == 'assessment') {
                        $objgrade = $obj;
                        try {
                            if ($this->samis_data->set_export_grade($objgrade)) {
                                $this->local_grades_transfer_log->outcomeid = TRANSFER_SUCCESS;
                                $this->local_grades_transfer_log->gradetransferred = $objgrade->mark;
                                $this->local_grades_transfer_log->timetransferred = time();
                                $this->local_grades_transfer_log->save();

                                if ($web) {
                                    // Display result to the user.
                                    $status = new \gradereport_transfer\output\transfer_status(
                                        $objgrade->userid,
                                        'success',
                                        $objgrade->mark);
                                }
                            }

                        } catch (\Exception $e) {
                            // Log failure.
                            echo "logging failure";
                            $this->local_grades_transfer_log->outcomeid = TRANSFER_FAILURE;
                            // Get error id.
                            $this->local_grades_transfer_error->error_message = $e->getMessage();
                            $this->local_grades_transfer_error->save();
                            $this->local_grades_transfer_log->grade_transfer_error_id = $this->local_grades_transfer_error->id;

                            if ($web) {
                                // Display result to the user.
                                $status = new \gradereport_transfer\output\transfer_status(
                                    $objgrade->userid,
                                    'failure', $objgrade->mark,
                                    'SYSTEM FAILURE');
                            }
                        }
                    }

                }
            }
            return $status;
        }

    }


    /**
     * @return bool
     */
    /*
    public function local_bath_grades_transfer_scheduled_task() {

        if (!$this->is_admin_config_present()) {
            mtrace("Settings to the plugins seems to be missing. Please fix this");
            return false;
        }
        // 1. Housekeeping , get all look-ups for current year
        try {
            $this->assessment_lookup->housekeep_lookup();
        } catch (\Exception $e) {

        }
    }
    */

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
     *
     */
    public function cron() {
        // CRON RUN.
        global $DB;
        $now = new DateTime();
        $time = 1490967029;
        global $CFG;
        require($CFG->dirroot . '/enrol/sits/lib.php');
        $this->enrolsitsplugin = new \enrolsitsplugin();
        // Get me all mapping whose transfer time is null ( they've never been transferred ).
        $assessmentmappingids = \local_bath_grades_transfer_assessment_mapping::getAll(null, true);
        var_dump($assessmentmappingids);
        if (!empty($assessmentmappingids)) {
            foreach ($assessmentmappingids as $mappingid) {
                if (isset($mappingid)) {
                    // For each assessment mapping id , get the mapping object.
                    if ($assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true)) {
                        // From course module ID , get course.
                        $this->local_grades_transfer_log->coursemoduleid = $assessmentmapping->coursemodule;
                        $this->local_grades_transfer_log->gradetransfermappingid = $assessmentmapping->id;

                        $moodlecourseid = $this->get_moodle_course_id_coursemodule($assessmentmapping->coursemodule);
                        echo "\n\n +++++++++++++++++DEALING WITH Mapping ID : $assessmentmapping->id +++++++++++++++++ \n\n";
                        // If the end date is null, we leave it to the users to transfer it from the interface.
                        if (is_null($assessmentmapping->samisassessmentenddate)) {
                            debugging("Manual grade transfer enabled. Skipping : " . $assessmentmapping->id);
                            continue;
                        }
                        if (isset($assessmentmapping->lookup) && $objlookup = $assessmentmapping->lookup) {
                            // Check that the lookup exists in SAMIS.
                            $lookup = \local_bath_grades_transfer_assessment_lookup::get($objlookup->id);
                            $this->local_grades_transfer_log->assessmentlookupid = $lookup->id;
                            if (isset($moodlecourseid)) {
                                $defaultsamismapping = $this->default_samis_mapping($moodlecourseid, $lookup->attributes);
                                if (!is_null($defaultsamismapping)) {
                                    echo "\n\n +++++++ DEFAULT SAMIS MAPPING FOUND  $defaultsamismapping->id  +++++++++++++\n ";
                                    $samisusers = $this->get_samis_users($defaultsamismapping->id);
                                    echo "\n\n +++++++ SAMIS USERS: \n\n";
                                    if (!empty($samisusers)) {

                                        /**** GRADE STRUCTURE ***/
                                        $gradestructure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis(
                                            $lookup);
                                        if (empty($gradestructure)) {
                                            echo "Could not get gradr structure for $mappingid ";
                                            continue;
                                        }
                                        foreach ($samisusers as $k => $objuser) {
                                            // For a single user , get the grade.
                                            $bucsusername = $objuser->username;
                                            $userid = $objuser->userid;
                                            $usergrades[$userid] = $this->get_moodle_grade(
                                                $userid,
                                                $assessmentmapping->coursemodule
                                            );
                                            if (!empty($usergrades[$userid]->finalgrade)) {
                                                try {
                                                    $usergrades[$userid]->spr_code = $this->samis_data->get_spr_from_bucs_id_rest(
                                                        $bucsusername
                                                    );
                                                } catch (\Exception $e) {
                                                    echo "Could not get SPR CODE for $objuser->username";
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
                                            }

                                        }
                                        // Now that we have go the grade structures,
                                        // send this to a function to do all the prechecks.
                                        $gradestopass = $this->precheck_conditions($usergrades, $gradestructure);
                                        echo("FINAL GRADES TO PASS:");
                                        // DO TRANSFER.
                                        if (!empty($gradestopass)) {
                                            $this->do_transfer($gradestopass);
                                        }
                                        die();

                                    } else {
                                        mtrace("no samis users found for this course!!!");
                                    }
                                }
                            }
                        } else {
                            // Continue.
                            die("no lookup for this mapping.skipping");

                        }
                    }

                }
            }
        } else {
            mtrace("No Assessment Mappings to transfer");
        }
        die("Never leave me !!!!");
    }

    public function transfer_mapping($mappingid, $userids = array()) {
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
            //var_dump($gradestructure);
            $this->local_grades_transfer_log->assessmentlookupid = $lookup->id;
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
                            $gradestopass = $this->precheck_conditions($usergrades, $gradestructure, true);
                            var_dump($gradestopass);
                            die();
                            if (!empty($gradestopass)) {

                                if (array_key_exists('statuses', $gradestopass)) {
                                    if (!empty($gradestopass['statuses'])) {
                                        $transferstatuses = $gradestopass['statuses'];
                                    } else {
                                        $transferstatuses = $this->do_transfer($gradestopass);

                                    }
                                }

                            }
                        }
                    }
                    return $transferstatuses;
                }
            }
        } else {
            // Continue.
            mtrace("no lookup for this mapping.skipping");
        }
    }

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

    /**
     * @param $moodleusergrades
     * @param $remotegradestructure
     * @return mixed
     */
    public function precheck_conditions($moodleusergrades, $remotegradestructure, $web = false) {
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
                echo "Setting OUTCOME to 1";
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
                    $finalgradestruct[$objmoodlegrade->spr_code]['assessment'] = $passgradeobject;
                }
            } else {
                // The user is probably a manual user in MOODLE ?
                // ##################  CONDITION 3 : STUDENT NOT IN GRADE STRUCTURE.
                echo "Something else $objmoodlegrade->spr_code . skipping\n\n";
                continue;
            }
        }
        if ($web) {
            $finalgradestruct['statuses'] = $status;
        }
        echo "FINAL GRADE STRUCT";
        var_dump($finalgradestruct);
        die();
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
        global $DB, $USER, $COURSE;
        $mappingchanged = false;
        if ($data->modulename !== 'assign') {
            return true;
        }
        $formsamisassessmentlookupid = $data->bath_grade_transfer_samis_lookup_id == '0' ? null :
            $data->bath_grade_transfer_samis_lookup_id;
        // Mapping is already defined.
        if (!is_null($formsamisassessmentlookupid)) {
            // Get previous mapping.
            if ($currentassessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($data->coursemodule)) {
                $currentassessmentlookupid = $currentassessmentmapping->assessmentlookupid;
                $current_assessment_end_date = $currentassessmentmapping->samisassessmentenddate;
                if ($formsamisassessmentlookupid != $currentassessmentlookupid || $current_assessment_end_date
                    != $data->bath_grade_transfer_time_start
                ) {
                    $mappingchanged = true;
                }

                // UPDATE.
                if ($mappingchanged) {
                    // CONSTRUCT NEW DATA.
                    $newassessmentmappingdata = new stdClass();
                    $newassessmentmappingdata->modifierid = $USER->id;
                    $newassessmentmappingdata->coursemodule = $data->coursemodule;
                    $newassessmentmappingdata->activitytype = $data->modulename;
                    $newassessmentmappingdata->samisassessmentenddate = $data->bath_grade_transfer_time_start;
                    $newassessmentmappingdata->assessmentlookupid = $formsamisassessmentlookupid;
                    // SET.
                    $this->assessmentmapping = new local_bath_grades_transfer_assessment_mapping();
                    $this->assessmentmapping->set_data($newassessmentmappingdata);
                    // Insert a new mapping.
                    $this->assessmentmapping->save();
                    // Expire the old one.
                    $newassessmentmappingdata->id = $currentassessmentmapping->id;
                    $newassessmentmappingdata->expired = 1;
                    $newassessmentmappingdata->assessmentlookupid = $currentassessmentlookupid;
                    $this->assessmentmapping->set_data($newassessmentmappingdata);
                    $this->assessmentmapping->update();

                    // Get new assessment name for logging.

                    // TRIGGER.
                    //echo "Triggering update event as mapping attrs changed {I}";
                    /*$parentcontext = context_module::instance($data->coursemodule);
                    $event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
                        array('context' => $parentcontext,
                            'relateduserid' => null,
                            'other' => array('assessment_name' => 'Hittesh Ahuja')
                        )
                    );
                    $event->trigger();*/
                }


            } else {
                // Mapping has never been defined, create it.
                // CONSTRUCT NEW DATA.
                $newassessmentmappingdata = new stdClass();
                $newassessmentmappingdata->modifierid = $USER->id;
                $newassessmentmappingdata->coursemodule = $data->coursemodule;
                $newassessmentmappingdata->activitytype = $data->modulename;
                $newassessmentmappingdata->bath_grade_transfer_time_start = $data->bath_grade_transfer_time_start;
                $newassessmentmappingdata->assessmentlookupid = $formsamisassessmentlookupid;
                // SET.
                // Only create a new mapping if something is selected.
                if (!is_null($formsamisassessmentlookupid) || $data->bath_grade_transfer_time_start !== 0) {
                    // SET.
                    $this->assessmentmapping->set_data($newassessmentmappingdata);

                    // SAVE.
                    $this->assessmentmapping->save();
                }


                // Get new assessment name for logging.
                // TRIGGER.
                /*$event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
                    array('context' => $parentcontext,
                        'other' =>
                            array('instanceid' => $parentcontext->instanceid,
                                'userid' => $USER->id,
                                'timefrom' => microtime(),
                                'action' => 'create',
                                'other' => array('assessment_name' => $new_assessment_name))
                    ));
                $event->trigger();*/
            }
        }

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
                    $record = $DB->get_record('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1, 'acyear' => $this->currentacademicyear]);
                    if ($record) {
                        // Return Samis attributes object.
                        $samisattributes = new local_bath_grades_transfer_samis_attributes(
                            $record->sits_code,
                            $record->acyear,
                            $record->period_code,
                            'A');
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