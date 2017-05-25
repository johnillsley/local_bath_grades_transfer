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
require_once 'api/rest_client.php';

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 03/03/2017
 * Time: 17:05
 */
class local_bath_grades_transfer_external_data
{
    /**
     * @var samis_http_client
     */
    private $wsclient;
    /**
     * @var array
     */
    public $assessment_data = array();

    /**
     * local_bath_grades_transfer_external_data constructor.
     */
    public function __construct() {
        //$this->http_ws_client = new local_bath_grades_transfer_samis_api_client();
        $this->http_wsclient = new samis_http_client();
        $this->rest_wsclient = new local_bath_grades_transfer_rest_client();

    }

    /** Handle WS error
     * @param $data
     * @throws Exception
     */
    private function handle_error($data) {
        //We have an error
        throw new \Exception("There is an error. STATUS: " . (string)$data->status . " MESSAGE: " . (string)$data->messagebuffer);
    }

    /**
     * @param local_bath_grades_transfer_samis_attributes $attributes
     * @throws Exception
     */
    public function get_remote_grade_structure($attributes, $samis_assessment_id) {
        $function = 'MOO_SAS_EXP';
        list($mab_name, $mab_seq) = explode("_", $samis_assessment_id);
        echo $mab_name;
        $data = array();
        try {
            //$xml_response = $this->http_wsclient->call_samis($function, $data);
            $xml_response = <<<XML
<records>
    <assessments>
        <assessment>
            <student>169156431/1</student>
            <name>ADLER NICKLAS</name>
            <year>2016/7</year>
            <period>S1</period>
            <module>MN10008</module>
            <occurrence>A</occurrence>
            <assess_pattern>MN10008C</assess_pattern>
            <assess_item>01</assess_item>
            <attempt>1</attempt>
            <mark></mark>
            <grade>F</grade>
</assessment>
        <assessment>
            <student>159124064/1</student>
            <name>AFFORD HANNAH N</name>
            <year>2016/7</year>
            <period>S1</period>
            <module>MN10008</module>
            <occurrence>A</occurrence>
            <assess_pattern>MN10008C</assess_pattern>
            <assess_item>01</assess_item>
            <attempt>1</attempt>
            <mark>95</mark>
            <grade>P</grade>
        </assessment>
</assessments>
</records>
XML;
            $data = simplexml_load_string($xml_response);
            return $data;
            if ($data->status < 0) {
                $this->handle_error($data);
            }
            if (isset($data->records)) {
                $assessments = $data->records->assessments;
                if (!empty($assessments)) {
                    $assessment_grade_structure = simplexml_load_string($assessments);
                }
                return $assessment_grade_structure;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }


    }

    /**
     * @param local_bath_grades_transfer_samis_attributes $attributes
     * @return array
     * @throws Exception
     */
    public function get_remote_assessment_details(\local_bath_grades_transfer_samis_attributes $attributes) {
        $function = 'MOO_MAB_EXP';
        $data = array();
        $assessments = array();
        //TODO Overwrite this with only a working value as SAMIS team is still setting this up
        $data['AYR_CODE'] = $attributes->academic_year;
        $data['MOD_CODE'] = $attributes->samis_code; //P06
        $data['PSL_CODE'] = $attributes->period_code; //P05
        $data['MAV_OCCUR'] = $attributes->occurrence; //P07
        //If for some reason we cant connect to the client ,report error
        try {
            $xml_response = $this->http_wsclient->call_samis($function, $data);
            $data = simplexml_load_string($xml_response);
            if ($data->status < 0) {
                //We have an error
                $this->handle_error($data);
            }
            if (isset($data->outdata)) {
                $xml_assessment_data = simplexml_load_string($data->outdata);

                foreach ($xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAB'}->{'MAB.CAMS'} as $objAssessment) {
                    $map_code = (string)$xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAP_CODE'};
                    if (!empty($objAssessment)) {
                        $assessments[$map_code][] = $objAssessment;
                    }
                }
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
        return $assessments;
    }

    /**
     * @param local_bath_grades_transfer_samis_attributes $attributes
     * @return array
     * @throws Exception
     */
    public function get_remote_assessment_details_rest(\local_bath_grades_transfer_samis_attributes $attributes) {
        $function = 'MAB_EXPORT';
        $data = array();
        $assessments = array();
        //TODO Overwrite this with only a working value as SAMIS team is still setting this up
        $data['MOD_CODE'] = $attributes->samis_code; //P06
        $data['AYR_CODE'] = str_replace('/', '-', $attributes->academic_year);
        $data['PSL_CODE'] = $attributes->period_code; //P05
        $data['MAV_OCCUR'] = $attributes->occurrence; //P07
        //If for some reason we cant connect to the client ,report error
        try {
            //$xml_response = $this->http_wsclient->call_samis($function, $data);
            $xml_response = $this->rest_wsclient->call_samis($function, $data);
            //var_dump($xml_response);

            //$data = simplexml_load_string($xml_response);
            $data = (json_decode($xml_response, true));

            if (isset($data) && !empty($data)) {
                if (isset($data['status']) && $data['status'] < 0) {
                    //We have an error
                    $this->handle_error($data);
                }
                foreach ($data['EXCHANGE']['MAV']['MAV.CAMS'] as $arrayCam) {
                    foreach ($arrayCam['MAP']['MAP.CAMS'] as $arrayMab) {
                        foreach ($arrayMab['MAB']['MAB.CAMS'] as $objAssessment) {
                            $map_code = $objAssessment['MAP_CODE'];
                            if (!empty($objAssessment)) {
                                $assessments[$map_code][] = $objAssessment;
                            }
                        }
                    }
                }
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            var_dump($e);
            throw new Exception($e->getMessage());
        }
        return $assessments;
    }


    /**
     * Given a bucs username, return the SPR code from SAMIS
     * @param $bucs_username
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function get_spr_from_bucs_id($bucs_username) {
        $method = 'MOO_SPR_EXP';
        $data['STU_UDF1'] = $bucs_username;
        $spr_code = null;
        try {
            $xml_response = $this->http_wsclient->call_samis($method, $data);
            $response_data = simplexml_load_string($xml_response);
            if ($response_data->status < 0) {
                //We have an error
                //$this->handle_error($response_data);
                throw new \Exception("There is an error SPR. STATUS: " . (string)$response_data->status . " MESSAGE: " . (string)$response_data->messagebuffer);
            }
            if (isset($response_data->outdata)) {
                $xml_assessment_data = simplexml_load_string($response_data->outdata);
                foreach ($xml_assessment_data->{'STU'}->{'STU.SRS'}->{'SCE'}->{'SCE.SRS'}->{'SCJ'}->{'SCJ.SRS'} as $objAssessment) {
                    $spr_code = (string)$xml_assessment_data->{'SCJ_CODE'};

                }
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
        die("SPR CODE !!!!");
        return $spr_code;
    }

    /**
     * @param $userid
     */
    public function set_export_grade_structure($userid) {
        //returns xml of each grade item to be passes to SAMIS
    }


    /**
     * @param $userdata
     * @param $samisdetails
     */
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