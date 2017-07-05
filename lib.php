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
//TODO - MAP is now obsolete ( how do we know ?) - Ask Martin
//TODO - check for unenrolled students in SAMIS ( Ask Martin )

/**
 * Class local_bath_grades_transfer
 */
const MAX_GRADE = 100;
/**
 * Class local_bath_grades_transfer
 */
class local_bath_grades_transfer
{

    /**
     * @var local_bath_grades_transfer_assessment_mapping
     */
    public $assessment_mapping;
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
        $this->outcome = new  \local_bath_grades_transfer_outcome();
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
    public function housekeep_lookup($lookup_record = null){
        if(isset($lookup_record)){
            echo "Houskeeping Lookup $lookup_record->id";
            $lookup_record->housekeep();
        }
        else{
            //Get all lookups to housekeep from the current academic year
            $lookup_records = \local_bath_grades_transfer_assessment_lookup::get_lookup_by_academic_year('2016-7');
            if (!empty($lookup_records)) {
                foreach ($lookup_records as $lookup_record) {
                    //housekeep
                    $lookup_record->housekeep();
                }
            }
        }
    }
    /**
     * Form API method to display under module settings
     * @param $mform
     * @param $context
     * @param string $modulename
     * @return true if no config set
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {

        global $COURSE, $CFG;
        //require($CFG->dirroot . '/enrol/samisv2/lib.php');
        require($CFG->dirroot . '/enrol/sits/lib.php');
        //$this->enrol_sits_plugin = new \enrol_samisv2_plugin();
        $this->enrol_sits_plugin = new \enrol_sits_plugin();

        $maxgradeexceeded = get_string('modgradeerrorbadpoint', 'grades', get_config('core', 'gradepointmax'));
        //Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);
        $dropdown_attributes = $remote_assessments_ids = array();
        $date_time_selector_options = array('optional' => true);
        //$cantchangemaxgrade = get_string('modgradecantchangeratingmaxgrade', 'grades');
        $checkmaxgradechange = function ($val) {
            echo "coming into my callback";
            var_dump($val);
            if ($val < 100) {
                echo "ret false";
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

            $this->fetch_remote_assessments($COURSE->id);
        } catch (Exception $e) {
            $mform->addElement('html', "<p class=\"alert-danger alert\">" . $e->getMessage() . "</p>");
            //Show error to the user but continue with the rest of the page
        }
        ////// BUILD CONTROLS /////////////
        //Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {
            ////// Show Static text
            //$mform->addElement('html', "<p class=\"alert-info alert\">" . get_string('samis_mapping_warning', 'local_bath_grades_transfer') . "</p>");

            // GET SAMIS MAPPING ATTRIBUTES.
            $samis_attributes = $this->get_samis_mapping_attributes($COURSE->id);
            //var_dump($samis_attributes);
            //Get all the records associated with the samis mapping attributes fom Moodle table

            $lookup_records = \local_bath_grades_transfer_assessment_lookup::get_by_samis_details($samis_attributes);
            //First housekeep them
            if (!empty($lookup_records)) {
                foreach ($lookup_records as $lookup_record) {
                    //housekeep
                    $this->housekeep_lookup($lookup_record);
                }
            }
            //var_dump($lookup_records);
            ///////////////// GET MAPPINGS ( LOCALLY ) //////
            $this->show_transfer_controls($lookup_records, $cmid, $mform);
        } else {
            // no samis mapping defined for this course.
            $mform->addElement('html', "<span class=\"alert alert-warning\">" . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</span>");
        }
    }

    public function show_transfer_controls($lookup_records, $cmid, $mform) {
        $dropdown_attributes = array();
        $date_time_selector_options = array('optional' => true);

        if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($cmid)) {
            $samis_assessment_end_date = userdate($assessment_mapping->samis_assessment_end_date == NULL ? 'Not Set' : $assessment_mapping->samis_assessment_end_date);
            $locked = $assessment_mapping->is_locked();
            if ($locked) {
                $mform->addElement('html', "<p class=\"alert-warning alert\">" . get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</p>");
                $dropdown_attributes['disabled'] = 'disabled';
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                //$select->disabledif();

                $this->select_option_format("None", 0, $dropdown_attributes, $select);
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
                        /*if ($lrecord->is_expired()) {
                            //LOCKED AND EXPIRED
                            $mform->addElement('html', "<p class=\"alert-danger alert\">$lrecord->mab_name exists but the lookup has now expired !!! </p>");
                            continue;
                        }*/
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

                $select->addOption("None", 0, $dropdown_attributes);
                //var_dump($lookup_records);
                foreach ($lookup_records as $lrecord) {
                    if ($lrecord->id == $assessment_mapping->assessment_lookup_id) {
                        //Something is mapped
                        echo "and CURRENT MAPPING EXISTS  ";
                        $select->setSelected($lrecord->id);
                        //$lrecord->set_expired(true);
                        if ($lrecord->is_expired()) {
                            echo "  AND ITS EXPIRED !!";
                            $mform->addElement('html', "<p class=\"alert-danger alert\">" . get_string('bath_grade_transfer_samis_assessment_expired', 'local_bath_grades_transfer') . "</p>");
                            continue;
                        }
                        $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $dropdown_attributes, $select, $cmid);

                    } else {
                        //other non-mapped lookups
                        echo "other non-mapped lookups";
                        if ($lrecord->is_expired()) {
                            //echo "  ITS EXPIRED !!";
                            //$mform->addElement('html', "<p class=\"alert-danger alert\">$lrecord->mab_name exists but the lookup has now expired !!! </p>");
                            continue;
                        }
                        $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $dropdown_attributes, $select);
                    }
                }
                $this->transfer_date_control($mform, $assessment_mapping->samis_assessment_end_date, $date_time_selector_options);
            }
        } else {
            echo "NO ASSESSMENT MAPPING ";
            if (!empty($lookup_records)) {
                $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], []);
                $this->select_option_format("None", 0, $dropdown_attributes, $select);
                foreach ($lookup_records as $lrecord) {
                    if ($lrecord->is_expired()) {
                        //echo "  ITS EXPIRED !!";
                        //$mform->addElement('html', "<p class=\"alert-danger alert\">$lrecord->mab_name exists but the lookup has now expired !!! </p>");
                        continue;
                    }
                    $this->select_option_format($lrecord->mab_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, $dropdown_attributes, $select);
                }
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

    /**
     * @param $title
     * @param $value
     * @param $attributes
     * @param $select
     * @return mixed
     */
    public function select_option_format($title, $value, $attributes, &$select, $cmid = null) {
        //Check that the record_id is mapped to an assessment mapping
        if (\local_bath_grades_transfer_assessment_mapping::exists_by_lookup_id($value)) {
            //Fetch the mapping
            $assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get_by_lookup_id($value);
            if (!empty($assessment_mapping)) {
                if ($cmid != $assessment_mapping->coursemodule) {
                    //It is not mapped to the current course module
                    return $select->addOption($title . " is in use", $value, ['disabled' => 'disabled', 'title' => 'ACTIVITY ID :' . $assessment_mapping->coursemodule . ' AND TYPE : ' . $assessment_mapping->activity_type]);
                }
            }
        }
        //Lookup is not mapped, show as is
        return $select->addOption($title, $value, $attributes);
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
                            $assessment_lookup->map_code = $arrayAssessment['map_code'];
                            $assessment_lookup->mab_seq = $arrayAssessment['mab_seq'];
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

    /**
     * @param $gradetransferid
     * @param $users
     */
    public function do_transfer($grades) {
        global $DB;
        //This is then passed on to grade.transfer.class.php

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

    /** For a given mapping with users, transfer the grades to SAMIS
     * @param $mapping_id
     * @param array $users
     * @return bool true | false
     */
    public function do_transfer_mapping($mapping_id, $users = array()) {

        //Check that config is set.
        if (!$this->is_admin_config_present()) {
            debugging("Settings to the plugins seems to be missing. Please fix this");
            return false;
        }


        if (isset($mapping_id)) {
            //From mapping ID , get mapping details and the rest.
            echo "\n\n Processing MAPPING  ID : $mapping_id \n\n";
            if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get($mapping_id, true)) {
                echo "\n\nMapping Object found for ID : $assessment_mapping->id \n\n";
                //$this->assessment_mapping->set_data($assessment_mapping);
                var_dump($assessment_mapping);

                //If the end date is null, we leave it to the users to transfer it from the interface
                if (($assessment_mapping->samis_assessment_end_date)) {
                    //Log it
                    debugging("SORRY , there is already a date set for this assessment in the assignment settings: " . $assessment_mapping->id);
                    return false;

                }
                //From course module ID , get course
                $moodle_course_id = $this->get_moodle_course_id_coursemodule($assessment_mapping->coursemodule);

                if (isset($assessment_mapping->lookup) && $objLookup = $assessment_mapping->lookup) {
                    //Check that the lookup exists in SAMIS
                    $lookup = \local_bath_grades_transfer_assessment_lookup::get($objLookup->id);
                    if ($lookup->assessment_exists_in_samis() == false) {
                        //Set it to be expired
                        debugging("Setting it to be expired");
                        if (!$lookup->is_expired()) {
                            $lookup->set_expired(time());
                        }
                        $lookup->update();
                    } else {
                        //continue
                        if (isset($moodle_course_id)) {

                            $samis_mappings = $this->enrol_sits_plugin->sync->samis_mapping->get_mapping_for_course($moodle_course_id);
                            echo "\n \n SAMIS MAPPINGS FOR THIS MOODLE COURSE ";
                            var_dump($samis_mappings);
                            if (!is_null($samis_mappings)) {
                                foreach ($samis_mappings as $samis_mapping) {
                                    if ($samis_mapping->active = 1 and $samis_mapping->default = 1) {
                                        //Get users for that samis mapping
                                        echo "FOR MAPPING $samis_mapping->id \n ";
                                        //$samis_users = array_keys($this->get_users_samis_mapping($samis_mapping->id));
                                        //GET SAMIS users
                                        echo "!!!!!!!! SETTING DUMMY SAMIS TEST USERS .......!!!!!!! ";
                                        $samis_users = [4285, 6229, 4556]; //TODO Change this when going live
                                        var_dump($samis_users);
                                        if (!empty($users) && !empty($samis_users)) {
                                            foreach ($users as $userid) {
                                                //ensure that the user is in the samis list.
                                                //Only if the given user is in the SAMIS users list, add it to be transferred
                                                //If the given user is not in the SAMIS users list, return false with a warning
                                                if (in_array($userid, $samis_users)) {
                                                    //Continue getting grades for them
                                                    echo "Getting GRADES for " . $userid . " \n \n";
                                                    $usergrades[$userid] = $this->get_moodle_grade($userid, $assessment_mapping->coursemodule);
                                                }

                                            }
                                        }
                                        var_dump($usergrades);
                                        die();
                                        /**** GRADE STRUCTURE ***/
                                        $grade_structure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);
                                        var_dump($grade_structure);
                                        die();
                                        //Now that we have go the grade structures, send this to a function to do all the prechecks
                                        $grades_to_pass = $this->precheck_conditions($usergrades, $grade_structure);
                                        debugging("FINAL GRADES TO PASS:");
                                        var_dump($grades_to_pass);
                                        //DO TRANSFER
                                        $this->do_transfer($grades_to_pass);
                                        die();

                                    }
                                }
                            } else {
                                //TODO log this
                                debugging("NO SAMIS MAPPING(S) FOUND FOR THIS ASSESSMENT MAPPING. SORRY!");
                                return false;
                            }

                        }
                    }
                } else {
                    debugging("Mapping exists but no lookup associated with it ! BYE ");
                    return false;
                }

            } else {
                debugging("Could not find mapping object for :" . $mapping_id);
                return false;
            }
        } else {
            //Report error.
            return false;
        }

    }

    /**
     * Return default samis mapping for a Moodle course
     * @param $moodle_course_id Moodle Course ID
     * @return $default_mapping
     */
    private function default_samis_mapping($moodle_course_id) {
        $default_mapping = null;
        global $DB;
        $sql = "SELECT * FROM {sits_mappings} WHERE courseid = ?  AND active = 1 and default_map = 1";
        //$samis_mappings = $this->enrol_sits_plugin->sync->samis_mapping->get_mapping_for_course($moodle_course_id);
        $samis_mapping = $DB->get_record_sql($sql,array($moodle_course_id));
        var_dump($samis_mapping);
        if (!is_null($samis_mapping) &&  $samis_mapping->active = 1 && $samis_mapping->default = 1 ) {
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
        require($CFG->dirroot . '/mod/assign/locallib.php');
        //Get me all mapping whose transfer time is null ( they've never been transferred )
        $assessment_mapping_ids = \local_bath_grades_transfer_assessment_mapping::getAll(null, true);
        var_dump($assessment_mapping_ids);
        foreach ($assessment_mapping_ids as $mapping_id) {
            if (isset($mapping_id)) {
                //For each assessment mapping id , get the mapping object
                if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get($mapping_id, true)) {
                    //From course module ID , get course
                    $moodle_course_id = $this->get_moodle_course_id_coursemodule($assessment_mapping->coursemodule);
                    echo "\n\n +++++++++++++++++DEALING WITH Mapping ID : $assessment_mapping->id +++++++++++++++++ \n\n";
                    //$this->assessment_mapping->set_data($assessment_mapping);
                    var_dump($assessment_mapping);
                    //If the end date is null, we leave it to the users to transfer it from the interface
                    if (is_null($assessment_mapping->samis_assessment_end_date)) {
                        debugging("No END date,skipping : " . $assessment_mapping->id);
                        continue;
                    }
                    if (isset($assessment_mapping->lookup) && $objLookup = $assessment_mapping->lookup) {
                        //Check that the lookup exists in SAMIS
                        $lookup = \local_bath_grades_transfer_assessment_lookup::get($objLookup->id);
                        $this->housekeep_lookup($lookup);

                        if (isset($moodle_course_id)) {
                            $default_samis_mapping = $this->default_samis_mapping($moodle_course_id);
                            if (!is_null($default_samis_mapping)) {
                                echo "\n\n +++++++ DEFAULT SAMIS MAPPING FOUND  $default_samis_mapping->id  +++++++++++++\n ";
                                $samis_users = [4285, 6229, 4556]; //TODO Change this when going live
                                echo "\n\n +++++++ SAMIS USERS: \n\n";
                                var_dump($samis_users);
                                if (!empty($samis_users)) {
                                    foreach ($samis_users as $userid) {
                                        //For a single user , get the grade
                                        echo "Getting GRADE for " . $userid . " \n \n";
                                        $usergrades[$userid] = $this->get_moodle_grade($userid, $assessment_mapping->coursemodule);
                                    }
                                    var_dump($usergrades);
                                    /**** GRADE STRUCTURE ***/
                                    $grade_structure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);
                                    var_dump($grade_structure);
                                    //Now that we have go the grade structures, send this to a function to do all the prechecks
                                    $grades_to_pass = $this->precheck_conditions($usergrades, $grade_structure);
                                    debugging("FINAL GRADES TO PASS:");
                                    var_dump($grades_to_pass);
                                    //DO TRANSFER
                                    die();

                                } else {
                                    mtrace("no samis users found for this course!!!");
                                }
                            }
                        }
                    } else {
                        //continue

                    }
                }

            }
        }
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
    public function precheck_conditions($moodleusergrades, $remote_grade_structure) {
        $ok_to_transfer = true;
        //TODO Change this when going LIVE -- DEV TESTING
        /* $moodleusergrades = array();
         $obj1 = new stdClass();
         $obj1->finalgrade = null;
         $obj1->rawgrademax = null;
         $obj1->spr_code = "159124064/1";//This should come from SAMIS

         $moodleusergrades[4285] = $obj1;
         $obj2 = new stdClass();
         $obj2->finalgrade = 50;
         $obj2->rawgrademax = 100;
         $obj2->spr_code = "169156431/1";
         $moodleusergrades[4556] = $obj2;*/


        if (empty($moodleusergrades) || empty($remote_grade_structure)) {
            $ok_to_transfer = false;
        }

        //1. Check against Moodle grades
        foreach ($moodleusergrades as $moodleuserid => $objMoodleGrade) {
            if (is_null($objMoodleGrade->finalgrade)) {
                //Not dealing with empty grades
                $this->outcome->set_outcome(0); // No grade to transfer
                $ok_to_transfer = false;
                continue;
            } elseif ($objMoodleGrade->rawgrademax != MAX_GRADE) {
                //Max grade not satisfied
                echo "Setting OUTCOME to 1";
                $this->outcome->set_outcome(1);
                $ok_to_transfer = false;
                continue;
            }
            //get student spr
            //$spr_code = $this->get_spr_from_bucs_id($moodleuserid);
            //$spr_code = "169156431/1";
            //var_dump($objMoodleGrade);die;
            //$spr_code = $objMoodleGrade->student;
            // 2. Check against remote grade structure for that student
            if (array_key_exists("169156431/1", $remote_grade_structure)) {
                // OK, Student found in the RGS
                foreach ($remote_grade_structure as $spr_key => $objStructure) {
                    //1. Check if the GRADE already exists in SAMIS
                    if (!empty($objStructure->getMark())) {
                        echo "There is already a GRADE in SAMIS for \"169156431/1\" \n \n";
                        echo "Setting OUTCOME to 3 for \"169156431/1\" \n \n ";
                        $this->outcome->set_outcome(3);
                        $ok_to_transfer = false;
                        continue;
                    } else {
                        //Check if there is a grade in Moodle to pass

                        $ok_to_transfer = true;
                    }
                    echo "FINAL VERDICT for : " . $spr_key;
                    var_dump($ok_to_transfer);
                    //IF the final verdict is not OK , then remove them from the array
                    if (!$ok_to_transfer) {
                        unset($remote_grade_structure[$spr_key]);
                    } else {
                        //Add it to a bag of things to transfer
                        echo "Adding to the bag." . $objMoodleGrade->finalgrade;
                        $objStructure->setMark($objMoodleGrade->finalgrade);
                    }
                }
                //Log each outcome

            }
        }
        return $remote_grade_structure;

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
                //echo "Lookup ID has changed";
                $mapping_changed = true;
            }

            //UPDATE
            if ($mapping_changed) {
                ///CONSTRUCT NEW DATA
                $new_assessment_mapping_data = new stdClass();
                $new_assessment_mapping_data->id = $current_assessment_mapping->id;
                $new_assessment_mapping_data->modifierid = $USER->id;
                $new_assessment_mapping_data->coursemodule = $data->coursemodule;
                $new_assessment_mapping_data->activity_type = $data->modulename;
                $new_assessment_mapping_data->samis_assessment_end_date = $data->bath_grade_transfer_time_start;
                $new_assessment_mapping_data->assessment_lookup_id = $form_samis_assessment_lookup_id;
                //SET
                $this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
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
            $this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
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
                    //Fetch the mapping
                    $record = $DB->get_record('sits_mappings', ['courseid' => $moodlecourseid, 'default_map' => 1]);
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