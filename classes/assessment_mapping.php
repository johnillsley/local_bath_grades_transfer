<?php
require_once 'local_bath_grades_transfer_samis_assessment_data.php';
 /**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 09/02/2017
 * Time: 14:53
 */
class local_bath_grades_transfer_assessment_mapping
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



    public function __construct(){

    }

    /**
     * @param $name
     */
    function __get($id) {
        // TODO: Implement __get() method.
    }
    public function delete_record(){
        global $DB;
        if($DB->record_exists('local_bath_grades_transfer')){
            $DB->delete_records('local_bath_grades_transfer');
        }
    }
    public function get_by_cm_id($cmid){
        global $DB;
        $record = false;
        if($this->exists_by_cm_id($cmid)){
            $record =  $DB->get_record('local_bath_grades_transfer',['coursemoduleid'=>$cmid]);
        }
        return $record;
    }
    public function exists_by_cm_id($cmid){
        global $DB;
        return $DB->record_exists('local_bath_grades_transfer',['coursemoduleid'=>$cmid]);
    }
    public function exists_by_samis_assessment_id($map_code){
        global $DB;
        return $DB->record_exists('local_bath_grades_transfer',['coursemoduleid'=>$map_code]);
    }
    public function get_remote_assesment_data($modulecode,$academic_year,$periodslotcode,$occurrence){
        $assessment_data = new local_bath_grades_transfer_samis_assessment_data();
        $data = json_decode($assessment_data->get_remote_assessment_details_for_mapping($modulecode,$academic_year,$periodslotcode,$occurrence));
        return $data;
    }
    /**
     * @param $name
     * @param $value
     */
    function __set($name, $value) {
        // TODO: Implement __set() method.
    }


}