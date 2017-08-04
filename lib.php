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
//TODO - MAB is now obsolete ( how do we know ?) - Ask Martin
//TODO - check for unenrolled students in SAMIS ( Ask Martin )

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
/**
 * Class local_bath_grades_transfer
 */
class local_bath_grades_transfer
{

    /**
     * @var local_bath_grades_transfer_assessment_mapping
     */
    public $assessment_mapping;
    public $current_academic_year;
    /**
     * Moodle Course ID
     * @var
     */
    public $moodlecourseid;
    /**
     * List of Modules allowed to be used for Grades Transfer
     * @var array
     */
    public $allowed_mods = array();

    /**
     * @var
     */
    public $enrol_sits_plugin;

    /**
     * local_bath_grades_transfer constructor.
     */
    public function __construct() {
        $this->samis_data = new \local_bath_grades_transfer_external_data();
        //$this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
        $this->allowed_mods = explode(',', get_config('local_bath_grades_transfer', 'bath_grades_transfer_use'));
        $this->local_grades_transfer_log = new \local_bath_grades_transfer_log();
        $this->local_grades_transfer_error = new \local_bath_grades_transfer_error();
        $this->date = new DateTime();
        $this->assessment_mapping = new \local_bath_grades_transfer_assessment_mapping();
        $this->set_current_academic_year();
    }

    /**
     * Do not show users the Grades Transfer settings part if the plugin is not completely setup
     * @return bool true | false
     */
    public function is_admin_config_present() {
        $config = get_config('local_bath_grades_transfer'); //Get config vars from mdl_config
        if (!empty($config->samis_api_key) || !empty($config->samis_api_url) || !empty($config->samis_api_user) || !empty($config->samis_api_password)
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param null $lookup_record
     */
    /*
    public function housekeep_lookup($lookup_record = null) {
        if (isset($lookup_record)) {
            echo "Houskeeping Lookup $lookup_record->id";
            $lookup_record->housekeep();
        } else {
            //Get all lookups to housekeep from the current academic year
            $lookup_records = \local_bath_grades_transfer_assessment_lookup::get_lookup_by_academic_year(CURRENT_YEAR);
            if (!empty($lookup_records)) {
                foreach ($lookup_records as $lookup_record) {
                    //housekeep
                    $lookup_record->housekeep();
                }
            }
        }
    }
    */

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
        //require($CFG->dirroot . '/enrol/samisv2/lib.php');
        require($CFG->dirroot . '/enrol/sits/lib.php');
        //$this->enrol_sits_plugin = new \enrol_samisv2_plugin();
        //$this->enrol_sits_plugin = new \enrol_sits_plugin();

        $maxgradeexceeded = get_string('modgradeerrorbadpoint', 'grades', get_config('core', 'gradepointmax'));
        //Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);
        //$cantchangemaxgrade = get_string('modgradecantchangeratingmaxgrade', 'grades');
        $checkmaxgradechange = function ($val) {
            var_dump($val);
            if ($val < 100) {
                return false;
            }
            return true;

        };
        //$mform->addRule('grade[modgrade_point]', 'Cant Change max grade', 'callback', $checkmaxgradechange, 'server', false, false);


        //Check that config is set.
        if (!in_array($modulename, $this->allowed_mods) || !($this->is_admin_config_present())) {
            return true;
        }
        //Render the header.
        $mform->addElement('header', 'local_bath_grades_transfer_header', 'Grades Transfer');

        /////////////////// FETCH (ANY) NEW REMOTE ASSESSMENTS AND DO HOUSEKEEPING ///////////////////

        try {
            //TODO Do we need to query the samis API on every refresh ?
            //TODO Think about when a lookup comes back ( un-expires?)

            $this->sync_remote_assessments($COURSE->id);
            $mform->addElement('html', "<p class=\"alert-info alert alert-dismissable \"><a href=\"#\" class=\"close\" data-dismiss=\"alert\" aria-label=\"close\">&times;</a> Fetched any new assessments from SAMIS</p>");
        } catch (Exception $e) {
            $mform->addElement('html', "<p class=\"alert-danger alert\">" . $e->getMessage() . "</p>");
            //Show error to the user but continue with the rest of the page
        }

        ////// BUILD CONTROLS /////////////
        //Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {

            // GET SAMIS MAPPING ATTRIBUTES.
            $samis_attributes = $this->get_samis_mapping_attributes($COURSE->id);
             //Get all the records associated with the samis mapping attributes fom Moodle table
            $lookup_records = \local_bath_grades_transfer_assessment_lookup::get_by_samis_details($samis_attributes);
            //First housekeep them
            //TODO Think if you need to this later ??
            /*if (!empty($lookup_records)) {
                foreach ($lookup_records as $lookup_record) {
                    //housekeep
                    $this->housekeep_lookup($lookup_record);
                }
            }*/

            ///////////////// GET MAPPINGS ( LOCALLY ) //////
            $this->show_transfer_controls($lookup_records, $cmid, $mform);
        } else {
            // no samis mapping defined for this course.
            $mform->addElement('html', "<span class=\"alert alert-warning\">" . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</span>");
        }
    }
    /** Display transfer controls to the user
     * @param $lookup_records
     * @param $cmid
     * @param $mform
     */
    public function show_transfer_controls($lookup_records, $cmid, $mform) {
        $dropdown_attributes = array();
        $date_time_selector_options = array('optional' => true);

        if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($cmid)) {
            $samis_assessment_end_date = userdate($assessment_mapping->samis_assessment_end_date == NULL ? 'Not Set' : $assessment_mapping->samis_assessment_end_date);
            $locked = $assessment_mapping->is_locked();
            if ($locked) {
                //TODO only admins can unlock mappings for now
                $mform->addElement('checkbox', 'unlock_assessment', '', get_string('unlock', 'local_bath_grades_transfer'));
                $mform->addElement('html', "<div id = 'unlock-msg' style='display: none;'><p class=\"alert-warning alert\">" . get_string('unlock_warning', 'local_bath_grades_transfer') . "</p></div>");
                $mform->addElement('html', "<p class=\"alert-warning alert\">" . get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</p>");
                $dropdown_attributes['disabled'] = 'disabled';
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $select->addOption("None", 0, $dropdown_attributes);
                foreach ($lookup_records as $lrecord) {
                    if ($lrecord->id == $assessment_mapping->assessment_lookup_id) {
                        //Something is mapped
                        $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $dropdown_attributes, $select);
                        $select->setSelected($lrecord->id);
                        if ($lrecord->is_expired()) {
                            //LOCKED AND EXPIRED
                            $mform->addElement('html', "<p class=\"alert-danger alert\">$lrecord->mab_name exists but the lookup has now expired !!! </p>");
                        }
                    } else {
                        $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $dropdown_attributes, $select);
                    }
                }
                $mform->addElement('static', 'bath_grade_transfer_time_start_locked', 'Transfer grades from',
                    $samis_assessment_end_date);
            } else {
                //MAPPING IS NOT LOCKED
                echo "MAPPING NOT LOCKED  ";
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $mform->disabledIf('bath_grade_transfer_samis_lookup_id', 'grade[modgrade_point]', 'neq', 100);

                //ADD BLANK OPTION
                $select->addOption("None", 0, $dropdown_attributes);

                foreach ($lookup_records as $lrecord) {
                    if ($lrecord->is_expired()) {
                        echo "  AND ITS EXPIRED !!";
                        $mform->addElement('html', "<p class=\"alert-danger alert\">" . get_string('bath_grade_transfer_samis_assessment_expired', 'local_bath_grades_transfer', $lrecord) . "</p>");
                        continue;
                    }
                    $this->display_option($lrecord, $assessment_mapping, $dropdown_attributes, $select, $cmid);
                }
                /******** DATE CONTROL ******/
                $this->transfer_date_control($mform, $assessment_mapping->samis_assessment_end_date, $date_time_selector_options);
            }
        } else {
            if (!empty($lookup_records)) {
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $select->addOption("None", 0, $dropdown_attributes);
                foreach ($lookup_records as $lrecord) {
                    if ($lrecord->is_expired()) {
                        //$mform->addElement('html', "<p class=\"alert-danger alert\">$lrecord->mab_name exists but the lookup has now expired !!! </p>");
                        continue;
                    }
                    $this->display_option($lrecord, $assessment_mapping, $dropdown_attributes, $select, $cmid);
                }
                /******** DATE CONTROL ******/
                $this->transfer_date_control($mform, null, $date_time_selector_options);
            } else {
                $mform->addElement('html', "<p class=\"alert-info alert\">No lookup records were found </p>");
            }
        }
    }

    /** Get the transfer form control
     * @param $mform
     * @param $date
     * @param $date_time_selector_options
     */
    protected function transfer_date_control(&$mform, $date, $date_time_selector_options) {
        if (!is_null($date)) {
            $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from', $date_time_selector_options, []);
            $mform->setDefault('bath_grade_transfer_time_start', $date);
        } else {
            $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from', $date_time_selector_options, []);
        }
        $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
        $mform->disabledIf('bath_grade_transfer_time_start', 'bath_grade_transfer_samis_assessment_id', 'eq', 0);
    }

    private function display_option($lrecord,$assessment_mapping,$attributes,&$select,$cmid){

        if (!empty($assessment_mapping) && $lrecord->id == $assessment_mapping->assessment_lookup_id) {
            $select->setSelected($lrecord->id);
            $select->addOption($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $attributes, $select);
        } else {
            //other lookups
            $mapping_by_lookup = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($lrecord->id);
            if (!empty($mapping_by_lookup)) {
                if ($cmid != $mapping_by_lookup->coursemodule) {
                    $select->addOption($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% ) is in use", $lrecord->id, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' . $mapping_by_lookup->coursemodule . ' AND TYPE : ' . $mapping_by_lookup->coursemodule], $select);
                }
            } else {
                $select->addOption($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $attributes, $select);

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
        //Check that the record_id is mapped to an assessment mapping
        echo "CMID: " . $cmid . "\n\n";
        echo "VALUE: " . $value . "\n\n";
        if (\local_bath_grades_transfer_assessment_mapping::exists_by_lookup_id($value)) {
            //Fetch the mapping
            $assessment_mappings = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($value);
            var_dump($assessment_mappings);
            foreach ($assessment_mappings as $assessment_mapping) {
                if (!empty($assessment_mapping)) {
                    if ($cmid != $assessment_mapping->coursemodule) {
                        //It is not mapped to the current course module
                        $select->addOption($title . " is in use", $value, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' . $assessment_mapping->coursemodule . ' AND TYPE : ' . $assessment_mapping->activity_type]);
                    } else {
                        //Lookup is not mapped, show as is
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

        if ($this->assessment_mapping->exists_by_lookup_id($lrecord->id)) {
            //Get the mapping to get the course ID
            $assessment_mapping = $this->assessment_mapping->get_by_lookup_id($lrecord->id);
            if (!empty($assessment_mapping)) {
                $this->select_option_format($lrecord->mab_name . " is in use", $lrecord->id, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' . $assessment_mapping->coursemodule . ' AND TYPE : ' . $assessment_mapping->activity_type], $select);
            }

        } else {
            $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, [], $select);
            $select->setSelected($lrecord->id);
        }
    }

    /** Fetches remote assessments from SAMIS
     * @param $moodlecourseid
     * @return array
     */
    /*
    protected function fetch_remote_assessments($moodlecourseid) {
        $remote_assessments_ids = array();
        $samis_attributes = $this->get_samis_mapping_attributes($moodlecourseid);
        //$samis_attributes = new \local_bath_grades_transfer_samis_attributes();
        //$samis_attributes = \local_bath_grades_transfer_samis_attributes::set($moodlecourseid);
        if (!empty($samis_attributes)) {
            try {
                $remote_assessment_data = $this->samis_data->get_remote_assessment_details_rest($samis_attributes);
                //With the data,create a new lookup object
                //var_dump($remote_assessment_data);
                foreach ($remote_assessment_data as $map_code => $arrayAssessments) {
                    foreach ($arrayAssessments as $key => $arrayAssessment) {
                        $assessment_lookup = new local_bath_grades_transfer_assessment_lookup();
                        $assessment_lookup->set_attributes($samis_attributes);
                        //if lookup exists, housekeep
                        // var_dump($assessment_lookup);
                        if ($assessment_lookup->lookup_exists($arrayAssessment['map_code'], $arrayAssessment['mab_seq']) == false) {
                            echo "adding new lookup as it doesnt exist in MOODLE ";
                            //die();
                            $assessment_lookup->map_code = $arrayAssessment['map_code']; //also known as assess_pattern
                            $assessment_lookup->mab_seq = $arrayAssessment['mab_seq']; //also known as assess_item
                            $assessment_lookup->ast_code = $arrayAssessment['ast_code'];
                            $assessment_lookup->mab_perc = $arrayAssessment['mab_perc'];
                            $assessment_lookup->mab_name = $arrayAssessment['mab_name'];
                            $assessment_lookup->set_attributes($samis_attributes);
                            //var_dump($assessment_lookup);
                            //die();
                            $assessment_lookup->add();
                        }
                    }
                }
                return $remote_assessments_ids;
            } catch (Exception $e) {
                echo "Throwing Exception #4";
                //var_dump($e->getMessage());
                throw new Exception($e->getMessage());
            }
        }
    }
    */
    protected function sync_remote_assessments( $moodlecourseid=null ) {
        global $DB;
        //$moodlecourseid=null;

        if( is_null( $moodlecourseid ) ) {
            $samis_attributes_list = local_bath_grades_transfer_samis_attributes::attributes_list( $this->current_academic_year );
        } else {
            $samis_attributes_list = array( $this->get_samis_mapping_attributes( $moodlecourseid ) );
        }

        if (!empty($samis_attributes_list)) {
            try {
                foreach( $samis_attributes_list as $samis_attributes ) {

                    $remote_data = $this->samis_data->get_remote_assessment_details_rest($samis_attributes);
                    $remote_data = array_pop($remote_data);
                    $local_data = $this->get_local_assessment_details($samis_attributes);
                    $remote_assessments = array_map("self::lookup_transform", $remote_data);
                    $local_assessments = array_map("self::lookup_transform", $local_data);
                    
                    // Expire obsolete lookups
                    $update = array();
                    $update['expired'] = time();
                    $expire_lookups = array_diff($local_assessments, $remote_assessments);
                    foreach ($expire_lookups as $k => $v) {
                        $update['id'] = $k;
                        $DB->update_record('local_bath_grades_lookup', $update);
                    }

                    // Add new lookups
                    $add_lookups = array_diff($remote_assessments, $local_assessments);
                    foreach ($add_lookups as $k => $add_lookup) {
                        $lookup = array_merge($remote_data[$k], (array)$samis_attributes);
                        $lookup["samis_assessment_id"] = $lookup["map_code"] . '_' . $lookup["mab_seq"];
                        $lookup["timecreated"] = time();
                        $DB->insert_record('local_bath_grades_lookup', $lookup);
                    }

                    // Check for updates in lookups
                    $check_updates = array_intersect($local_assessments, $remote_assessments);
                    foreach ($check_updates as $local_key => $check_update) {
                        $remote_key = array_search($check_update, $remote_assessments);
                        if ($local_data[$local_key] != $remote_data[$remote_key]) {
                            // At least one field has changed so update
                            $local_data[$local_key] = $remote_data[$remote_key];
                            $local_data[$local_key]["id"] = $local_key;
                            $DB->update_record('local_bath_grades_lookup', $local_data[$local_key]);
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

    private static function lookup_transform( $mapping ) {
        $mapping = (array) $mapping;
        $a = array();
        $a["map_code"] = $mapping["map_code"];
        $a["mab_seq"] = $mapping["mab_seq"];
        return serialize( $a );
    }

    function get_local_assessment_details($samis_attributes) {
        global $DB;
        $conditions = array();
        $conditions['expired']          = 0;
        $conditions['samis_unit_code']  = $samis_attributes->samis_unit_code;
        $conditions['occurrence']       = $samis_attributes->occurrence;
        $conditions['academic_year']    = $samis_attributes->academic_year;
        $conditions['periodslotcode']   = $samis_attributes->periodslotcode;

        $local_assessments = $DB->get_records( 'local_bath_grades_lookup', $conditions, '', 'id, map_code, mab_seq, ast_code, mab_perc, mab_name');

        foreach( $local_assessments as $k=>$v ) {
            unset( $local_assessments[$k]->id );
        }
        return $local_assessments;
    }
    /**
     * @param $gradetransferid
     * @param $users
     */
    public function do_transfer($grades, $web = false) {
        global $DB;
        //echo "+++++++++++++ INITIATING TRANSFER ++++++++ ";
        if (!empty($grades)) {
            foreach ($grades as $spr_code => $arrayAssessment) {
                foreach($arrayAssessment as $key => $obj){
                    if($key == 'assessment'){
                        $objGrade = $obj;
                        try {
                            if ($this->samis_data->set_export_grade($objGrade)) {
                                //log success
                                //var_dump($objGrade);
                                //echo "\nlogging success\n";
                                $this->local_grades_transfer_log->outcomeid = TRANSFER_SUCCESS;
                                $this->local_grades_transfer_log->gradetransferred = $objGrade->mark;
                                $this->local_grades_transfer_log->timetransferred = time();
                                $this->local_grades_transfer_log->save();

                                if ($web) {
                                    //Display result to the user
                                    $status = new \gradereport_transfer\output\transfer_status($objGrade->userid, 'success', $objGrade->mark);
                                }
                            }

                        } catch (\Exception $e) {
                            //log failure
                            echo "logging failure";
                            $this->local_grades_transfer_log->outcomeid = TRANSFER_FAILURE;
                            //get error id
                            $this->local_grades_transfer_error->error_message = $e->getMessage();
                            $this->local_grades_transfer_error->save();
                            $this->local_grades_transfer_log->grade_transfer_error_id = $this->local_grades_transfer_error->id;

                            if ($web) {
                                //Display result to the user
                                echo "setting status 2";
                                $status = new \gradereport_transfer\output\transfer_status($objGrade->userid, 'failure', $objGrade->mark, 'SYSTEM FAILURE');
                            }
                        }
                    }

                }

                //save success / failure
                //$this->local_grades_transfer_log->save();

            }
            return $status;
        }

    }

    /**
     * Fetch grade data for a user
     * @param $user
     */
    protected function grade_data($user) {

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
     * @param $moodle_course_id Moodle Course ID
     * @return $default_mapping
     */
    private function default_samis_mapping($moodle_course_id, \local_bath_grades_transfer_samis_attributes $attributes) {
        //var_dump($attributes);
        $default_mapping = null;
        global $DB;
        $sql = "SELECT * FROM {sits_mappings} WHERE courseid = :courseid  AND active = 1 and default_map = 1
        AND acyear = :academic_year AND period_code= :periodslotcode ;";
        //$samis_mappings = $this->enrol_sits_plugin->sync->samis_mapping->get_mapping_for_course($moodle_course_id);
        $samis_mapping = $DB->get_record_sql($sql, array(
            'courseid' => $moodle_course_id,
            'academic_year' => $attributes->academic_year,
            'periodslotcode' => $attributes->periodslotcode));
        if (!is_null($samis_mapping) && $samis_mapping->active = 1 && $samis_mapping->default = 1) {
            $default_mapping = $samis_mapping;
        }
        return $default_mapping;
    }

    /**
     *
     */
    public function cron() {
        //CRON RUN
        global $DB;
        $now = new DateTime();
        $time = 1490967029;
        global $CFG;
        require($CFG->dirroot . '/enrol/sits/lib.php');
        $this->enrol_sits_plugin = new \enrol_sits_plugin();
        //Get me all mapping whose transfer time is null ( they've never been transferred )
        $assessment_mapping_ids = \local_bath_grades_transfer_assessment_mapping::getAll(null, true);
        var_dump($assessment_mapping_ids);
        if (!empty($assessment_mapping_ids)) {
            foreach ($assessment_mapping_ids as $mapping_id) {
                if (isset($mapping_id) && $mapping_id == 27) {
                    //For each assessment mapping id , get the mapping object
                    if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get($mapping_id, true)) {
                        //From course module ID , get course
                        $this->local_grades_transfer_log->coursemoduleid = $assessment_mapping->coursemodule;
                        $this->local_grades_transfer_log->gradetransfermappingid = $assessment_mapping->id;

                        $moodle_course_id = $this->get_moodle_course_id_coursemodule($assessment_mapping->coursemodule);
                        echo "\n\n +++++++++++++++++DEALING WITH Mapping ID : $assessment_mapping->id +++++++++++++++++ \n\n";
                        //$this->assessment_mapping->set_data($assessment_mapping);
                        //If the end date is null, we leave it to the users to transfer it from the interface
                        if (is_null($assessment_mapping->samis_assessment_end_date)) {
                            debugging("Manual grade transfer enabled. Skipping : " . $assessment_mapping->id);
                            continue;
                        }
                        if (isset($assessment_mapping->lookup) && $objLookup = $assessment_mapping->lookup) {
                            //Check that the lookup exists in SAMIS
                            $lookup = \local_bath_grades_transfer_assessment_lookup::get($objLookup->id);
                            //$this->housekeep_lookup($lookup);
                            $this->local_grades_transfer_log->assessment_lookup_id = $lookup->id;
                            if (isset($moodle_course_id)) {
                                $default_samis_mapping = $this->default_samis_mapping($moodle_course_id, $lookup->attributes);
                                if (!is_null($default_samis_mapping)) {
                                    echo "\n\n +++++++ DEFAULT SAMIS MAPPING FOUND  $default_samis_mapping->id  +++++++++++++\n ";
                                    $samis_users = $this->get_samis_users($default_samis_mapping->id);
                                    echo "\n\n +++++++ SAMIS USERS: \n\n";
                                    if (!empty($samis_users)) {

                                        /**** GRADE STRUCTURE ***/
                                        $grade_structure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);

                                        foreach ($samis_users as $k => $objUser) {
                                            //For a single user , get the grade
                                            //$bucs_username = 'eel24-xx-xx';
                                            $bucs_username = $objUser->username;
                                            $userid = $objUser->userid;
                                            $usergrades[$userid] = $this->get_moodle_grade($userid, $assessment_mapping->coursemodule);
                                            if (!empty($usergrades[$userid]->finalgrade)) {
                                                try {
                                                    $usergrades[$userid]->spr_code = $this->samis_data->get_spr_from_bucs_id_rest($bucs_username);
                                                } catch (\Exception $e) {
                                                    echo "Could not get SPR CODE for $objUser->username";
                                                    continue;
                                                }
                                            } else {
                                                //log it as no grade in Moodle
                                                //Not dealing with empty grades
                                                $this->local_grades_transfer_log->userid = $userid;
                                                $this->local_grades_transfer_log->outcomeid = GRADE_NOT_IN_MOODLE_COURSE;
                                                $this->local_grades_transfer_log->save();
                                                //remove them from the list
                                                unset($usergrades[$userid]);
                                            }

                                            /*$dummygradeobj = new stdClass();
                                            $dummygradeobj->finalgrade = 77;
                                            $dummygradeobj->spr_code = '1234';
                                            $dummygradeobj->rawgrademax = 100;*/
                                            /*if ($userid == 4285) {
                                                $dummygradeobj->spr_code = '169068411/1';
                                                $dummygradeobj->finalgrade = 75;
                                            } elseif ($userid == 6229) {
                                                $dummygradeobj->spr_code = '169050494/1';
                                                $dummygradeobj->finalgrade = 76;
                                            }*/

                                            //$usergrades[$userid] = $dummygradeobj; //DEV TESTING

                                        }
                                        //var_dump($usergrades);
                                        //Now that we have go the grade structures, send this to a function to do all the prechecks
                                        //var_dump($grade_structure);
                                        //die();
                                        //die("GIVE ME GRADE STR");
                                        $grades_to_pass = $this->precheck_conditions($usergrades, $grade_structure);
                                        //var_dump($grades_to_pass);
                                        echo("FINAL GRADES TO PASS:");
                                        //DO TRANSFER
                                        if (!empty($grades_to_pass)) {
                                            $this->do_transfer($grades_to_pass);
                                        }
                                        die();

                                    } else {
                                        mtrace("no samis users found for this course!!!");
                                    }
                                }
                            }
                        } else {
                            //continue
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
    public function transfer_mapping($mappingid,$userids = array()){
        //Get the mapping object for the ID
        global $DB;
        $usergrades = array();
        $assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get($mappingid, true);
        $this->local_grades_transfer_log->coursemoduleid = $assessment_mapping->coursemodule;
        $this->local_grades_transfer_log->gradetransfermappingid = $assessment_mapping->id;
        if (isset($assessment_mapping->lookup) && $objLookup = $assessment_mapping->lookup) {
            $moodle_course_id = $this->get_moodle_course_id_coursemodule($assessment_mapping->coursemodule);
            //Check that the lookup exists in SAMIS

            $lookup = \local_bath_grades_transfer_assessment_lookup::get($objLookup->id);
            $grade_structure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);
            $this->local_grades_transfer_log->assessment_lookup_id = $lookup->id;
            if (isset($moodle_course_id)) {
                $default_samis_mapping = $this->default_samis_mapping($moodle_course_id, $objLookup->attributes);
                if (!is_null($default_samis_mapping)) {
                    //echo "\n\n +++++++ DEFAULT SAMIS MAPPING FOUND  $default_samis_mapping->id  +++++++++++++\n ";
                    //Get SAMIS USERS FOR THIS MAPPING
                    $samis_users = $this->get_samis_users($default_samis_mapping->id);
                    foreach ($samis_users as $key => $objUser) {
                        $samisusers[] = $objUser->userid;
                    }
                    if (!empty($userids)) {
                        foreach ($userids as $userid) {
                            //Start the time
                            //Check that the user is part of the SAMIS users group
                            if (in_array($userid, $samisusers)) {
                                $bucs_username = $DB->get_field('user', 'username', array('id' => $userid));
                                $usergrades[$userid] = $this->get_moodle_grade($userid, $assessment_mapping->coursemodule);
                                if (!empty($usergrades[$userid]->finalgrade)) {
                                    try {
                                        $usergrades[$userid]->spr_code = $this->samis_data->get_spr_from_bucs_id_rest($bucs_username);
                                    } catch (\Exception $e) {
                                        $transfer_statuses = new \gradereport_transfer\output\transfer_status($userid, 'failure', null, "Could not get SPR CODE for $bucs_username");
                                        continue;
                                    }
                                } else {
                                    //log it as no grade in Moodle
                                    //Not dealing with empty grades
                                    $this->local_grades_transfer_log->userid = $userid;
                                    $this->local_grades_transfer_log->outcomeid = GRADE_NOT_IN_MOODLE_COURSE;
                                    $this->local_grades_transfer_log->save();
                                    //remove them from the list
                                    unset($usergrades[$userid]);
                                    $transfer_statuses = new \gradereport_transfer\output\transfer_status($userid, 'failure', null,'No grade to transfer');
                                    //$transfer_statuses[] = $status;
                                }
                                //var_dump($usergrades);

                            }

                        }
                        //$start = microtime(true);
                        if(!empty($usergrades)){
                            $grades_to_pass = $this->precheck_conditions($usergrades, $grade_structure,true);
                            if (!empty($grades_to_pass)) {
                                if(array_key_exists('statuses',$grades_to_pass)){
                                    if(!empty($grades_to_pass['statuses'])){
                                        $transfer_statuses = $grades_to_pass['statuses'];
                                    }
                                    else{
                                        $transfer_statuses = $this->do_transfer($grades_to_pass, true);

                                    }
                                }

                            }
                        }
                    }
                    return $transfer_statuses;
                }
            }
        } else {
            //continue
            mtrace("no lookup for this mapping.skipping");

        }


    }
    protected function get_samis_users($samis_mapping_id) {
        global $DB;
        $users = null;
        $sql = " SELECT ue.userid,u.username FROM {sits_mappings} AS sm
        JOIN {sits_mappings_enrols} AS me ON me.map_id = sm.id
        JOIN {user_enrolments} AS ue ON ue.id = me.u_enrol_id -- PROBLEM WITH user_enrolments BEING REMOVED!!!
        JOIN {user} AS u ON u.id = ue.userid
        WHERE sm.active = 1 AND sm.default_map = 1 AND sm.id = :sits_mapping_id";
        try {
            $users_recordset = $DB->get_recordset_sql($sql, ['sits_mapping_id' => $samis_mapping_id]);
            foreach ($users_recordset as $user_record) {
                $users[] = $user_record;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
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
        $spr_code = null;
        if (isset($moodleuserid)) {
            echo "Getting username for $moodleuserid";
            //Get username
            $username = $DB->get_field('user', 'username', ['id' => $moodleuserid]);
            //Pass username to SAMIS to get SPR code
            $spr_code = $this->samis_data->get_spr_from_bucs_id($username);
        }
        return $spr_code;
    }

    /**
     * @param $moodleusergrades
     * @param $remote_grade_structure
     * @return mixed
     */
    public function precheck_conditions($moodleusergrades, $remote_grade_structure,$web =false) {
        $ok_to_transfer = false;
        $final_grade_struct = array();
        //TODO Change this when going LIVE -- DEV TESTING
        //echo "+++++++++++ PRECHECKING CONDITIONS+++++++++++++++ \n\n";
        // ##################  EMPTY MOODLE GRADES OR EMPTY STRUCTURE
        if (empty($moodleusergrades) || empty($remote_grade_structure)) {
            echo "EMPTY MOODLE GRADES OR EMPTY STRUCTURE";
            return true;
        }

        //1. Check against Moodle grades
        foreach ($moodleusergrades as $moodleuserid => $objMoodleGrade) {
            $ok_to_transfer = true;
            $this->local_grades_transfer_log->userid = $moodleuserid;
            //echo "CHECKING CONDITIONS FOR $moodleuserid \n\n";
            // ##################  CONDITION 1 : EMPTY MOODLE GRADE// ##################  CONDITION 2 : MAX GRADE NOT OUT OF 100
            if ($objMoodleGrade->rawgrademax != MAX_GRADE) {
                //Max grade not satisfied
                echo "Setting OUTCOME to 1";
                $this->local_grades_transfer_log->outcomeid = GRADE_NOT_OUT_OF_100;
                echo "Not out of 100";
                $this->local_grades_transfer_log->timetransferred = time();
                $this->local_grades_transfer_log->save();
                if($web){
                    $status = new \gradereport_transfer\output\transfer_status($objMoodleGrade->userid, 'failure', null,'Grade not OUT of 100');
                }
                continue;
            }
            if (array_key_exists($objMoodleGrade->spr_code, $remote_grade_structure)) {
                // OK, Student found in the RGS
                //var_dump($remote_grade_structure);
                //See if that user has a grade
                $samis_grade = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getMark();
                //echo "SAMIS GRADE IS ";
                //var_dump($samis_grade);
                // ##################  CONDITION 4 : GRADE ALREADY EXISTS IN SAMIS
                if (!empty($samis_grade)) {
                    $this->local_grades_transfer_log->outcomeid = GRADE_ALREADY_EXISTS;
                    $this->local_grades_transfer_log->timetransferred = time();
                    $this->local_grades_transfer_log->save();
                    if($web){
                        $status = new \gradereport_transfer\output\transfer_status($moodleuserid, 'failure', null,'Grade Already Exists in SAMIS');
                    }

                    //echo "Grade already exists in SAMIS . skipping \n\n";
                    continue;
                }
                if ($ok_to_transfer == false) {
                    echo "UNSETTING GRADE FOR $objMoodleGrade->spr_code \n \n";
                    unset($remote_grade_structure[$objMoodleGrade->spr_code]);
                } else {
                    //ADD TO BAG
                    //echo "Adding to the bag. " . $objMoodleGrade->finalgrade;
                    $pass_grade_object = new local_bath_grades_transfer_assessment_grades();
                    $pass_grade_object->student = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getStudent();
                    $pass_grade_object->name = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getName();
                    $pass_grade_object->assess_pattern = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getAssessPattern();
                    $pass_grade_object->assess_item = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getAssessItem();
                    $pass_grade_object->attempt = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getAttempt();
                    $pass_grade_object->grade = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getGrade();
                    $pass_grade_object->setMark($objMoodleGrade->finalgrade);
                    $pass_grade_object->year = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getYear();
                    $pass_grade_object->period = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getPeriod();
                    $pass_grade_object->module = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getModule();
                    $pass_grade_object->occurrence = $remote_grade_structure[$objMoodleGrade->spr_code]['assessment']->getOccurrence();
                    $pass_grade_object->userid = $moodleuserid;
                    $final_grade_struct[$objMoodleGrade->spr_code]['assessment'] = $pass_grade_object;
                }
            } else {
                // The user is probably a manual user in MOODLE ?
                // ##################  CONDITION 3 : STUDENT NOT IN GRADE STRUCTURE
                echo "Something else $objMoodleGrade->spr_code . skipping\n\n";
                continue;
            }
        }
        if($web){
            $final_grade_struct['statuses'] = $status;
        }
        return $final_grade_struct;

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
     * @param $samis_mapping_id
     * @return array
     */
    private function get_users_samis_mapping($samis_mapping_id) {
        global $DB;
        $users = array();
        //TODO Change this to samisv1 when going live !!!!
        $sql = "SELECT DISTINCT (u.id) FROM {samis_mapping} AS sm JOIN {samis_mapping_enrolments} AS me ON me.mapping_id = $samis_mapping_id
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
        $moodle_course_id = null;
        $moodle_course_id = $DB->get_field('course_modules', 'course', ['id' => $coursemoduleid]);
        return $moodle_course_id;


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
     * @param $samis_assessment_id
     * @param $remote_assessments
     * @return bool
     */
    public function samis_assessment_id_valid($samis_assessment_id, $remote_assessments) {

        //Fetch samis attributes.
        $valid = false;
        foreach ($remote_assessments as $map_code => $arrAssessment) {
            foreach ($arrAssessment as $key => $objAssessment) {
                $remote_samis_assessment_id = $objAssessment->map_code . '_' . $objAssessment->mab_seq;
                if ($remote_samis_assessment_id == $samis_assessment_id) {
                    //Assessment ID is still valid
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

        //Get time start
        global $DB, $USER, $COURSE;
        $mapping_changed = false;
        if ($data->modulename !== 'assign') {
            return true;
        }
        $form_samis_assessment_lookup_id = $data->bath_grade_transfer_samis_lookup_id == '0' ? NULL : $data->bath_grade_transfer_samis_lookup_id;
        //Mapping is already defined
        if ($form_samis_assessment_lookup_id != 0) {
            //Check that the grade is set to be 100
            if ($data->grade < 100) {
                //display an error message ?
                echo "<p class='alert alert-danger'>Grades need to be 100 for grades transfer to work !</p>";
                return;
            }
        }
        //Get previous mapping
        if ($current_assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($data->coursemodule)) {
            $current_assessment_lookup_id = $current_assessment_mapping->assessment_lookup_id;
            $current_assessment_end_date = $current_assessment_mapping->samis_assessment_end_date;
            if ($form_samis_assessment_lookup_id != $current_assessment_lookup_id || $current_assessment_end_date != $data->bath_grade_transfer_time_start) {
                $mapping_changed = true;
            }

            //UPDATE
            if ($mapping_changed) {
                ///CONSTRUCT NEW DATA
                $new_assessment_mapping_data = new stdClass();
                $new_assessment_mapping_data->modifierid = $USER->id;
                $new_assessment_mapping_data->coursemodule = $data->coursemodule;
                $new_assessment_mapping_data->activity_type = $data->modulename;
                $new_assessment_mapping_data->samis_assessment_end_date = $data->bath_grade_transfer_time_start;
                $new_assessment_mapping_data->assessment_lookup_id = $form_samis_assessment_lookup_id;
                //SET
                $this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
                $this->assessment_mapping->set_data($new_assessment_mapping_data);
                //insert a new mapping
                $this->assessment_mapping->save();
                //expire the old one
                $new_assessment_mapping_data->id = $current_assessment_mapping->id;
                $new_assessment_mapping_data->expired = 1;
                $new_assessment_mapping_data->assessment_lookup_id = $current_assessment_lookup_id;
                $this->assessment_mapping->set_data($new_assessment_mapping_data);
                $this->assessment_mapping->update();

                //Get new assessment name for logging.
                //$new_assessment_name = $this->assessment_mapping->lookup->get_assessment_name_by_samis_assessment_id($form_samis_assessment_lookup_id);

                //TRIGGER
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
            //Mapping has never been defined, create it
            ///CONSTRUCT NEW DATA
            $new_assessment_mapping_data = new stdClass();
            $new_assessment_mapping_data->modifierid = $USER->id;
            $new_assessment_mapping_data->coursemodule = $data->coursemodule;
            $new_assessment_mapping_data->activity_type = $data->modulename;
            $new_assessment_mapping_data->bath_grade_transfer_time_start = $data->bath_grade_transfer_time_start;
            $new_assessment_mapping_data->assessment_lookup_id = $form_samis_assessment_lookup_id;
            // var_dump($new_assessment_mapping_data);
            //SET
            //Only create a new mapping if something is selected
            if (!is_null($form_samis_assessment_lookup_id) || $data->bath_grade_transfer_time_start !== 0) {
                //SET
                $this->assessment_mapping->set_data($new_assessment_mapping_data);

                //SAVE
                $this->assessment_mapping->save();
            }


            //Get new assessment name for logging.
            /*$new_assessment_name = $this->assessment_mapping->lookup->get_assessment_name_by_samis_assessment_id($form_samis_assessment_lookup_id);
            //TRIGGER
            $event = \local_bath_grades_transfer\event\assessment_mapping_saved::create(
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

    /**
     * Return SAMIS attributes for a Moodle Coursse from mdl_sits_mapping table
     * @param $moodlecourseid
     * @return local_bath_grades_transfer_samis_attributes
     */
    private function get_samis_mapping_attributes($moodlecourseid) {
        $samis_attributes = array();
        global $DB;
        if (isset($moodlecourseid)) {
            //Check if course exists
            if ($DB->record_exists('course', ['id' => $moodlecourseid])) {
                //Check if mapping exists ( should be default only)
                if ($this->samis_mapping_exists($moodlecourseid)) {
                    //Fetch the mapping for current year
                    $record = $DB->get_record('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1,'acyear'=>$this->current_academic_year]);
                    if ($record) {
                        //Return Samis attributes object
                        $samis_attributes = new local_bath_grades_transfer_samis_attributes(
                            $record->sits_code,
                            $record->acyear,
                            $record->period_code,
                            'A');
                    }
                }
            }
        }
        return $samis_attributes;
    }

    /**
     * sets current academic year in the format 'yyyy/+1' style, such as 2010/1, 2011/2 and the lke
     */
    protected function set_current_academic_year() {
        $date_array = explode('-', $this->date->format('m-Y'));
        if(intval($date_array[0]) > 9){ //TODO change academic year end to 7 (end of July)
            $this->current_academic_year = strval(intval($date_array[1])) . '/' . substr(strval(intval($date_array[1]) + 1), -1);
            $this->current_academic_year_start = new DateTime($date_array[1] . '-07-31 00:00:00');
            $this->academic_year_end = new DateTime($date_array[1] + 1 . '-07-31 00:00:00');
        } else {
            $this->current_academic_year = strval(intval($date_array[1]) - 1) . '/' . substr(strval(intval($date_array[1])), -1);
            $this->current_academic_year_start = new DateTime($date_array[1] - 1 . '-07-31 00:00:00');
            $this->current_academic_year_end = new DateTime($date_array[1] . '-07-31 00:00:00');
        }
    }

    /**
     * @param $moodlecourseid
     * @return bool
     */
    public function samis_mapping_exists($moodlecourseid) {
        global $DB;
        return $DB->record_exists('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1]);
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