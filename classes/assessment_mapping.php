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
    public $samisassessmentenddate;
    /**
     * @var
     */
    public $samisassessmentid;
    /**
     * @var
     */
    public $assessmentlookupid;
    /**
     * @var
     */
    public $coursemodule;

    /**
     * @var $activitytype
     */
    public $activitytype;
    /**
     * @var $lookup
     */
    public $lookup;
    private $expired;

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
        $conditions = null;
        $rs = $DB->get_recordset_select(self::$table, $conditions, null, '', 'id');
        if ($rs->valid()) {
            foreach ($rs as $record) {
                if ($onlyids) {
                    $return[] = $record->id;
                } else {
                    if (!$record->expired) {
                        // Dont show expired lookups.
                        $return[] = self::instantiate($record);
                    }

                }
            }
        }
        return $return;
    }

    /**
     * Gets a grade transfer assessment by mapping ID
     * @param $id
     * @param $getlookup
     * @return mixed|null
     */
    public static function get($id, $getlookup = false) {
        global $DB;
        $mappingobject = array();
        $objlookup = null;
        if ($DB->record_exists(self::$table, ['id' => $id])) {
            $record = $DB->get_record(self::$table, ['id' => $id]);
            $mappingobject = self::instantiate($record);
        }
        //Fetch the corresponding lookup too
        if ($getlookup && isset($mappingobject->assessmentlookupid)) {
            $objlookup = \local_bath_grades_transfer_assessment_lookup::get($mappingobject->assessmentlookupid);
            if ($objlookup) {
                $mappingobject->lookup = $objlookup;
            }
        }
        return $mappingobject;
    }


    /**
     * Fetches a grade transfer assessment by samis_assessmentlookupid
     * @param $lookupid
     * @return mixed|null $record
     */
    public static function get_by_lookup_id($lookupid, $cmid = null) {
        global $DB;
        $record = null;
        if ($DB->record_exists(self::$table, ['assessmentlookupid' => $lookupid, 'expired' => 0])) {
            if (!is_null($cmid)) {
                $record = $DB->get_record(self::$table,
                    ['assessmentlookupid' => $lookupid, 'coursemodule' => $cmid, 'expired' => 0]);
            } else {
                $record = $DB->get_record(self::$table, ['assessmentlookupid' => $lookupid, 'expired' => 0]);
            }

        }
        return $record;
    }

    /** Check that the assessment mapping exists by lookup ID
     * @param $lookupid
     * @return bool true | false
     */
    public static function exists_by_lookup_id($lookupid) {
        global $DB;
        if ($DB->record_exists(self::$table, ['assessmentlookupid' => $lookupid, 'expired' => 0])) {
            return true;
        }
        return false;
    }

    public function expire_mapping($expireflag) {
        $this->expired = $expireflag;
    }

    public function get_expired() {
        return $this->expired;
    }

    /**
     * Sets the data
     * @param $data
     */
    public function set_data($data) {
        if (!empty($data)) {
            // Set id.
            if (isset($data->id)) {
                $this->id = $data->id; // Local_bath_grades_transfer ID.
            }
            // Set assessment end date.
            if (isset($data->samisassessmentenddate)) {
                $this->samisassessmentenddate = $data->samisassessmentenddate;
            }
            // Set course module.
            $this->coursemodule = $data->coursemodule; // Settings.php
            // Set lookup id.
            $this->assessmentlookupid = $data->assessmentlookupid;
            // Set locked status.
            if (isset($data->locked)) {
                $this->locked = $data->locked;
            }
            // Set activity type.
            if (isset($data->activitytype)) {
                $this->activitytype = $data->activitytype;
            }
            // Set expired status.
            if (isset($data->expired)) {
                $this->expired = $data->expired;
            }
            // Set modifier id.
            $this->modifierid = $data->modifierid;
        }
    }

    /**
     * @param $mappingid
     * @return mixed
     */
    public function get_assessment_name($mappingid) {
        global $DB;
        // From mapping id, get lookup id, get mab_name.
        $sql = "SELECT l.mab_name FROM {local_bath_grades_mapping} m
JOIN {local_bath_grades_lookup} l ON m.assessmentlookupid = l.id WHERE m.id = ? AND l.expired IS NULL  ";
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
            $record = $DB->get_record(self::$table, ['coursemodule' => $cmid, 'expired' => 0]);
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
        return $DB->record_exists(self::$table, ['coursemodule' => $cmid, 'expired' => 0]);
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
     * @param $mapcode
     * @return bool
     */
    public function exists_by_samisassessmentid($mapcode) {
        global $DB;
        return $DB->record_exists(self::$table, ['coursemodule' => $mapcode]);
    }


    /**
     *
     * @return bool
     */
    public function update() {
        global $DB;

        $objassessment = new stdClass();
        $objassessment->id = $this->id;
        $objassessment->coursemodule = $this->coursemodule;
        $objassessment->activitytype = $this->activitytype;
        $objassessment->modifierid = $this->modifierid;
        $objassessment->timemodified = time();
        $objassessment->assessmentlookupid = $this->assessmentlookupid;
        $objassessment->samisassessmentenddate = $this->samisassessmentenddate;
        $objassessment->expired = $this->expired;
        $objassessment->locked = $this->locked;
        return $DB->update_record(self::$table, $objassessment);
    }

    /**
     * This method looks after any redundant mapping and deals with it
     * @param $remoteassessment
     * @param $samisattributes
     */
    public function housekeeping_mapping($remoteassessment, $samisattributes) {
        // From remote mapping get the local mapping and compare the following:.
        // 1. If their assessment name has changed.
        // 2. That mapping does not exist in remote anymore.
        // 3. Which means we have to compare it first against the local mappings.
        // 4. If a mapping has been removed from remote but still exists in local.
        // 5. Remove it from the lookup table , remove its foreign link from the transfer table.


        // Get all asessments locally based on SAMIS code, period, ac yr and mav_occur.
        if (!empty($samisattributes)) {
            $localassessments = $this->lookup->get_by_samis_details($samisattributes->samisunitcode,
                $samisattributes->academicyear,
                $samisattributes->periodslotcode,
                $samisattributes->occurrence);
        }
        var_dump($remoteassessment);
        //Compare this to the remote assessment to see if anything has gone missing.
        foreach ($localassessments as $assessment) {

        }
        var_dump($localassessments);
        die();

    }

    /**
     * Save a mapping record in the moodle database
     */
    public function save() {
        global $DB;
        $objassessment = new stdClass();
        $objassessment->coursemodule = $this->coursemodule;
        $objassessment->activitytype = $this->activitytype;
        $objassessment->timecreated = time(); // Now.
        $objassessment->modifierid = $this->modifierid;
        $objassessment->timemodified = time(); // Now.
        $objassessment->assessmentlookupid = $this->assessmentlookupid;
        $objassessment->samisassessmentenddate = $this->samisassessmentenddate;
        $objassessment->locked = 0;
        if (isset($this->locked)) {
            $objassessment->locked = $this->locked;
        }
        $DB->insert_record(self::$table, $objassessment);
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
        $objectvars = get_object_vars($this);
        return array_key_exists($attribute, $objectvars);
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
    public function get_locked() {
        return $this->locked;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value) {
        // TODO: Implement __set() method.
    }


}