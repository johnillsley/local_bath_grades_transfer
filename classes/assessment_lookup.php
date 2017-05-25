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
    private static $table = 'local_bath_grades_lookup';
    public $attributes;
    public $id;
    public $map_code;
    public $mab_seq;
    public $ast_code;
    public $mab_perc;
    public $mab_name;
    public $expired;
    public $samis_assessment_id;

    public function set_attributes(\local_bath_grades_transfer_samis_attributes $attributes) {
        /*$this->attributes->samis_code = $attributes->samis_code;
        $this->attributes->period_code = $attributes->period_code;
        $this->attributes->academic_year = $attributes->academic_year;
        $this->attributes->occurrence = $attributes->occurrence;*/
        $this->attributes = $attributes;
    }

    /** Get assessment lookup record by Lookup ID
     * @param $samis_assessment_id
     * @return mixed|null
     */
    public static function get_by_id($id) {
        global $DB;
        $object = null;
        $record = null;
        if ($DB->record_exists(self::$table, ['id' => $id])) {
            $record = $DB->get_record(self::$table, ['id' => $id]);
            $object = self::instantiate($record);
        }
        return $object;
    }

    public static function get($id) {
        if (isset($id)) {
            return self::get_by_id($id);
        }
        return false;
    }

    /**
     * Gets the lookup from the Moodle database and verifies it still exists in SAMIS
     * @param $lookupid
     * @return stdClass | bool $lookup Lookup object if exists or false
     */
    public function get_lookup($lookupid) {
        if (isset($lookupid)) {
            if ($lookup = $this->get_by_id($lookupid)) {
                return $lookup;
            }
        }
        return false;
    }

    protected function has_attribute($attribute) {
        $object_vars = get_object_vars($this);
        return array_key_exists($attribute, $object_vars);
    }

    private static function instantiate($record) {

        $object = new self;
        foreach ($record as $key => $value) {
            if ($object->has_attribute($key)) {
                $object->$key = $value;
            }
            //Add the attributes
            $object->attributes = new \local_bath_grades_transfer_samis_attributes(
                $record->samis_unit_code,
                $record->academic_year,
                $record->periodslotcode,
                $record->mab_seq);
        }
        return $object;
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

    public static function lookup_exists_by_id($lookupid) {
        global $DB;
        return $DB->record_exists(self::$table, ['id' => $lookupid]);

    }

    public function get_assessment_name() {
        return $this->mab_name;
    }

    public static function get_assessment_name_by_id($lookupid) {
        global $DB;
        $assessment_name = $DB->get_field(self::$table, 'mab_name', ['id' => $lookupid]);
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
        if ($id = $DB->get_field(self::$table, 'id', array('samis_assessment_id' => $samis_assessment_id
        ,
            'samis_unit_code' => $this->attributes->samis_code,
            'periodslotcode' => $this->attributes->period_code,
            'academic_year' => $this->attributes->academic_year,
            'occurrence' => $this->attributes->mab_seq))
        ) {

        }
        return $id;
    }

    public function add() {
        global $DB;
        $id = null;
        $data = new stdClass();
        $data->mab_seq = $this->mab_seq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->samis_unit_code = $this->attributes->samis_code;
        $data->periodslotcode = $this->attributes->period_code;
        $data->academic_year = $this->attributes->academic_year;
        $data->occurrence = $this->attributes->occurrence;
        //new lookup has no expiry
        $this->set_expired(0);
        $data->samis_assessment_id = $this->construct_assessment_id($this->map_code, $this->mab_seq);
        $data->mab_name = $this->mab_name;
        $id = $DB->insert_record(self::$table, $data, true);
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
        $id = $DB->insert_record(self::$table, $data, true);
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
        if ($DB->record_exists(self::$table, ['id' => $samis_assessment_id])) {
            $record = $DB->get_field(self::$table, 'mab_name', ['id' => $samis_assessment_id]);
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

    public function save() {
        return isset($this->id) ? $this->update() : $this->create();

    }

    protected function create() {
        global $DB;
        $id = null;
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
        $data->samis_assessment_id = $this->construct_assessment_id($this->mab_name, $this->mab_seq);
        $data->mab_name = $this->mab_name;

    }

    protected function update() {
        global $DB;
        $data = new stdClass();
        $data->id = $this->id;
        $data->mab_seq = $this->mab_seq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->expired = $this->expired;
        $data->mab_name = $this->mab_name;
        $data->samis_assessment_id = $this->samis_assessment_id;
        $DB->update_record(self::$table, $data, true);

    }

    /**
     *
     * Based on the Lookup ID see if the assessment still exists in SAMIS,
     * if not , set expire to 1 and return false
     * @return bool true | false
     */
    public function assessment_exists_in_samis() {
        $this->samis_data = new \local_bath_grades_transfer_external_data();
        $exists = false;
        if (isset($this->id)) {
            $samis_attributes = new local_bath_grades_transfer_samis_attributes(
                $this->attributes->samis_code,
                $this->attributes->academic_year,
                $this->attributes->period_code,
                $this->attributes->occurrence,
                $this->attributes->mab_sequence);
            try {
                $remote_assessment_data = $this->samis_data->get_remote_assessment_details($samis_attributes);
                foreach ($remote_assessment_data as $map_code => $arrayAssessments) {
                    foreach ($arrayAssessments as $objAssessment) {
                        var_dump($objAssessment);
                        $remote_mapping_assessment_id = $this->construct_assessment_id($objAssessment->MAP_CODE, $objAssessment->MAB_SEQ);
                        echo "Comparing $remote_mapping_assessment_id with $this->samis_assessment_id";
                        if ($remote_mapping_assessment_id == $this->samis_assessment_id) {
                            $exists = true;
                            echo "!!!!MATCH FOUND!!!!";
                        } else {

                            $exists = false;
                        }
                    }
                }

            } catch (\Exception $e) {
                //Some error, show it on the screen and continue
                //TODO log it
                echo $e->getMessage();
                echo "does not exist ! ";
                $exists = false;
            }
        }
        return $exists;
    }


    /**
     * Get Assessment Lookup locally by SAMIS details
     * @param local_bath_grades_transfer_samis_attributes $attributes
     * @return array
     */
    public static function get_by_samis_details($samis_attributes) {
        global $DB;
        $objects = null;
        $records = $DB->get_records(self::$table, [
            'samis_unit_code' => $samis_attributes->samis_code,
            'academic_year' => $samis_attributes->academic_year,
            'periodslotcode' => $samis_attributes->period_code,
            'occurrence' => $samis_attributes->occurrence
        ]);
        if (!empty($records)) {
            foreach ($records as $record) {
                $objects[] = self::instantiate($record);
            }
        }

        return $objects;
    }
}