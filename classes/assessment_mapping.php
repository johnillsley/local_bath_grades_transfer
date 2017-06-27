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
 * Class local_bath_grades_transfer_assessment_mapping
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
    public $timemodified;
    /**
     * @var
     */
    private $locked;
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
    public $assessment_lookup_id;
    /**
     * @var
     */
    public $coursemodule;

    /**
     * @var $activity_type
     */
    public $activity_type;
    /**
     * @var $lookup
     */
    public $lookup;

    /**
     * @var string
     */
    private static $table = 'local_bath_grades_mapping';

    /**
     * local_bath_grades_transfer_assessment_mapping constructor.
     * @param null $data
     */
    public function __construct($data = null) {
    }

    /** Get all mapping records from the table
     * @param null $lasttransfertime If provided, get only mapping based on the transfertime
     * @param bool $onlyids
     * @return array|null
     */
    public static function getAll($lasttransfertime = null, $onlyids = true) {
        global $DB;
        $conditions = "lasttransfertime IS NULL";
        $return = null;
        if (!is_null($lasttransfertime)) {
            $conditions = "lasttransfertime < " . time();

        }
        $rs = $DB->get_recordset_select(self::$table, $conditions, null, '', 'id');
        if ($rs->valid()) {
            foreach ($rs as $record) {
                if ($onlyids) {
                    $return[] = $record->id;
                } else {
                    $return[] = self::instantiate($record);
                }
            }
        }
        return $return;
    }

    /**
     * Gets a grade transfer assessment by mapping ID
     * @param $id
     * @param $get_lookup
     * @return mixed|null
     */
    public static function get($id, $get_lookup = false) {
        global $DB;
        $mapping_object = array();
        $objLookup = null;
        if ($DB->record_exists(self::$table, ['id' => $id])) {
            $record = $DB->get_record(self::$table, ['id' => $id]);
            $mapping_object = self::instantiate($record);
        }
        //Fetch the corresponding lookup too
        if ($get_lookup && isset($mapping_object->assessment_lookup_id)) {
            $objLookup = \local_bath_grades_transfer_assessment_lookup::get($mapping_object->assessment_lookup_id);
            if ($objLookup) {
                $mapping_object->lookup = $objLookup;
            }
        }
        return $mapping_object;
    }


    /**
     * Fetches a grade transfer assessment by samis_assessment_lookup_id
     * @param $lookupid
     * @return mixed|null $record
     */
    public static function get_by_lookup_id($lookupid) {
        global $DB;
        $record = null;
        if ($DB->record_exists(self::$table, ['assessment_lookup_id' => $lookupid])) {
            $record = $DB->get_record(self::$table, ['assessment_lookup_id' => $lookupid]);
        }
        return $record;
    }

    /** Check that the assessment mapping exists by lookup ID
     * @param $lookupid
     * @return bool true | false
     */
    public static function exists_by_lookup_id($lookupid) {
        global $DB;
        if ($DB->record_exists(self::$table, ['assessment_lookup_id' => $lookupid])) {
            return true;
        }
        return false;
    }

    /**
     *
     */
    public function delete_record() {
        global $DB;
        if ($DB->record_exists(self::$table)) {
            $DB->delete_records(self::$table);
        }
    }

    /**
     * Sets the data
     * @param $data
     */
    public function set_data($data) {
        if (!empty($data)) {
            //Set id
            if (isset($data->id)) {
                $this->id = $data->id; // local_bath_grades_transfer ID
            }
            //Set assessment end date
            if (isset($data->samis_assessment_end_date)) {
                $this->samis_assessment_end_date = $data->samis_assessment_end_date;
            }
            //set coursemodule
            $this->coursemodule = $data->coursemodule; //settings.php
            //set lookup id
            $this->assessment_lookup_id = $data->assessment_lookup_id;
            //set locked status
            if (isset($data->locked)) {
                $this->locked = $data->locked;
            }
            if (isset($data->activity_type)) {
                $this->activity_type = $data->activity_type;
            }
            //set modifier id
            $this->modifierid = $data->modifierid;
        }
    }

    /**
     * @param $mappingid
     * @return mixed
     */
    public function get_assessment_name($mappingid) {
        global $DB;
        //From mapping id, get lookup id, get mab_name
        $sql = "SELECT l.mab_name FROM {local_bath_grades_mapping} m JOIN {local_bath_grades_lookup} l ON m.assessment_lookup_id = l.id WHERE m.id = ? AND l.expired IS NULL  ";
        return $DB->get_field_sql($sql, [$mappingid], MUST_EXIST);
    }

    /**
     * Fetches a grade transfer assessment by Course Module ID
     * @param $cmid
     * @return bool|mixed false | $object
     */
    public static function get_by_cm_id($cmid) {
        global $DB;
        if (self::exists_by_cm_id($cmid)) {
            $record = $DB->get_record(self::$table, ['coursemodule' => $cmid]);
            $object = self::instantiate($record);
        } else {
            return false;
        }
        return $object;
    }

    /**
     * See if an transfer assessment mapping record exists by Course Module ID
     * @param $cmid
     * @return bool
     */
    private static function exists_by_cm_id($cmid) {
        global $DB;
        return $DB->record_exists(self::$table, ['coursemodule' => $cmid]);
    }

    /**
     * See if mapping exists
     * @param $id
     * @return bool
     */
    public function exists_by_id($id) {
        global $DB;
        return $DB->record_exists(self::$table, ['id' => $id]);
    }

    /**
     * @param $map_code
     * @return bool
     */
    public function exists_by_samis_assessment_id($map_code) {
        global $DB;
        return $DB->record_exists(self::$table, ['coursemodule' => $map_code]);
    }


    /**
     *
     * @return bool
     */
    public function update() {
        global $DB;
        $objAssessment = new stdClass();
        $objAssessment->id = $this->id;
        $objAssessment->coursemodule = $this->coursemodule;
        $objAssessment->activity_type = $this->activity_type;
        $objAssessment->modifierid = $this->modifierid;
        $objAssessment->timemodified = time();
        $objAssessment->assessment_lookup_id = $this->assessment_lookup_id;
        $objAssessment->samis_assessment_end_date = $this->samis_assessment_end_date;
        return $DB->update_record(self::$table, $objAssessment);
    }

    /**
     * This method looks after any redundant mapping and deals with it
     * @param $remoteAssessment
     * @param $samisAttributes
     */
    public function housekeeping_mapping($remoteAssessment, $samisAttributes) {
        //From remote mapping get the local mapping and compare the following:
        //1. If their assesment name has changed
        //2. That mapping does not exist in remote anymore
        //3. Which means we have to compare it first against the local mappings
        //4. If a mapping has been removed from remote but still exists in local
        //5. Remove it from the lookup table , remove its foreign link from the transfer table


        //Get all asessments locally based on SAMIS code, period, ac yr and mav_occur
        if (!empty($samisAttributes)) {
            $local_assessments = $this->lookup->get_by_samis_details($samisAttributes->samis_unit_code,
                $samisAttributes->academic_year,
                $samisAttributes->periodslotcode,
                $samisAttributes->occurrence);
        }
        var_dump($remoteAssessment);
        //Compare this to the remote assessment to see if anything has gone missing.
        foreach ($local_assessments as $assessment) {

        }
        var_dump($local_assessments);
        die();

    }

    /**
     * Save a mapping record in the moodle database
     */
    public function save() {
        global $DB;
        $objAssessment = new stdClass();
        $objAssessment->coursemodule = $this->coursemodule;
        $objAssessment->activity_type = $this->activity_type;
        $objAssessment->timecreated = time(); //now
        $objAssessment->modifierid = $this->modifierid;
        $objAssessment->timemodified = time(); //now
        $objAssessment->assessment_lookup_id = $this->assessment_lookup_id;
        $objAssessment->samis_assessment_end_date = $this->samis_assessment_end_date;
        $objAssessment->locked = 0;
        var_dump($objAssessment);
        $DB->insert_record(self::$table, $objAssessment);
    }

    /**
     * @param $record
     * @return local_bath_grades_transfer_assessment_mapping
     */
    private static function instantiate($record) {

        $object = new self;
        foreach ($record as $key => $value) {
            if ($object->has_attribute($key)) {
                $object->$key = $value;
            }
        }
        return $object;
    }

    /**
     * @param $attribute
     * @return bool
     */
    private function has_attribute($attribute) {
        $object_vars = get_object_vars($this);
        return array_key_exists($attribute, $object_vars);
    }

    /**
     * Set an assessment mapping to be locked in the moodle database preventing users from selecting it
     * @param $locked
     */
    public function set_locked($locked) {
        $this->locked = $locked;
    }

    /**
     *  See if transfer has been locked. Usually happens after the first grade has been transferred
     */
    public function is_locked() {
        return $this->locked;
    }

    /**
     * @param $name
     * @param $value
     */
    function __set($name, $value) {
        // TODO: Implement __set() method.
    }


}