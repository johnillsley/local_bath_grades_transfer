<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 08/03/2017
 * Time: 16:16
 */
require_once($CFG->dirroot.'/course/moodleform_mod.php');
class samis_assessment_form extends moodleform_mod {


    public function definition() {
      // TODO: Implement definition() method.
      $mform = $this->_form;
       $mform->addElement('html', "<span class=\"alert alert-warning\">Please ensure you have contacted SAMIS....</span>");
      $mform->addElement('html', "<span class=\"alert alert-warning\">Please ensure you have contacted SAMIS....</span>");
      //Date-Time Selector to show the transfer date
      $mform->addElement('date_time_selector', 'bath_grade_transfer_time_start', 'Transfer grades from', [], []);
      $mform->addHelpButton('bath_grade_transfer_time_start', 'bath_grade_transfer_time_start', 'local_bath_grades_transfer');
      $select = $mform->addElement('select', 'bath_grade_transfer_samis_assessment', 'Select Assessment to Link to', [], []);
      $remote_assessments = $this->_customdata['remote_assessments'];
      foreach ($remote_assessments as $map_code  => $arrAssessment) {
          var_dump($map_code);
          $data = new stdClass();
          $data->samis_unit_code = 'ED00000';
          $data->academic_year = '2016/7';
          $data->periodslotcode = 'AY';
          $data->occurrence = 'A';
          $select->addOption( "$data->samis_unit_code -
                $data->academic_year - 
                $data->periodslotcode - 
                $data->occurrence", '', array( 'disabled' => 'disabled','class'=>'samis_assessment_mapping_details' ) );
          //Help button for transfer data
          foreach($arrAssessment as $key => $objAssessment){
              $select->addOption($objAssessment->mab_name,$objAssessment->map_code.'_'.$objAssessment->mab_seq);
              //$options[$objAssessment->map_code.'_'.$objAssessment->mab_seq] = ;
          }
      }
  }
}
