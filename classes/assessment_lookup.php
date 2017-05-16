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
    public $attributes;
    public $id;
    public $map_code;
    public $mab_seq;
    public $ast_code;
    public $mab_perc;
    public $mab_name;
    public $expired;
    public $samis_assessment_id;

    /**
     * local_bath_grades_transfer_assessment_lookup constructor.
     * @param null $attributes
     * @param $id
     * @param $map_code
     * @param $mab_seq
     * @param $ast_code
     * @param $mab_perc
     * @param $mab_name
     */
    public function __construct(\local_bath_grades_transfer_samis_attributes $attributes, $id = null, $map_code = null, $mab_seq = null, $ast_code = null, $mab_perc = null, $mab_name = null) {
        $this->attributes = $attributes;
        $this->id = $id;
        $this->map_code = (string)$map_code;
        $this->mab_seq = (string)$mab_seq;
        $this->ast_code = (string)$ast_code;
        $this->mab_perc = (string)$mab_perc;
        $this->mab_name = (string)$mab_name;
        $this->samis_assessment_mapping = new \local_bath_grades_transfer_assessment_mapping();
    }

    /**
     * local_bath_grades_transfer_assessment_lookup constructor.
     */

    public function create_lookup_instance() {

    }

    /** Get assessment lookup record by SAMIS Assessment ID
     * @param $samis_assessment_id
     * @return mixed|null
     */
    public function get_by_id($id) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['id' => $id])) {
            $record = $DB->get_record($this->table, ['id' => $id]);
        }
        return $record;
    }

    /**
     * Gets the lookup from the Moodle database and verifies it still exists in SAMIS
     * @param $lookupid
     * @return stdClass | bool $lookup Lookup object if exists or false
     */
    public function get_lookup($lookupid) {
        if (isset($lookupid)) {
            if ($lookup = $this->get_by_id($lookupid)) {
                if ($this->assessment_exists_in_samis($lookup->id) == true) {
                    //Assessment exists,return object
                    return $lookup;
                } else {
                    //TODO : log - lookup exists locally but missing from SAMIS
                }
            } else {
                //TODO : lookup does not exist locally anymore
            }
        }
        return false;
    }

    /**
     * Set an assessment lookup to be expired in the table
     * An expired lookup would mean....
     * @param $lookupid
     */
    public function set_expired($expired) {
        //Set expired
        $this->expired = $expired;
    }

    public function is_expired() {
        return $this->expired;
    }

    public function set_samis_assessment_id($samis_assessment_id) {
        //Set expired
        $this->samis_assessment_id = $samis_assessment_id;
    }

    public function get_samis_assessment_id() {
        return $this->samis_assessment_id;
    }

    public function lookup_exists_by_id($lookupid) {
        global $DB;
        return $DB->record_exists($this->table, ['id' => $lookupid]);

    }

    public function get_assessment_name_by_id($lookupid) {
        global $DB;
        $assessment_name = $DB->get_field($this->table, 'mab_name', ['id' => $lookupid]);
    }

    /**
     * Make sure that the assessment lookup record exists in the table
     * @param $assessment
     * @return bool
     */
    public function lookup_exists($map_code, $mab_seq) {
        global $DB;
        $id = null;
        $samis_assessment_id = $this->construct_assessment_id($map_code, $mab_seq);
        if (!isset($this->attributes)) {
            return false;
        }
        if ($id = $DB->get_field($this->table, 'id', array('samis_assessment_id' => $samis_assessment_id
        ,
            'samis_unit_code' => $this->attributes->samis_code,
            'periodslotcode' => $this->attributes->period_code,
            'academic_year' => $this->attributes->academic_year,
            'occurrence' => $this->attributes->occurrence))
        ) {

        }
        return $id;
    }

    /**
     * Add a new lookup record in the table
     * @param $data
     * @return bool|int|null
     */
    public function add_new_lookup($data) {
        global $DB;
        $id = null;
        $this->set_data($data);
        $data = new stdClass();
        $data->samis_unit_code = $this->attributes->samis_code;
        $data->periodslotcode = $this->attributes->period_code;
        $data->academic_year = $this->attributes->academic_year;
        $data->occurrence = $this->attributes->occurrence;
        $data->mab_seq = $this->mab_seq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->expired = null; // Initially not expired
        $data->samis_assessment_id = $this->construct_assessment_id($this->map_code, $this->mab_seq);
        $data->mab_name = $this->mab_name;
        $id = $DB->insert_record($this->table, $data, true);
        return $id;
    }

    private function set_data($objAssessment) {
        $this->map_code = (string)$objAssessment->MAP_CODE;
        $this->mab_seq = (string)$objAssessment->MAB_SEQ;
        $this->ast_code = (string)$objAssessment->AST_CODE;
        $this->mab_perc = (string)$objAssessment->MAB_PERC;
        $this->mab_name = (string)$objAssessment->MAB_NAME;
        if (isset($objAssessment->expired)) {
            $this->set_expired($objAssessment->expired);
        }
        if (isset($objAssessment->samis_assessment_id)) {
            $this->set_samis_assessment_id($objAssessment->samis_assessment_id);
        }
    }

    /** Construct an ASSESSMENT ID as SAMIS does not seem to have a unique ID for MAB records
     * @param $samis_assessment_name
     * @param $assessment_seq
     * @return string
     */
    public function construct_assessment_id($samis_assessment_name, $assessment_seq) {
        return $samis_assessment_name . '_' . $assessment_seq;
    }

    /**
     * @param $samis_assessment_id
     * @return mixed|null
     */
    public function get_assessment_name_by_samis_assessment_id($samis_assessment_id) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['id' => $samis_assessment_id])) {
            $record = $DB->get_field($this->table, 'mab_name', ['id' => $samis_assessment_id]);
        }
        return $record;
    }

    /**
     * Fetch remote assessments from SAMIS
     * @param local_bath_grades_transfer_samis_attributes object
     * @return array
     * @throws Exception
     */
    /*public function get_remote_assessments(\local_bath_grades_transfer_samis_attributes $samis_attributes) {
        $returnids = $assessments = array();
        global $DB;
        $samis_unit_code = $samis_attributes->samis_code;
        $academic_year = $samis_attributes->academic_year;
        $periodslotcode = $samis_attributes->period_code;
        $occurrence = $samis_attributes->occurrence;
        try {
            $remote_mappings = $this->samis_data->get_remote_assessment_details(
                $samis_attributes);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        if (isset($remote_mappings->outdata)) {
            $xml_assessment_data = simplexml_load_string($remote_mappings->outdata);
            //var_dump($xml_assessment_data);
            $assessments = array();
            foreach ($xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAB'}->{'MAB.CAMS'} as $objAssessment) {
                $map_code = (string)$xml_assessment_data->{'MAV'}->{'MAV.CAMS'}->{'MAP'}->{'MAP.CAMS'}->{'MAP_CODE'};
                if (!empty($objAssessment)) {
                    $assessments[$map_code][] = $objAssessment;

                }
            }
            var_dump($assessments);
        }
        foreach ($assessments as $arrayAssessmentsContainer) {
            foreach ($arrayAssessmentsContainer as $objAssessment) {
                //Single Object
                if ($this->lookup_exists($objAssessment)) {
                    $samis_assessment_id = $this->construct_assessment_id($objAssessment->map_code, $objAssessment->mab_seq);
                } else {
                    //Add it to the lookup table
                    $this->add_new_lookup($objAssessment);
                }
            }
        }
        //Add newly created remote assessment to the lookup table
        if ($assessments) {
            foreach ($assessments as $mapcode => $assessmentRecord) {
                foreach ($assessmentRecord as $key => $objAssessment) {
                    if ($this->lookup_exists($objAssessment)) {
                        //If it already exists in the lookup table , house keep it
                        //See that the lookup still has the same MAP_NAME
                        $samis_assessment_id = $this->construct_assessment_id($objAssessment->map_code, $objAssessment->mab_seq);
                        $local_lookup_samis_assessment_name = $DB->get_field($this->table, 'samis_assessment_name', array('samis_assessment_id' => $samis_assessment_id
                        ,
                            'samis_unit_code' => $objAssessment->module,
                            'periodslotcode' => $objAssessment->period,
                            'academic_year' => $objAssessment->year,
                            'occurrence' => $objAssessment->occurrence));
                        if ($local_lookup_samis_assessment_name !== $objAssessment->mab_name) {


                        }


                    } else {
                        //Add it locally
                        $data = new stdClass();
                        $data->samis_unit_code = $objAssessment->module;
                        $data->periodslotcode = $objAssessment->period;
                        $data->academic_year = $objAssessment->year;
                        $data->occurrence = $objAssessment->occurrence;
                        $data->mab_sequence = $objAssessment->mab_seq;
                        $data->timecreated = time();
                        $data->expired = 0; // Initially not expired
                        $samis_assessment_id = $this->construct_assessment_id($objAssessment->map_code, $objAssessment->mab_seq);
                        $data->samis_assessment_id = $samis_assessment_id;
                        $data->samis_assessment_name = $objAssessment->mab_name;
                        $returnids[] = $this->add_new_lookup($data);
                    }
                }

            }
        }
        return $returnids;

    }*/

    /**
     *
     */
    private function check_expired_assessments() {

    }

    /**
     * @param null $objLookup
     */
    public function housekeep_lookup($lookupid, $remote_assessment) {
        global $DB;
        //Check that the lookup is still there
        $objLookup = $DB->get_record($this->table, ['id' => $lookupid], '*', MUST_EXIST);
        //var_dump($objLookup);
        //$this->set_data($objLookup);
        $remote_assessment_id = $this->construct_assessment_id($remote_assessment->MAP_CODE, $remote_assessment->MAB_SEQ);
        if (!empty($objLookup) && $objLookup->samis_assessment_id == $remote_assessment_id) {
            //Exists
            $exists = true;
            //Do nothing

        } else {
            //Eek ! Something has changed on SAMIS , for now, set this to expired
            // remove the link from the mappings table to that lookup id
            $this->set_expired($lookupid);
        }
    }

    /**
     *
     * Based on the Lookup ID see if the assessment still exists in SAMIS,
     * if not , set expire to 1 and return false
     * @return bool true | false
     */
    public function assessment_exists_in_samis($remote_assessment) {
        echo "Checking against remote asessment";
        $exists = false;
        //Compare this to the remote assessment object
        var_dump($remote_assessment);
        die;

        //From the lookup id , get the details
        //From the details, get the SAMIS attributes
        //From SAMIS attributes, contact samis to see if its there
        if (!is_null($assessment_lookup)) {
            $samis_assessment_id = $assessment_lookup->samis_assessment_id;
            $samis_attributes = new local_bath_grades_transfer_samis_attributes(
                $assessment_lookup->samis_unit_code,
                $assessment_lookup->academic_year,
                $assessment_lookup->periodslotcode,
                $assessment_lookup->occurrence,
                $assessment_lookup->mab_sequence);
            try {
                $remote_mappings = $this->samis_assessment_data->get_remote_assessment_details(
                    $samis_attributes);

            } catch (\Exception $e) {

            }
            var_dump($remote_mappings);
            //TODO Fix this for later
            foreach ($remote_mappings->assessments->assessment as $remote_mapping_object) {
                $remote_mapping_assessment_id = $this->construct_assessment_id($remote_mapping_object->map_code, $remote_mapping_object->mab_seq);
                echo "Comparing $remote_mapping_assessment_id with $samis_assessment_id";
                if ($remote_mapping_assessment_id == $samis_assessment_id) {
                    $exists = true;
                } else {
                    //Eek ! Something has changed on SAMIS , for now, set this to expired and skip it
                    $this->set_expired($lookupid);
                    $exists = false;
                }
            }

        }
        return $exists;
    }


    /**
     * Get Assessment Lookup locally by SAMIS details
     * @param local_bath_grades_transfer_samis_attributes $attributes
     * @return array
     */
    public function get_by_samis_details() {
        global $DB;
        $record = null;
        return $DB->get_records($this->table, [
            'samis_unit_code' => $this->attributes->samis_code,
            'academic_year' => $this->attributes->academic_year,
            'periodslotcode' => $this->attributes->period_code,
            'occurrence' => $this->attributes->occurrence
        ]);
    }
}