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
 * Grade transfer assessment mapping class
 * This class gives access to assessment mappings
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/assessment.php');

class local_bath_grades_transfer_assessment_mapping extends local_bath_grades_transfer_assessment
{
    /**
     * @var integer
     */
    public $id;
    /**
     * @var integer
     */
    public $timecreated;
    /**
     * @var integer
     */
    public $modifierid;
    /**
     * @var integer
     */
    public $timemodified;
    /**
     * @var boolean
     */
    public $locked;
    /**
     * @var integer
     */
    public $samisassessmentenddate;
    /**
     * @var integer
     */
    public $assessmentlookupid;
    /**
     * @var integer
     */
    public $coursemodule;
    /**
     * @var string $activitytype
     */
    public $activitytype;
    /**
     * @var integer
     */
    private $expired;
    /**
     * @var integer
     */
    public $lasttransfertime;
    /**
     * @var object $lookup
     */
    public $lookup;
    /**
     * @var string
     */
    protected static $table = 'local_bath_grades_mapping';

    public function __construct() {
        parent::__construct();
    }

    /** Get all mapping records from the table
     * @param null $lasttransfertime If provided, get only mapping based on the transfertime
     * @param bool $onlyids
     * @return array|null
     */
    public static function getAll($tasktime, $onlyids = true) {
        global $DB;
        $return = array();
        $conditions = "lasttransfertime IS NULL AND samisassessmentenddate <= $tasktime AND expired = 0
         AND assessmentlookupid > 0 ;";
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
        // Fetch the corresponding lookup too.
        if ($getlookup && isset($mappingobject->assessmentlookupid)) {
            $objlookup = \local_bath_grades_transfer_assessment_lookup::get_by_id($mappingobject->assessmentlookupid);
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
    public static function save_mapping($mapping) {
        global $DB, $USER;
        // Check compulsory fields - they must all be set.
        if (isset($mapping->coursemodule, $mapping->assessmentlookupid, $mapping->activitytype)) {
            $mapping->modifierid = $USER->id;
            $mapping->timemodified = time();
            try {
                if (empty($mapping->id)) {
                    // Insert record as id not set.
                    $mapping->timecreated = time();
                    $id = $DB->insert_record(self::$table, $mapping);
                } else {
                    // Modify existing record.
                    $id = $mapping->id;
                    $DB->update_record(self::$table, $mapping);
                }
            } catch (\Exception $e) {

            }
            return static::get_by_id($id);

        } else {
            return false; // Compulsory data missing.
        }
    }

    /**
     * Fetches a grade transfer assessment by Course Module ID
     * @param $cmid
     * @return bool|mixed false | $object
     */
    public static function get_by_cm_id($cmid) {
        global $DB;
        if ($DB->record_exists(self::$table, ['coursemodule' => $cmid, 'expired' => 0])) {
            $record = $DB->get_record(self::$table, ['coursemodule' => $cmid, 'expired' => 0]);
            $object = self::instantiate($record);
        } else {
            return false;
        }
        return $object;
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
        if (isset($this->lasttransfertime)) {
            $objassessment->lasttransfertime = $this->lasttransfertime;

        }
        // No need to change time modified if locked by cron process.
        if ($this->get_locked() == false) {
            $objassessment->timemodified = time();
        }
        $objassessment->assessmentlookupid = $this->assessmentlookupid;
        $objassessment->samisassessmentenddate = $this->samisassessmentenddate;
        $objassessment->expired = $this->expired;
        $objassessment->locked = $this->locked;

        return $DB->update_record(self::$table, $objassessment);
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