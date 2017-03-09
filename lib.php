<?php
require_once 'classes/assessment_mapping.php';
require_once 'samis_assessment_form.php';

/**
 * Class local_bath_grades_transfer
 */
class local_bath_grades_transfer
{

    public $assessment_mapping;

    /**
     * local_bath_grades_transfer constructor.
     */
    public function __construct() {
        $this->assessment_mapping_data = new local_bath_grades_transfer_samis_assessment_data();
        $this->assessment_mapping = new local_bath_grades_transfer_assessment_mapping();
    }

    /**
     * Form API method to display under module settings
     * @param $mform
     * @param $context
     * @param string $modulename
     */
    public function get_form_elements_module($parent_form, $mform, $context, $modulename = "") {
        global $DB, $COURSE, $error_message;
        //Optional cmid param.
        $cmid = optional_param('update', 0, PARAM_INT);
        //Only get settings if the course is mapped to a SAMIS code.
        if ($this->mapping_exists($COURSE->id)) {


            $this->get_settings($parent_form, $mform, $COURSE->id, $cmid, $error_message);
        } else {
            $mform->addElement('html', "<span class=\"alert alert-warning\">" . get_string('bath_grade_transfer_not_samis_default_mapping', 'local_bath_grades_transfer') . "</span>");
        }
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
     * Defines the fields used in the settings
     * @return array
     */
    private function setting_fields() {
        return array('bath_grade_transfer_time_start', 'bath_grade_transfer_samis_assessment');

    }


    /**
     * Fetches settings for each course module id either
     * locally - from the moodle database table OR
     * remotely - from the SAMIS API
     * @param $mform
     * @param $moodlecourseid
     * @param null $cmid
     * @param $error_mesage
     */
    public function get_settings($parent_form, $mform, $moodlecourseid, $cmid = null, &$error_mesage) {
        global $DB;
        $local_samis_assessment_id = null;
        $data = null;
        $options = array();
        $error_message = null;
        $date_time_selector_options = array('optional' => true);
        $dropdown_attributes = array();
        if ($cmid) {
            //Look for settings locally
            //Append remote settings
            if ($this->assessment_mapping->exists_by_cm_id($cmid)) {
                $data = $DB->get_record('local_bath_grades_transfer', array('coursemoduleid' => $cmid));
                //var_dump($data);
                //Locked
                //$data->locked = 1;
                if (isset($data->locked) && $data->locked) {
                    $mform2->addElement('static', '', '', "<span class='alert alert-info'>" . get_string('bath_grade_transfer_settings_locked', 'local_bath_grades_transfer') . "</span>");
                    //$date_time_selector_options = array('optional' => false);
                    $dropdown_attributes['disabled'] = 'disabled';
                }


            } else {
                //Fetch remote settings
                $data = $this->get_samis_mapping_attributes($moodlecourseid);
            }
        } else {
            //Fetch remote settings
            $data = $this->get_samis_mapping_attributes($moodlecourseid);
        }


        //Build the form bases on the API result
        // Basic HTML Message


        if (!empty($data)) {
            $remote_assessments = $this->get_remote_settings($data->samis_unit_code,
                $data->academic_year,
                $data->periodslotcode,
                $data->occurrence);
            $params = array();
            $params['remote_assessments'] = $remote_assessments;

            $mform->addElement('html', "<span class=\"alert alert-warning\">Please ensure you have contacted SAMIS....</span>");
            $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from', [], []);
            $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
            $select = $mform->addElement('select', 'bath_grade_transfer_samis_assessment', 'Select Assessment to Link to', [], []);
            $repeated[] = $mform->createElement('editor', 'hint', get_string('hintn', 'question'),
                array('rows' => 3), []);

            $this->add_more_mappings($parent_form, $repeated);

            if (isset($data->samis_assessment_id)) {
                if (!array_key_exists($data->samis_assessment_id, $options)) {
                    debugging('NO it doesnt exist');

                    //If , for some reason the mapping is not in remote anymore
                    $error_message = 'The mapping is now redundant . Please contact the SAMIS team';
                } else {
                    //$dropdown_settings
                    debugging('Yes it does exist');
                    $default = $data->samis_assessment_id;
                }
            }
        }


        //Radio options for Assessment Mapping
        $radioarray = array();

        foreach ($options as $code => $title) {
            $radioarray[] = $mform->createElement('radio', 'bath_grade_transfer_samis_assessment_radio', '', $title, $code, array('class' => 'samis_assessment_group_item'));
        }
        // $mform->addGroup($radioarray, 'assessment_group', 'Select Assessment to Link to', [], false);
        //$mform->addHelpButton('assessment_group', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
        //$mform->setDefault('assessment_group',$default);

        //$mform->getElement('bath_grade_transfer_samis_assessment')->setSelected($default);
        if ($error_message) {
            //$mform->addElement('html', "<span class=\"alert alert-warning\">Mapping not in remote anymore</span>");
        }

        // if cmid and mapping already exists -- > show local + remote
        // if cmid and no mapping --> get remote
        // if no cmid ( and no mapping) --> get remote

    }

    /**
     *  See if transfer has been locked. Usually happens after the first grade has been transferred
     */
    public function is_transfer_locked() {

        return false;

    }

    protected function add_more_mappings($form, $repeated) {

        $form->repeat_elements($repeated, 0, [],
            'numhints', 'addhint', 1, 'Add another Mapping', true);
    }

    protected function add_more_mappings_string() {
        return get_string('addmoreanswerblanks', 'qtype_shortanswer');
    }

    private function format_assessment_group_header($objAssessment) {
        $html = '';
        $item_title = $objAssessment->mab_name;
        $samis_attributes = "" . " $objAssessment->module -  " . " $objAssessment->year  - " . " $objAssessment->period " . "";
        $html = $item_title . $samis_attributes;
        return $html;
    }

    public function get_remote_settings($samis_unit_code, $academic_year, $periodslotcode, $occurrence) {
        $settings = array();
        $remote_mappings = $this->assessment_mapping->get_remote_assesment_data(
            $samis_unit_code,
            $academic_year,
            $periodslotcode,
            $occurrence);
        foreach ($remote_mappings->assessments as $objRemoteAssessment) {
            $settings[$objRemoteAssessment->map_code][] = $objRemoteAssessment;
        }
        return $settings;

    }

    /**
     * Action to perform when module settings a saved in modedit.php form page
     * @param $data
     */
    public function save_form_elements($data) {
        //Get time start
        global $DB;
        var_dump($data);
        //Get the settings from the local_bath_grades_transfer DB
        $mod_setting_values = $this->get_settings($data->coursemodule);
        //Get the fields
        $fields = $this->setting_fields();

        //Get SAMIS course details
        $samis_mapping = $this->get_samis_mapping_attributes($data->course);
        if (!empty($samis_mapping)) {

        } else {

        }
        foreach ($fields as $key => $setting_value) {
            if (isset($data->$setting_value)) {
                $optionfield = new stdClass();
                $optionfield->coursemoduleid = $data->coursemodule;
                $optionfield->name = $setting_value;
                $optionfield->value = $data->$setting_value;

                if (isset($mod_setting_values[$setting_value])) {
                    //Update
                } else {
                    //insert
                }

            }
        }
        //Get SAMIS Assessment from API
        $databasevalues = $this->get_settings($data->coursemodule, false);
        var_dump($databasevalues);


        die();

    }

    /**
     * @param $moodlecourseid
     * @return array
     */
    public function get_samis_mapping_attributes($moodlecourseid) {
        $samis_attributes = array();
        global $DB;
        if (isset($moodlecourseid)) {
            //Check if course exists
            if ($DB->record_exists('course', ['id' => $moodlecourseid])) {
                //Check if mapping exists ( should be default only)
                if ($this->mapping_exists($moodlecourseid)) {
                    //Fetch the mapping
                    $record = $DB->get_record('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
                    if ($record) {
                        //Fetch SAMIS details only
                        $samis_attributes = new stdClass();
                        $samis_attributes->samis_unit_code = $record->samis_code;
                        $samis_attributes->academic_year = $record->academic_year;
                        $samis_attributes->periodslotcode = $record->period_code;
                        $samis_attributes->occurrence = $record->occurrence;

                    }
                } else {
                    //No samis mapping exists for the course
                    return [];
                }
            }
        }
        return $samis_attributes;
    }

    private function mapping_exists($moodlecourseid) {
        global $DB;
        return $DB->record_exists('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
    }

    /**
     * Test Connection SAMIS API
     * @param bool $testing
     */
    public function test_samis_connection($testing = false) {
        //Contact the API client
        $this->api_client->authenticate();
    }
}