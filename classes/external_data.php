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
defined('MOODLE_INTERNAL') || die();
require_once('api/client.php');
require_once('api/rest_client.php');

class local_bath_grades_transfer_external_data {
    /**
     * @var samis_http_client
     */
    private $wsclient;
    /**
     * @var array
     */
    public $assessmentdata = array();

    /**
     * local_bath_grades_transfer_external_data constructor.
     */
    public function __construct() {
        $this->httpwsclient = new samis_http_client();
        $this->restwsclient = new local_bath_grades_transfer_rest_client();

    }

    /** Handle WS error
     * @param $data
     * @throws Exception
     */
    private function handle_error($data) {
        // We have an error.
        throw new \Exception("There is an error. STATUS: " . (string)$data->status . " MESSAGE: " . (string)$data->messagebuffer);
    }

    /**
     * Output from this is still XML.
     * @param \local_bath_grades_transfer_assessment_lookup $lookup
     * @return array|SimpleXMLElement
     * @throws Exception
     */
    public function get_remote_grade_structure(\local_bath_grades_transfer_assessment_lookup $lookup) {
        global $DB;

        $function = 'ASSESSMENTS';
        $data = $responses = array();
        $lookupattributes = $lookup->attributes;

        // DEV DATA FOR TESTING.
        $data['P04'] = str_replace('/', '-', $lookupattributes->academicyear);
        $data['P05'] = $lookupattributes->periodslotcode;
        $data['P06'] = $lookupattributes->samisunitcode;
        $data['P08'] = $lookup->mapcode;
        $data['P09'] = $lookup->mabseq;
        try {
            // Get all occurrences for the lookup.
            $conditions = array();
            $conditions["lookupid"] = $lookup->id;
            $occurrences = $DB->get_records('local_bath_grades_lookup_occ', $conditions, '', 'mavoccur');
            foreach ($occurrences as $occurrence) {
                $data['P07'] = $occurrence->mavoccur;
                error_log(json_encode($data), 0);
                $this->restwsclient->call_samis($function, $data);
                if ($this->restwsclient->response['status'] == 200 && $this->restwsclient->response['contents']) {
                    $response = simplexml_load_string($this->restwsclient->response['contents']);
                    if ($response->status < 0) {
                        $this->handle_error($response);
                    }
                }
                $responses[] = $response;
            }
            error_log(json_encode($responses), 0);
            return $responses;

            /*if (isset($response->records)) {
                $assessments = $response->records->assessments;
                if (!empty($assessments)) {
                    $assessmentgradestructure = simplexml_load_string($assessments);
                }
                return $assessmentgradestructure;
            }*/
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
        // TODO Overwrite this with only a working value as SAMIS team is still setting this up.
        $data['AYR_CODE'] = str_replace('/', '-', $attributes->academicyear);
        $data['MOD_CODE'] = $attributes->samisunitcode; // P06.
        $data['PSL_CODE'] = $attributes->periodslotcode; // P05.
        $data['MAV_OCCUR'] = $attributes->occurrence; // P07.
        // If for some reason we cant connect to the client ,report error.
        try {
            $xmlresponse = $this->httpwsclient->call_samis($function, $data);
            $data = simplexml_load_string($xmlresponse);
            if ($data->status < 0) {
                // We have an error.
                $this->handle_error($data);
            }
            if (isset($data->outdata)) {
                $xmlassessmentdata = simplexml_load_string($data->outdata);
                foreach ($xmlassessmentdata->{'mav'}->{'mav.cams'}->{'map'}->{'map.cams'}->{'mab'}->{'mab.cams'} as
                         $objassessment) {
                    $mapcode = (string)$xmlassessmentdata->{'mav'}->{'mav.cams'}->{'map'}->{'map.cams'}->{'map_code'};
                    if (!empty($objassessment)) {
                        $assessments[$mapcode][] = $objassessment;
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
        $data['MOD_CODE'] = $attributes->samisunitcode; // P06.
        $data['AYR_CODE'] = str_replace('/', '-', $attributes->academicyear);
        $data['PSL_CODE'] = $attributes->periodslotcode; // P05.
        $data['MAV_OCCUR'] = "<GOLD>*"; // P07. - GET ALL OCCURRENCES
        // If for some reason we cant connect to the client ,report error.
        try {
            $this->restwsclient->call_samis($function, $data);
            if ($this->restwsclient->response['status'] == 200 && $this->restwsclient->response['contents']) {
                $retdata = json_decode($this->restwsclient->response['contents'], true);
                if (is_null($retdata)) {
                    // Something else is wrong. Maybe SAMIS is un-reachable.
                    throw new Exception("Unexpected error occurred.Please contact SAMIS administrator");
                }
            }
            if (isset($retdata) && !empty($retdata)) {
                if (isset($retdata['status']) && $retdata['status'] < 0) {
                    // We have an error.
                    $this->handle_error($data);
                }
                foreach ($retdata['exchange']['mav']['mav.cams'] as $arraycam) {
                    $mavoccur = $arraycam['mav_occur'];
                    foreach ($arraycam['map']['map.cams'] as $arraymab) {
                        foreach ($arraymab['mab']['mab.cams'] as $objassessment) {
                            $mapcode = $objassessment['map_code'];
                            if (!empty($objassessment)) {
                                $assess['mavoccur'] = $mavoccur;
                                $assess['mapcode'] = $objassessment['map_code'];
                                $assess['mabseq'] = $objassessment['mab_seq'];
                                $assess['astcode'] = $objassessment['ast_code'];
                                $assess['mabperc'] = $objassessment['mab_perc'];
                                $assess['mabname'] = $objassessment['mab_name'];
                                // Added anonymous marking.
                                $assess['mab_pnam'] = $objassessment['mab_pnam'];
                                $assessments[$mapcode][] = self::convert_underscores_clean($assess);
                            }
                        }
                    }
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new Exception($e->getMessage());
        }
        return $assessments;
    }

    private static function convert_underscores_clean($array) {
        $newarray = array();
        foreach ($array as $k => $v) {
            $k = str_replace('_', '', $k);
            $newarray[$k] = $v;
        }
        return $newarray;
    }

    /**
     * Given a bucs username, return the SPR code from SAMIS
     * @param $bucsusername
     * @return stdClass $studentidentifers
     * @throws Exception
     */
    public function get_spr_from_bucs_id_rest($bucsusername) {
        $method = 'USERS';
        $data['STU_UDF1'] = $bucsusername;
        $studentidentifer = new stdClass();
        try {
            $this->restwsclient->call_samis($method, $data);
            if ($this->restwsclient->response['status'] == 200 && $this->restwsclient->response['contents']) {
                $retdata = new SimpleXMLIterator($this->restwsclient->response['contents'], null, false);
            }
            if (isset($retdata) && !empty($retdata)) {
                if (isset($retdata['status']) && $retdata['status'] < 0) {
                    // We have an error.
                    $this->handle_error($retdata);
                }
                /*foreach ($retdata->{'STU'}->{'STU.SRS'}->{'SCJ'}->{'SCE.SRS'} as $objspr) {
                    $studentidentifer->sprcode = (string)$objspr->{'SCJ'}->{'SCJ.SRS'}->{'SCJ_SPRC'};
                    $studentidentifer->candidatenumber = (string)$objspr->{'SCN'}->{'SCN.CAMS'}->{'SCN_CODE'};

                }*/
                for ($retdata->rewind(); $retdata->valid(); $retdata->next()) {
                    if ($retdata->hasChildren()) {
                        if ($retdata->key() == 'STU') {
                            // Continue.
                            foreach ($retdata->getChildren() as $STU => $stuobject) {
                                // Fetch STU Code.
                                $studentidentifer->stucode = (string)$stuobject->{'STU_CODE'};
                                // Fetch SPR Code.
                                foreach ($stuobject->{'SCJ'}->{'SCJ.SRS'} AS $scjobject) {
                                    $studentidentifer->sprcode  = (string)$scjobject->{'SCJ_SPRC'};
                                }
                                // Fetch Candidate Number.
                                foreach ($stuobject->{'SCN'}->{'SCN.CAMS'} AS $scnobject) {
                                    $studentidentifer->candidatenumber = (string)$scnobject->{'SCN_CODE'};
                                }
                            }
                        }
                    }
                }

            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
         return $studentidentifer;
    }


    /**
     * Collate and set the grades that next to be export grades to SAMIS
     * @param $objgrade
     * @return boolean true or false
     */
    public function set_export_grade($objgrade) {
        $method = 'ASSESSMENTS';
        $recordssimplexmlobject = new SimpleXMLElement("<records></records>");
        $assessments = $recordssimplexmlobject->addChild('assessments');
        $assessment = $assessments->addChild('assessment');
        $this->array_to_xml($objgrade, $assessment);
        $data['body'] = $recordssimplexmlobject->asXML();
        $data['P04'] = str_replace('/', '-', $objgrade->year);
        $data['P05'] = $objgrade->period;
        $data['P06'] = $objgrade->module;
        $data['P07'] = $objgrade->occurrence;
        $data['P08'] = $objgrade->assess_pattern;
        $data['P09'] = $objgrade->assess_item;
        try {
            $this->restwsclient->call_samis($method, $data, 'POST');
            if ($this->restwsclient->response['status'] == 201) {
                return true;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            die("Got an error from WS Client!");
        }
    }

    /**
     * Convert PHP Array to XMl
     * @param $array
     * @param $simplexmlobj
     */
    private function array_to_xml($array, &$simplexmlobj) {
        foreach ($array as $key => $value) {
            if ($key != 'samisdata') {
                $simplexmlobj->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

}