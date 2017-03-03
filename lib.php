<?php
require_once('classes/api/samis_api_client.php');

/**
 * Class local_bath_grades_transfer
 */
class local_bath_grades_transfer  {
    /**
     * @var local_bath_grades_transfer_samis_api_client
     * API Client to connect to SAMIS
     */
    public $api_client;

    /**
     * local_bath_grades_transfer constructor.
     */
    public function __construct() {
        $this->api_client = new local_bath_grades_transfer_samis_api_client();
    }

    /**
     * Form API method to display under module settings
     * @param $mform
     * @param $context
     * @param string $modulename
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {
        global $DB, $COURSE;

        // Check plugin is configured.

        // Get settings
        //$settings = $this->get_settings();
        //var_dump($settings);
        $json_content = $this->api_client->autheticate();

        //Build the form bases on the API result
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from',$options);
        $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
        $assessment_details = [1=>'SITS Assessment 1',2=>'SITS Assessment 2'];
        $mform->addElement('select', 'bath_grade_transfer_samis_assessment', 'Select Assessment to Link to', $assessment_details, []);
        $mform->addHelpButton('bath_grade_transfer_samis_assessment', 'bath_grade_transfer_samis_assessment', 'local_bath_grades_transfer');


    }

    /**
     * Fetch global config settings for the plugins
     * @param $modulename
     * @return mixed
     */
    public static function get_config_settings($modulename) {
        $pluginconfig = get_config('local_bath_grades_transfer', 'bath_grades_transfer_use_'.$modulename);
        return $pluginconfig;
    }

    /**
     * Defines the fields used in the settings
     * @return array
     */
    private function setting_fields(){
        return array('bath_grade_transfer_time_start','bath_grade_transfer_samis_assessment');

    }

    /**
     * Fetches settings for each course module id either
     * locally - from the moodle database table
     * remotely - from the SAMIS API
     * @param null $cmid
     * @return array
     */
    public function get_settings($cmid = null){
        if($cmid){
            //Get previous data + new data
        }
        else{
            //Get data from API
            //Connect to SAMIS via API
            // Send SAMIS attributes and fetch the data using WS

        }
        //This will call the web service, which returns an xml / json data
        //Data is turned into array and passed onto form elements to be included in a drop down
        global $DB;
        $defaults = $DB->get_records_menu('local_bath_grades_transfer', array('cm' => null), '');
        $settings = $DB->get_records_menu('local_bath_grades_transfer', array('cm' => $cmid), '');
        return $settings;
        //var_dump($defaults);
        //return $defaults;
    }

    /**
     * Action to perform when module settings a saved in modedit.php form page
     * @param $data
     */
    public function save_form_elements($data){
        //Get time start
        global $DB;
        var_dump($data);
        //Get the settings from the local_bath_grades_transfer DB
        $mod_setting_values = $this->get_settings($data->coursemodule);
        //Get the fields
        $fields = $this->setting_fields();

        //Get SAMIS course details
        $samis_mapping = $this->get_samis_mapping_attributes($data->course);
        if(!empty($samis_mapping)){

        }
        else{

        }
        foreach($fields as $key => $setting_value){
           if(isset($data->$setting_value)){
               $optionfield = new stdClass();
               $optionfield->coursemoduleid = $data->coursemodule;
               $optionfield->name = $setting_value;
               $optionfield->value = $data->$setting_value;

               if(isset($mod_setting_values[$setting_value])){
                   //Update
               }
               else{
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
    public function get_samis_mapping_attributes($moodlecourseid){
        $samis_attributes = array();
    global $DB;
        if(isset($moodlecourseid)){
            //Check if course exists
            if($DB->record_exists('course',['id'=>$moodlecourseid])){
                //Check if mapping exists ( should be default only)
                if($DB->record_exists('mdl_samis_mapping',['moodle_course_id'=> $moodlecourseid,'is_default'=>1])){
                    //Fetch the mapping
                    $record = $DB->get_record('mdl_samis_mapping',['moodle_course_id'=> $moodlecourseid,'is_default'=>1]);
                    if($record){
                        //Fetch SAMIS details only
                        $samis_attributes['samis_code'] = $record->samis_code;
                        $samis_attributes['academic_year'] = $record->academic_year;
                        $samis_attributes['period_code'] = $record->period_code;

                    }
                }
            }
        }
        return $samis_attributes;
    }

    /**
     * Test Connection SAMIS API
     * @param bool $testing
     */
    public function test_samis_connection($testing = false){
        //Contact the API client
        $this->api_client->authenticate();
    }
}