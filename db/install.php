<?php
defined('MOODLE_INTERNAL') || die();
 function xmldb_local_bath_grades_transfer_install(){
     global $CFG,$DB;
     $dbman = $DB->get_manager();
     $table = new xmldb_table('local_bath_grades_outcome');
     $outcomes = array(
         1=>'Grade was transferred successfully',
         2=>'Grade is missing',
         3=> 'There was an error transferring grade',
         4=>'Grade already exists in SAMIS',
         5=>'Grade not found in Moodle course',
         6=>'Grade is not out of 100',
         7=>'Grade Structure is empty',
         8 => 'Added to Queue');
     if($dbman->table_exists($table)){
         // Add Data.
         foreach($outcomes as $outcome){
             $outcomeobj = new stdClass();
             $outcomeobj->outcome = $outcome;
             $DB->insert_record('local_bath_grades_outcome',$outcomeobj);

         }
     }

 }