<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14/03/2017
 * Time: 14:04
 */
class local_bath_grades_transfer_assessment_lookup
{
    /**
     * @var string
     */
    private $table = 'local_bath_grades_lookup';


    /** Get assessment lookup record by SAMIS Assessment ID
     * @param $samis_assessment_id
     * @return mixed|null
     */
    public function get_by_samis_assessment_id($samis_assessment_id) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['id' => $samis_assessment_id])) {
            $record = $DB->get_record($this->table, ['id' => $samis_assessment_id]);
        }
        return $record;
    }

    /** Construct an ASSESSMENT ID as SAMIS does not seem to have a unique ID for MAB records
     * @param $samis_assessment_name
     * @param $assessment_seq
     * @return string
     */
    public function construct_assessment_id($samis_assessment_name, $assessment_seq) {
        return $samis_assessment_name . '-' . $assessment_seq;
    }

    /**
     * @param $samis_assessment_id
     * @return mixed|null
     */
    public function get_assessment_name_by_samis_assessment_id($samis_assessment_id) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['id' => $samis_assessment_id])) {
            $record = $DB->get_field($this->table, 'samis_assessment_name', ['id' => $samis_assessment_id]);
        }
        return $record;
    }

    /**
     * Get Assessment Lookup Details by providing SAMIS mapping attributes for a course
     * @param $module_code
     * @param $academic_year
     * @param $periodslotocode
     * @param $mav_occur
     * @return array
     */
    public function get_by_samis_details($module_code, $academic_year, $periodslotocode, $mav_occur) {
        global $DB;
        $record = null;
        return $DB->get_records($this->table, [
            'samis_unit_code' => $module_code,
            'academic_year' => $academic_year,
            'periodslotcode' => $periodslotocode,
            'occurrence' => $mav_occur
        ]);
    }
}