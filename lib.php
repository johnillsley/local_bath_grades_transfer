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
        $this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
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

    /**
     * Form API method to display under module settings
     * @param $mform
     * @param $context
     * @param string $modulename
     * @return true if no config set
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {

        global $COURSE, $CFG;
        require($CFG->dirroot . '/enrol/samisv2/lib.php');
        $this->enrol_sits_plugin = new \enrol_samisv2_plugin();
        //Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);
        $dropdown_attributes = $remote_assessments_ids = array();
        $date_time_selector_options = array('optional' => true);


        //Check that config is set.
        if (!in_array($modulename, $this->allowed_mods) || !($this->is_admin_config_present())) {
            return true;
        }
        //Render the header.
        $mform->addElement('header', 'local_bath_grades_transfer_header', 'Grades Transfer');


        /////////////////// FETCH (ANY) NEW REMOTE ASSESSMENTS AND DO HOUSEKEEPING ///////////////////

        try {
            //TODO Do we need to query the samis API on every refresh ?

            $this->fetch_remote_assessments($COURSE->id);
        } catch (Exception $e) {
            $mform->addElement('html', "<p class=\"alert-danger alert\">" . $e->getMessage() . "</p>");
            //Show error to the user but continue with the rest of the page
        }
        ////// BUILD CONTROLS /////////////
        //Only get settings if the course is mapped to a SAMIS code.
        if ($this->samis_mapping_exists($COURSE->id)) {
            ////// Show Static text
            $mform->addElement('html', "<p class=\"alert-info alert\">" . get_string('samis_mapping_warning', 'local_bath_grades_transfer') . "</p>");
            $select = $mform->addElement('select', 'bath_grade_transfer_samis_lookup_id', 'Select Assessment to Link to', [], $dropdown_attributes);
            //Add default option
            $select->addOption("None", 0);


            $samis_attributes = $this->get_samis_mapping_attributes($COURSE->id);
            //Get all the records associated with the samis mapping attributes
            $lookup_records = \local_bath_grades_transfer_assessment_lookup::get_by_samis_details($samis_attributes);
            //var_dump($lookup_records);
            ///////////////// GET MAPPINGS ( LOCALLY ) //////

            if ($assessment_mapping_record = $this->assessment_mapping->get_by_cm_id($cmid)) {
                var_dump($assessment_mapping_record);
                //TODO Only get mappings that have a lookup id, otherwise dont bother
                $this->assessment_mapping->set_data($assessment_mapping_record);
                if (!is_null($assessment_mapping_record->assessment_lookup_id)) {
                    //See that the lookup record still exists
                    if (\local_bath_grades_transfer_assessment_lookup::lookup_exists_by_id($this->assessment_mapping->assessment_lookup_id)) {
                        //Get the assessment name to be shown on the dropdown
                        $assessment_lookup = \local_bath_grades_transfer_assessment_lookup::get($this->assessment_mapping->assessment_lookup_id);
                        $assessment_name = $assessment_lookup->get_assessment_name();
                    }
                    //$this->assessment_mapping->set_locked(true);
                    if ($this->assessment_mapping->is_locked()) {
                        ////// STATIC MESSAGE /////////////
                        $mform->addElement('html', "<p class=\"alert-warning alert\">" . get_string('settings_locked', 'local_bath_grades_transfer') . "</p>");
                        //Change dropdown attributes to disabled
                        $dropdown_attributes['disabled'] = 'disabled';
                        ///// STATIC TRANSFER TIME TEXT /////
                        $samis_assessment_end_date = userdate($this->assessment_mapping->samis_assessment_end_date == NULL ? 'Not Set' : $this->assessment_mapping->samis_assessment_end_date);
                        $mform->addElement('static', 'bath_grade_transfer_time_start_locked', 'Transfer grades from',
                            $samis_assessment_end_date);
                    } else {
                        //Mapping is not locked
                        if (!empty($lookup_records)) {
                            foreach ($lookup_records as $lrecord) {
                                ////// 2.1 MAPPED OPTIONS /////////////
                                if ($lrecord->is_expired()) {
                                    continue;
                                }
                                if ($lrecord->id == $this->assessment_mapping->assessment_lookup_id) {
                                    //This lookup is currently mapped to this course module
                                    $this->select_option_format($assessment_name . " ( Wt: " . $lrecord->mab_perc . "% )", $lrecord->id, [], $select);
                                    $select->setSelected($lrecord->id);
                                } else {
                                    ////// 2.2 NON-MAPPED OPTIONS /////////////
                                    $this->display_select_options($lrecord, $select);
                                }
                            }
                        }


                    }

                } else {
                    //Mapping entry exits but lookup ID is null, possibly someone reset it !
                    foreach ($lookup_records as $lrecord) {
                        $this->display_select_options($lrecord, $select);
                    }
                }
                ////// DATE TIME CONTROL /////////////
                $this->transfer_date_control($mform, $this->assessment_mapping->samis_assessment_end_date, $date_time_selector_options);
            } else {
                //NO ASSESSMENT MAPPINGS HAVE BEEN DEFINED YET
                ////// DROPDOWN CONTROL /////////////
                if (!empty($lookup_records)) {
                    foreach ($lookup_records as $lrecord) {
                        $this->display_select_options($lrecord, $select);
                    }
                }

                $this->transfer_date_control($mform, null, $date_time_selector_options);
            }

        } else {
            // no samis mapping defined for this course.
            $mform->addElement('html', "<span class=\"alert alert-warning\">" . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</span>");
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
    public function select_option_format($title, $value, $attributes, &$select) {
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
                        if ($lookupid = $assessment_lookup->lookup_exists($arrayAssessment['MAP_CODE'], $arrayAssessment['MAB_SEQ'])) {
                            echo "lookup exists\n";
                            echo "housekeeping IT ";
                            //Get lookup object and housekeep it
                            $objLookup = \local_bath_grades_transfer_assessment_lookup::get($lookupid);
                            if ($objLookup) {
                                $objLookup->housekeep();
                            }
                        } else {
                            //else ,add new lookup
                            echo "adding new lookup";
                            //die();
                            $assessment_lookup->map_code = $arrayAssessment['MAP_CODE'];
                            $assessment_lookup->mab_seq = $arrayAssessment['MAB_SEQ'];
                            $assessment_lookup->ast_code = $arrayAssessment['AST_CODE'];
                            $assessment_lookup->mab_perc = $arrayAssessment['MAB_PERC'];
                            $assessment_lookup->mab_name = $arrayAssessment['MAB_NAME'];
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
        //1.1 Check for lookups that have been expired in SAMIS

        //2. Transfer grades

        // Expired ?
        //Set expired in lookup
        //Get related mapping
        //Send email to user for modifier id
        // Name change ?
        //Change name ; end //
        //

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
                            $lookup->set_expired(true);
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
     *
     */
    public function cron() {
        //CRON RUN
        global $DB;
        $now = new DateTime();
        var_dump($now);
        $time = 1490967029;
        global $CFG;
        require($CFG->dirroot . '/enrol/samisv2/lib.php');
        $this->enrol_sits_plugin = new \enrol_samisv2_plugin();
        require($CFG->dirroot . '/mod/assign/locallib.php');
        //Get me all mapping whose transfer time is null ( they've never been transferred )
        $assessment_mapping_ids = $this->assessment_mapping->getAll(null, true);
        var_dump($assessment_mapping_ids);


        foreach ($assessment_mapping_ids as $mapping_id) {

            if (isset($mapping_id)) {
                //For each assessment mapping id , get the mapping object
                if ($assessment_mapping = \local_bath_grades_transfer_assessment_mapping::get($mapping_id, true)) {
                    echo "\n\nMapping ID : $assessment_mapping->id \n\n";
                    //$this->assessment_mapping->set_data($assessment_mapping);
                    var_dump($assessment_mapping);

                    //If the end date is null, we leave it to the users to transfer it from the interface
                    if (is_null($assessment_mapping->samis_assessment_end_date)) {
                        debugging("No END date,skipping : " . $assessment_mapping->id);
                        continue;
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
                                $lookup->set_expired(true);
                            }
                            $lookup->save();
                        } else {
                            //continue
                            if (isset($moodle_course_id)) {
                                echo $moodle_course_id;

                                $samis_mappings = $this->enrol_sits_plugin->sync->samis_mapping->get_mapping_for_course($moodle_course_id);
                                var_dump($samis_mappings);
                                if (!is_null($samis_mappings)) {
                                    foreach ($samis_mappings as $samis_mapping) {
                                        if ($samis_mapping->active = 1 and $samis_mapping->default = 1) {
                                            //Get users for that samis mapping
                                            echo "FOR MAPPING $samis_mapping->id \n ";
                                            //$samis_users = array_keys($this->get_users_samis_mapping($samis_mapping->id));
                                            $samis_users = [4285, 6229, 4556]; //TODO Change this when going live
                                            var_dump($samis_users);
                                            if (!empty($samis_users)) {
                                                foreach ($samis_users as $userid) {
                                                    //For a single user , get the grade
                                                    echo "Getting GRADES for " . $userid . " \n \n";
                                                    $usergrades[$userid] = $this->get_moodle_grade($userid, $assessment_mapping->coursemodule);
                                                }
                                                var_dump($usergrades);

                                            }
                                            /**** GRADE STRUCTURE ***/
                                            $grade_structure = \local_bath_grades_transfer_assessment_grades::get_grade_strucuture_samis($lookup);
                                            var_dump($grade_structure);
                                            //Now that we have go the grade structures, send this to a function to do all the prechecks
                                            $grades_to_pass = $this->precheck_conditions($usergrades, $grade_structure);

                                            debugging("FINAL GRADES TO PASS:");
                                            var_dump($grades_to_pass);
                                            //DO TRANSFER
                                            die();

                                        }
                                    }
                                }

                            }
                        }
                    }

                }
            }
        }
        //from object, get the lookup


        //[12345/1] => grade_structure
        //[42555/1] => grade_structure
        //For each assessment mapping
        //If mapping is valid
        //Get grade structure from LOOKUP SAMIS DETAILS
        //[12345/1] => grade_structure
        //[42555/1] => grade_structure

        //Get the users from the mapping {coursemoduleid->course->gg}


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
        var_dump($moodleusergrades);

        //TODO Change this when going LIVE -- DEV TESTING
        $moodleusergrades = array();
        $obj1 = new stdClass();
        $obj1->finalgrade = null;
        $obj1->rawgrademax = null;
        $obj1->spr_code = "159124064/1";//This should come from SAMIS

        $moodleusergrades[4285] = $obj1;
        $obj2 = new stdClass();
        $obj2->finalgrade = 50;
        $obj2->rawgrademax = 100;
        $obj2->spr_code = "169156431/1";
        $moodleusergrades[4556] = $obj2;

        $ok_to_transfer = true;
        if (empty($moodleusergrades) || empty($remote_grade_structure)) {
            $ok_to_transfer = false;
        }

        //1. Check against Moodle grades
        foreach ($moodleusergrades as $moodleuserid => $objMoodleGrade) {


            if (is_null($objMoodleGrade->finalgrade)) {
                //Not dealing with empty grades
                $this->outcome->set_outcome(0); // No grade to transfer
                $ok_to_transfer = false;
            } elseif ($objMoodleGrade->rawgrademax != MAX_GRADE) {
                //Max grade not satisfied
                echo "Setting OUTCOME to 1";
                $this->outcome->set_outcome(1);
                $ok_to_transfer = false;
            }
            //get student spr
            //$spr_code = $this->get_spr_from_bucs_id($moodleuserid);
            $spr_code = $objMoodleGrade->spr_code;
            // 2. Check against remote grade structure for that student
            if (array_key_exists($spr_code, $remote_grade_structure)) {
                // OK, Student found in the RGS
                foreach ($remote_grade_structure as $spr_key => $objStructure) {
                    //1. Check if the GRADE already exists in SAMIS
                    if (!empty($objStructure->getMark())) {
                        echo "There is already a GRADE in SAMIS for $spr_code \n \n";
                        echo "Setting OUTCOME to 3 for $spr_code \n \n ";
                        $this->outcome->set_outcome(3);
                        $ok_to_transfer = false;
                    } else {
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
        //Get previous mapping
        if ($current_assessment_mapping = $this->assessment_mapping->get_by_cm_id($data->coursemodule)) {
            $current_assessment_lookup_id = $current_assessment_mapping->assessment_lookup_id;
            $current_assessment_end_date = $current_assessment_mapping->samis_assessment_end_date;
            if ($form_samis_assessment_lookup_id != $current_assessment_lookup_id || $current_assessment_end_date != $data->bath_grade_transfer_time_start) {
                echo "Lookup ID has changed";
                $mapping_changed = true;
            }

            ///CONSTRUCT NEW DATA
            $new_assessment_mapping_data = new stdClass();
            $new_assessment_mapping_data->id = $current_assessment_mapping->id;
            $new_assessment_mapping_data->modifierid = $USER->id;
            $new_assessment_mapping_data->coursemodule = $data->coursemodule;
            $new_assessment_mapping_data->activity_type = $data->modulename;
            $new_assessment_mapping_data->samis_assessment_end_date = $data->bath_grade_transfer_time_start;
            $new_assessment_mapping_data->assessment_lookup_id = $form_samis_assessment_lookup_id;
            //SET

            $this->assessment_mapping->set_data($new_assessment_mapping_data);

            //UPDATE
            if ($mapping_changed) {
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
            var_dump($new_assessment_mapping_data);

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
                    $record = $DB->get_record('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
                    if ($record) {
                        //Return Samis attributes object
                        $samis_attributes = new local_bath_grades_transfer_samis_attributes(
                            $record->samis_code,
                            $record->academic_year,
                            $record->period_code,
                            $record->occurrence);
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
        //return $DB->record_exists('sits_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
        return $DB->record_exists('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
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