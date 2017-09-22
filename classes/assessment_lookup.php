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
    /**
     * @var
     */
    public $attributes;
    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $mapcode;
    /**
     * @var
     */
    public $mabseq;
    /**
     * @var
     */
    public $astcode;
    /**
     * @var
     */
    public $mabperc;
    /**
     * @var
     */
    public $mabname;
    /**
     * @var
     */
    public $expired;
    /**
     * @var
     */
    public $samisassessmentid;
    private $samisdata;

    public function __construct() {
        $this->samisdata = new \local_bath_grades_transfer_external_data();

    }

    /**
     * @param local_bath_grades_transfer_samis_attributes $attributes
     */
    public function set_attributes(\local_bath_grades_transfer_samis_attributes $attributes) {
        $this->attributes = $attributes;
    }

    /** Get assessment lookup record by Lookup ID
     * @param $id
     * @return mixed|null
     * @internal param $samisassessmentid
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

    /**
     * @param $id
     * @return bool|mixed|null
     */
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

    /** See if a object has the class attribute present
     * @param $attribute
     * @return bool
     */
    protected function has_attribute($attribute) {
        $objectvars = get_object_vars($this);
        return array_key_exists($attribute, $objectvars);
    }

    public static function get_lookup_by_academicyear($academicyear) {
        global $DB;
        $objects = null;
        $records = $DB->get_records(self::$table, [
            'academicyear' => $academicyear
        ]);
        if (!empty($records)) {
            foreach ($records as $record) {
                $objects[] = self::instantiate($record);
            }
        }
        return $objects;
    }

    /**
     * @param $record
     * @return local_bath_grades_transfer_assessment_lookup
     */
    private static function instantiate($record) {

        $object = new self;
        foreach ($record as $key => $value) {
            if ($object->has_attribute($key)) {
                $object->$key = $value;
            }
            // Add the attributes.
            $object->attributes = new \local_bath_grades_transfer_samis_attributes(
                $record->samisunitcode,
                $record->academicyear,
                $record->periodslotcode,
                $record->mabseq);
        }
        return $object;
    }

    /**
     * Set an assessment lookup to be expired in the table
     * An expired lookup would mean....
     * @param $expired
     * @internal param $lookupid
     */
    public function set_expired($expired) {
        //Set expired
        $this->expired = $expired;
    }

    /**
     * @return mixed
     */
    public function is_expired() {
        return $this->expired;
    }

    /**
     * @param $samisassessmentid
     */
    public function set_samisassessmentid($samisassessmentid) {
        // Set expired.
        $this->samisassessmentid = $samisassessmentid;
    }

    /**
     * @return mixed
     */
    public function get_samisassessmentid() {
        return $this->samisassessmentid;
    }

    /**
     * @param $lookupid
     * @return bool
     */
    public static function lookup_exists_by_id($lookupid) {
        global $DB;
        return $DB->record_exists(self::$table, ['id' => $lookupid]);

    }

    /**
     * @return mixed
     */
    public function get_assessment_name() {
        return $this->mabname;
    }

    /**
     * @param $lookupid
     */
    public static function get_assessment_name_by_id($lookupid) {
        global $DB;
        $assessmentname = $DB->get_field(self::$table, 'mabname', ['id' => $lookupid]);
        return $assessmentname;
    }

    /**
     * Make sure that the assessment lookup record exists in the table
     * @param $mapcode
     * @param $mabseq
     * @return bool
     * @internal param $assessment
     */
    public function lookup_exists($mapcode, $mabseq) {
        global $DB;
        $id = null;
        $samisassessmentid = $this->construct_assessment_id($mapcode, $mabseq);
        if (!isset($this->attributes)) {
            return false;
        }
        if ($id = $DB->get_field(self::$table, 'id', array(
            'samisassessmentid' => $samisassessmentid,
            'samisunitcode' => $this->attributes->samisunitcode,
            'periodslotcode' => $this->attributes->periodslotcode,
            'academicyear' => $this->attributes->academicyear,
            'occurrence' => $this->attributes->occurrence))
        ) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * @return bool|int|null
     */
    /*
    public function add() {
        global $DB;
        $id = null;
        $data = new stdClass();
        $data->mabseq = $this->mabseq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->samisunitcode = $this->attributes->samisunitcode;
        $data->periodslotcode = $this->attributes->periodslotcode;
        $data->academicyear = $this->attributes->academicyear;
        $data->occurrence = $this->attributes->occurrence;
        $data->mapcode = $this->mapcode;
        //new lookup has no expiry
        $this->set_expired(0);
        $data->samisassessmentid = $this->construct_assessment_id($this->mapcode, $this->mabseq);
        $data->mab_name = $this->mab_name;
        var_dump($data);
        $id = $DB->insert_record(self::$table, $data, true);
        return $id;
    }
    */
    /**
     * Add a new lookup record in the table
     * @param $data
     * @return bool|int|null
     */
    /*
    public function add_new_lookup($data) {
        global $DB;
        $id = null;
        $data = new stdClass();
        $data->samisunitcode = $this->attributes->samisunitcode;
        $data->periodslotcode = $this->attributes->periodslotcode;
        $data->academicyear = $this->attributes->academicyear;
        $data->occurrence = $this->attributes->occurrence;
        $data->mabseq = $this->mabseq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->expired = null; // Initially not expired
        $data->samisassessmentid = $this->construct_assessment_id($this->mapcode, $this->mabseq);
        $data->mab_name = $this->mab_name;
        $id = $DB->insert_record(self::$table, $data, true);
        return $id;
    }
    */
    /**
     * @param $objAssessment
     */
    /*
    private function set_data($objAssessment) {
        $this->mapcode = (string)$objAssessment->mapcode;
        $this->mabseq = (string)$objAssessment->mabseq;
        $this->ast_code = (string)$objAssessment->AST_CODE;
        $this->mab_perc = (string)$objAssessment->MAB_PERC;
        $this->mab_name = (string)$objAssessment->MAB_NAME;
        if (isset($objAssessment->expired)) {
            $this->set_expired($objAssessment->expired);
        }
        if (isset($objAssessment->samisassessmentid)) {
            $this->set_samisassessmentid($objAssessment->samisassessmentid);
        }
    }
    */
    /** Construct an ASSESSMENT ID as SAMIS does not seem to have a unique ID for MAB records
     * @param $samis_assessment_name
     * @param $assessment_seq
     * @return string
     */
    /*
    public function construct_assessment_id($samis_assessment_name, $assessment_seq) {
        return $samis_assessment_name . '_' . $assessment_seq;
    }
    */
    /** Return Assessment Name by Assessment ID
     * @param $samisassessmentid
     * @return mixed|null
     */
    /*
    public function get_assessment_name_by_samisassessmentid($samisassessmentid) {
        global $DB;
        $record = null;
        if ($DB->record_exists(self::$table, ['id' => $samisassessmentid])) {
            $record = $DB->get_field(self::$table, 'mab_name', ['id' => $samisassessmentid]);
        }
        return $record;
    }
    */

    /**
     * Get the current lookup and check it against remote to make sure it is still valid
     */
    /*
    public function housekeep() {
        if ($this->assessment_exists_in_samis() == false) {
            //does not exist
            if (!$this->is_expired()) {
                //TODO log it
                echo "Setting it to be expired";
                $this->set_expired(time());
            }
            $this->update();
        }
    }
    */
    /**
     * Create new assessment lookup
     */
    /*
    protected function create() {
        $id = null;
        $data = new stdClass();
        $data->samisunitcode = $this->attributes->samisunitcode;
        $data->periodslotcode = $this->attributes->periodslotcode;
        $data->academicyear = $this->attributes->academicyear;
        $data->occurrence = $this->attributes->occurrence;
        $data->mabseq = $this->mabseq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->expired = null; // Initially not expired
        $data->samisassessmentid = $this->construct_assessment_id($this->mab_name, $this->mabseq);
        $data->mab_name = $this->mab_name;

    }
    */
    /**
     * Update a lookup entry
     */
    /*
    protected function update() {
        global $DB;
        $data = new stdClass();
        $data->id = $this->id;
        $data->mabseq = $this->mabseq;
        $data->ast_code = $this->ast_code;
        $data->mab_perc = $this->mab_perc;
        $data->timecreated = time();
        $data->expired = $this->expired;
        $data->mab_name = $this->mab_name;
        $data->samisassessmentid = $this->samisassessmentid;
        $DB->update_record(self::$table, $data, true);

    }
    */
    /**
     *
     * Based on the Lookup ID see if the assessment still exists in SAMIS,
     * if not , set expire to 1 and return false
     * @return bool true | false
     */
    /*
    public function assessment_exists_in_samis() {
        $exists = false;
        if (isset($this->id)) {
            $samisattributes = new local_bath_grades_transfer_samis_attributes(
                $this->attributes->samisunitcode,
                $this->attributes->academicyear,
                $this->attributes->periodslotcode,
                $this->attributes->occurrence,
                $this->attributes->mabsequence);
            try {
                $remote_assessment_data = $this->samis_data->get_remote_assessment_details_rest($samisattributes);

                foreach ($remote_assessment_data as $mapcode => $arrayAssessments) {
                    foreach ($arrayAssessments as $key => $arrayAssessment) {
                        $remote_mapping_assessment_id = $this->construct_assessment_id($arrayAssessment['mapcode'], $arrayAssessment['mabseq']);
                        if ($remote_mapping_assessment_id == $this->samisassessmentid) {
                            echo "DOES  EXIST ";
                            $exists = true;
                        } else {
                            echo "DOES  NOT EXIST ";
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
    */

    /**
     * Get Assessment Lookup locally by SAMIS details
     * @param \local_bath_grades_transfer_samis_attributes $samisattributes
     * @return array
     */

    public static function get_by_samis_details(\local_bath_grades_transfer_samis_attributes $samisattributes) {
        global $DB;
        $objects = null;
        $records = $DB->get_records(self::$table, [
            'samisunitcode'   => $samisattributes->samisunitcode,
            'academicyear'     => $samisattributes->academicyear,
            'periodslotcode'    => $samisattributes->periodslotcode,
            'occurrence'        => $samisattributes->occurrence
        ]);
        if (!empty($records)) {
            foreach ($records as $record) {
                $objects[] = self::instantiate($record);
            }
        }
        return $objects;
    }
}