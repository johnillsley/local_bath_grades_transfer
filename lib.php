<?php
class local_bath_grades_transfer  {

    public function get_form_elements_module($mform, $context, $modulename = "") {
        global $DB, $COURSE;

        // Check plugin is configured.

        // Get settings
        //$settings = $this->get_settings();
        //var_dump($settings);
        $mform->addElement('date_time_selector', 'transfer_time_start', 'Transfer grades from');
        $mform->addHelpButton('transfer_time_start', 'transfer_time_start', 'local_bath_grades_transfer');
        $assessment_details = [1=>'SITS Assessment 1',2=>'SITS Assessment 2'];
        $mform->addElement('select', 'samis_assessment', 'Select Assessment to Link to', $assessment_details, []);
        $mform->addHelpButton('samis_assessment', 'samis_assessment', 'local_bath_grades_transfer');


    }
    public function get_settings($cmid = null){
        //This will call the web service, which returns an xml / json data
        //Data is turned into array and passed onto form elements to be included in a drop down
        global $DB;
        //$defaults = $DB->get_records_menu('local_bath_grades_transfer', array('course_module_id' => null),     '', 'name,value');
        //var_dump($defaults);
        //return $defaults;
    }
    public function save_form_elements(){

    }
    public function test_samis_connection(){

    }
}