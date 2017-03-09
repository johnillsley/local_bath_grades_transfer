<?php
require_once 'api/samis_api_client.php';
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 03/03/2017
 * Time: 17:05
 */
class local_bath_grades_transfer_samis_assessment_data
{
    public $wsclient;

    public function __construct() {
        $this->ws_client = new local_bath_grades_transfer_samis_api_client();

    }
    public function get_remote_assessment_details_for_mapping($modulecode,$academic_year,$periodslotcode,$occurrence){
        $method = 'GetSamisAssessmentDetailsForCourse';
        $data = new stdClass();
        $data->modulecode = $modulecode;
        $data->academic_year = $academic_year;
        $data->periodslotcode = $periodslotcode;
        $data->occurrence = $occurrence;
        $this->ws_client->autheticate();
        if($this->ws_client->authenticated){
            return $this->ws_client->call($method,$data,$auth);
        }
    }

}