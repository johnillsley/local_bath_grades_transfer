<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once 'api/client.php';

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 03/03/2017
 * Time: 17:05
 */
class local_bath_grades_transfer_external_data
{
    public $wsclient;
    public $assessment_data = array();

    public function __construct() {
        //$this->ws_client = new local_bath_grades_transfer_samis_api_client();
        $this->wsclient = new samis_http_client();

    }

    public function get_remote_assessment_details(\local_bath_grades_transfer_samis_attributes $attributes) {
        $function = 'MOO_MAB_EXP';
        $data = array();

        //TODO Overwrite this with only a working value as SAMIS team is still setting this up
        $data['AYR_CODE'] = $attributes->academic_year;
        $data['MOD_CODE'] = 'MN10008'; //P06
        $data['MOD_CODE'] = $attributes->samis_code; //P06
        $data['PSL_CODE'] = $attributes->period_code; //P05
        $data['MAV_OCCUR'] = 'A'; //P07

        //If for some reason we cant connect to the client ,report error
        try {
            $xml_response = $this->wsclient->call_samis($function, $data);
            $data = simplexml_load_string($xml_response);
            if ($data->status < 0) {
                //We have an error
                throw new \Exception("There is an error. STATUS: " . (string)$data->status . " MESSAGE: " . (string)$data->messagebuffer);
            }
            if (isset($data->outdata)) {
                $xml_assessment_data = simplexml_load_string($data->outdata);
                $assessments = array();
                foreach ($xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAB'}->{'MAB.CAMS'} as $objAssessment) {
                    $map_code = (string)$xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAP_CODE'};
                    if (!empty($objAssessment)) {
                        $assessments[$map_code][] = $objAssessment;
                    }
                }
                return $assessments;
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function set_export_grade_structure_bulk() {

    }

    /*
     * Given a bucs username, return the SPR code from SAMIS
     */
    public function get_spr_from_bucs_id($bucs_username) {
        $method = 'GetSPRFromBUCSID';
        try {
            $xml_response = $this->wsclient->call($method, null);
            var_dump($xml_response);
            $data = simplexml_load_string($xml_response);
            return $data;

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function set_export_grade_structure($userid) {
        //returns xml of each grade item to be passes to SAMIS
    }

    public function get_grade_structure($samis_code, $periodslotcode,
                                        $academic_year, $assessment_item) {
        $grade_structure = array();
        $method = 'GetSamisGradesStructure';
        try {
            $xml_response = $this->wsclient->call($method, null);
            $data = simplexml_load_string($xml_response);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }

        return $data;
    }

    public function grade_transfer($userdata, $samisdetails) {

        // Unpack samis details
        $year = $samisdetails->academic_year;
        $module = $samisdetails->samis_unit_code;
        $period = $samisdetails->periodslotcode;
        //Pass data for one user
        $student = $userdata->spr_code;
        $mark = $userdata->mark;
        //Assessment details
        $assess_item = $samisdetails->samis_assessment_id;

    }

}