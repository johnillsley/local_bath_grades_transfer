<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15/03/2017
 * Time: 16:22
 */
class local_bath_grades_log
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
    public $userid;
    /**
     * @var
     */
    public $gradetransfermappingid;
    /**
     * @var
     */
    public $assessment_lookup_id;
    /**
     * @var
     */
    public $timetransferred;
    /**
     * @var
     */
    public $outcomeid;
    /**
     * @var
     */
    public $grade_transfer_error_id;
    /**
     * @var string
     */
    private static $table  ='local_bath_grades_log';

    /**
     *
     */
    public static function get_logs(){
        global $DB;

    }

    /**
     * @param $id
     */
    public static function get_log_by_id($id){

    }

    /**
     *
     */
    public static function write_log(){

    }

    /**
     *
     */
    public function save(){

    }


}