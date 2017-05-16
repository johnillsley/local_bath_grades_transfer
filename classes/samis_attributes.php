<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 27/04/2017
 * Time: 16:53
 */
class local_bath_grades_transfer_samis_attributes
{

    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     */
    public $samis_code;
    /**
     * @var
     */
    public $academic_year;
    /**
     * @var
     */
    public $period_code;
    /**
     * @var
     */
    public $occurrence;

    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     */
    public function __construct() {

    }

    /**
     * @param $moodlecourseid
     */
    public function set($moodlecourseid) {
        global $DB;
        if (isset($moodlecourseid)) {
            //Check if course exists
            if ($DB->record_exists('course', ['id' => $moodlecourseid])) {
                //Check if mapping exists ( should be default only)
                //Fetch the mapping
                $record = $DB->get_record('samis_mapping', ['moodle_course_id' => $moodlecourseid, 'is_default' => 1]);
                if ($record) {
                    //Return Samis attributes object
                    $this->samis_code = $record->samis_code;
                    $this->academic_year = $record->academic_year;
                    $this->period_code = $record->period_code;
                    $this->occurrence = $record->occurrence;
                }

            }
        }
    }


}