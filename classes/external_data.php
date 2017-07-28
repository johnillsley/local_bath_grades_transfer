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
     * Output from this is still XML.
     * @param \local_bath_grades_transfer_assessment_lookup $lookup
     * @return array|SimpleXMLElement
     * @throws Exception
     */
    public function get_remote_grade_structure(\local_bath_grades_transfer_assessment_lookup $lookup) {
        $function = 'ASSESSMENTS';
        $data = array();
        $lookup_attributes = $lookup->attributes;
        /*$data['P04'] = $lookup_attributes->academic_year ;
        $data['P05'] = $lookup_attributes->periodslotcode;
        $data['P06'] = $lookup_attributes->samis_unit_code;
        $data['P07'] = $lookup_attributes->occurrence;
        $data['P08'] = $lookup->map_code;
        $data['P09'] = $lookup->mab_seq;*/

        //DEV DATA FOR TESTING
        $data['P04'] = '2016-7';
        $data['P05'] = 'S1';
        $data['P06'] = 'ME10003';
        $data['P07'] = 'A';
        $data['P08'] = 'ME10003A';
        $data['P09'] = '01';
        try {
            $this->rest_wsclient->call_samis($function, $data);
            if ($this->rest_wsclient->response['status'] = 200 && $this->rest_wsclient->response['contents']) {
                $data = simplexml_load_string($this->rest_wsclient->response['contents']);
                return $data;
            }

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
        $function = 'MABS';
        $data = array();
        $assessments = array();
        //TODO Overwrite this with only a working value as SAMIS team is still setting this up
        $data['AYR_CODE'] = str_replace('/', '-', $attributes->academic_year);
        $data['MOD_CODE'] = $attributes->samis_unit_code; //P06
        $data['PSL_CODE'] = $attributes->periodslotcode; //P05
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

                foreach ($xml_assessment_data->{'mav'}->{'mav.cams'}->{'map'}->{'map.cams'}->{'mab'}->{'mab.cams'} as $objAssessment) {
                    $map_code = (string)$xml_assessment_data->{'mav'}->{'mav.cams'}->{'map'}->{'map.cams'}->{'map_code'};
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
        $function = 'MABS';

        $data = array();
        $assessments = array();
        //TODO Overwrite this with only a working value as SAMIS team is still setting this up
        $data['MOD_CODE'] = $attributes->samis_unit_code; //P06
        $data['AYR_CODE'] = str_replace('/', '-', $attributes->academic_year);
        $data['PSL_CODE'] = $attributes->periodslotcode; //P05
        $data['MAV_OCCUR'] = $attributes->occurrence; //P07
        //If for some reason we cant connect to the client ,report error
        try {
            $this->rest_wsclient->call_samis($function, $data);
            if ($this->rest_wsclient->response['status'] == 200 && $this->rest_wsclient->response['contents']) {
                $retdata = json_decode($this->rest_wsclient->response['contents'], true);
            }
            if (isset($retdata) && !empty($retdata)) {
                if (isset($retdata['status']) && $retdata['status'] < 0) {
                    //We have an error
                    $this->handle_error($data);
                }
                foreach ($retdata['exchange']['mav']['mav.cams'] as $arrayCam) {
                    foreach ($arrayCam['map']['map.cams'] as $arrayMab) {
                        foreach ($arrayMab['mab']['mab.cams'] as $objAssessment) {
                            $map_code = $objAssessment['map_code'];
                            if (!empty($objAssessment)) {
                                $assessments[$map_code][] = $objAssessment;
                            }
                        }
                    }
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            //var_dump($e);
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
    public function get_spr_from_bucs_id_rest($bucs_username) {
        $method = 'USERS';
        $data['STU_UDF1'] = $bucs_username;
        $spr_code = null;
        try {
            $this->rest_wsclient->call_samis($method, $data);
            if ($this->rest_wsclient->response['status'] == 200 && $this->rest_wsclient->response['contents']) {
                $retdata = simplexml_load_string($this->rest_wsclient->response['contents']);
            }

            if (isset($retdata) && !empty($retdata)) {
                if (isset($retdata['status']) && $retdata['status'] < 0) {
                    //We have an error
                    $this->handle_error($data);
                }

                foreach ($retdata->{'STU'}->{'STU.SRS'}->{'SCE'}->{'SCE.SRS'}->{'SCJ'} as $objSPR) {
                    //var_dump($objSPR->{'SCJ.SRS'}->{'SCJ_SPRC'});
                    $spr_code = (string)$objSPR->{'SCJ.SRS'}->{'SCJ_SPRC'};
                }
                var_dump($spr_code);
            }
        } catch
        (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
        // die("SPR CODE !!!!");
        return $spr_code;

    }


    /**
     * Collate and set the grades that next to be export grades to SAMIS
     * @param $grades
     */
    public function set_export_grade($objGrade) {
        $method = 'ASSESSMENTS';
        echo "\n\nINSIDE SET EXPORT GRADES+++++++++++++++\n";
        $recordsSimpleXMLObject = new SimpleXMLElement("<records></records>");
        $assessments = $recordsSimpleXMLObject->addChild('assessments');
        $assessment = $assessments->addChild('assessment');
        $this->array_to_xml($objGrade, $assessment);
        $data['body'] = $recordsSimpleXMLObject->asXML();
        $data['P04'] = str_replace('/', '-', $objGrade->year);
        $data['P05'] = $objGrade->period;
        $data['P06'] = $objGrade->module;
        $data['P07'] = $objGrade->occurrence;
        $data['P08'] = $objGrade->assess_pattern;
        $data['P09'] = $objGrade->assess_item;
        try {
            $starttime = microtime(true);
            $this->rest_wsclient->call_samis($method, $data, 'POST');
            if ($this->rest_wsclient->response['status'] == 201) {
                echo "\n++++++++GRADE SENT TO SAMIS SUCCESSFULLY for $objGrade->name +++++";
                return true;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {

        }
        //die("GRADES DONE!");


    }

    /**
     * Convert PHP Array to XMl
     * @param $array
     * @param $simplexmlobj
     */
    private function array_to_xml($array, &$simplexmlobj) {
        foreach ($array as $key => $value) {
            $simplexmlobj->addChild("$key", htmlspecialchars("$value"));
        }
    }

}