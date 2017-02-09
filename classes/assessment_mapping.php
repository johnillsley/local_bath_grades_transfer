<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 09/02/2017
 * Time: 14:53
 */
class assessment_mapping
{
    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $coursemoduleid;
    /**
     * @var
     */
    public $samis_unit_code;
    /**
     * @var
     */
    public $periodslotcode;
    /**
     * @var
     */
    public $academic_year;
    /**
     * @var
     */
    public $occurrence;
    /**
     * @var
     */
    public $mab_sequence;
    /**
     * @var
     */
    public $timecreated;
    /**
     * @var
     */
    public $modifierid;
    /**
     * @var
     */
    public $timeomdified;
    /**
     * @var
     */
    public $locked;
    /**
     * @var
     */
    public $samis_assessment_end_date;
    /**
     * @var
     */
    public $samis_assessment_id;
    /**
     * @var
     */
    public $samis_assessment_name;

    /**
     * assessment_mapping constructor.
     * @param $id
     * @param $coursemoduleid
     * @param $samis_unit_code
     * @param $periodslotcode
     * @param $academic_year
     * @param $occurrence
     * @param $mab_sequence
     * @param $timecreated
     * @param $modifierid
     * @param $timeomdified
     * @param $locked
     * @param $samis_assessment_end_date
     * @param $samis_assessment_id
     * @param $samis_assessment_name
     */
    public function __construct($id, $coursemoduleid, $samis_unit_code, $periodslotcode, $academic_year, $occurrence, $mab_sequence, $timecreated, $modifierid, $timeomdified, $locked, $samis_assessment_end_date, $samis_assessment_id, $samis_assessment_name) {
        $this->id = $id;
        $this->coursemoduleid = $coursemoduleid;
        $this->samis_unit_code = $samis_unit_code;
        $this->periodslotcode = $periodslotcode;
        $this->academic_year = $academic_year;
        $this->occurrence = $occurrence;
        $this->mab_sequence = $mab_sequence;
        $this->timecreated = $timecreated;
        $this->modifierid = $modifierid;
        $this->timeomdified = $timeomdified;
        $this->locked = $locked;
        $this->samis_assessment_end_date = $samis_assessment_end_date;
        $this->samis_assessment_id = $samis_assessment_id;
        $this->samis_assessment_name = $samis_assessment_name;
    }

    /**
     * @param $name
     */
    function __get($id) {
        // TODO: Implement __get() method.
    }
    public function delete_record(){
        global $DB;
    }
    /**
     * @param $name
     * @param $value
     */
    function __set($name, $value) {
        // TODO: Implement __set() method.
    }
}