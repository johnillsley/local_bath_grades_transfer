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
 * Grade transfer assessment lookup class
 * This class gives access to assessment lookup info and also keeps local lookups synchronised with external data
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/assessment.php');

class local_bath_grades_transfer_assessment_lookup extends local_bath_grades_transfer_assessment
{
    /**
     * @var $table
     */
    protected static $table = 'local_bath_grades_lookup';
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
    public $mabpnam; // Anonymous Marking.
    /**
     * @var
     */
    public $expired;

    public function __construct() {
        parent::__construct();
    }

    public function is_expired() {
        return $this->expired;
    }

    /**
     * Returns the name of an assessment lookup
     * @param integer $lookupid Assessment lookup id
     * @returns string $assessmentname Name of assessment lookup
     */
    public static function get_assessment_name_by_id($lookupid) {
        global $DB;
        $assessmentname = $DB->get_field(self::$table, 'mabname', ['id' => $lookupid]);
        return $assessmentname;
    }

    /**
     * @param $id
     * @return bool|local_bath_grades_transfer_assessment|null
     */
    public static function get_by_id($id) {
        global $DB;

        if (empty($id) || empty(static::$table)) {
            return false;
        }
        $object = null;
        $record = null;

        if ($record = $DB->get_record(static::$table, ['id' => $id])) {
            $object = self::instantiate($record);
        } else {
            return false;
        }

        // Add the attributes.
        $object->attributes = new \local_bath_grades_transfer_samis_attributes(
            $record->samisunitcode,
            $record->academicyear,
            $record->periodslotcode,
            $record->mabseq);

        return $object;
    }


    /**
     * Get Assessment Lookups locally by SAMIS details - ignores occurrence codes
     * @param \local_bath_grades_transfer_samis_attributes $samisattributes
     * @return array $objects
     */
    public static function get_by_samis_details(\local_bath_grades_transfer_samis_attributes $samisattributes) {
        global $DB;
        $objects = array();
        $records = $DB->get_records(self::$table, [
            'samisunitcode' => $samisattributes->samisunitcode,
            'academicyear' => $samisattributes->academicyear,
            'periodslotcode' => $samisattributes->periodslotcode,
            'expired' => 0,
        ]);
        if (!empty($records)) {
            foreach ($records as $record) {
                $objects[] = self::instantiate($record);
            }
        }
        return $objects;
    }

    /**
     * Gets all locally stored lookups with all occurrence code variations
     * @param $samisattributes
     * @return mixed
     */
    public function get_local_assessment_details(\local_bath_grades_transfer_samis_attributes $samisattributes) {
        global $DB;
        $localassessments = $DB->get_records_sql("
            SELECT o.id, l.mapcode, l.mabseq, l.astcode, l.mabperc, l.mabname, l.mabpnam, o.mavoccur
            FROM {local_bath_grades_lookup} AS l, {local_bath_grades_lookup_occ} AS o
            WHERE o.lookupid = l.id
            AND l.expired = 0
            AND l.samisunitcode = '" . $samisattributes->samisunitcode . "'
            AND l.academicyear = '" . $samisattributes->academicyear . "'
            AND l.periodslotcode = '" . $samisattributes->periodslotcode . "'
        ");

        foreach ($localassessments as $k => $v) {
            unset($localassessments[$k]->id);
        }
        return (array)$localassessments;
    }

    /**
     * All-in-one method that deals with fetching new and expiring old lookups
     * @param integer $moodlecourseid
     * @return bool
     * @throws Exception
     */
    public function sync_remote_assessments($moodlecourseid = null) {
        global $DB;

        if (is_null($moodlecourseid)) {
            $samisattributeslist = local_bath_grades_transfer_samis_attributes::attributes_list($this->currentacademicyear);
        } else {
            $gradestransfer = new \local_bath_grades_transfer();
            $samisattributeslist = $gradestransfer->get_samis_mapping_attributes($moodlecourseid);
        }

        if (!empty($samisattributeslist)) {
            try {
                foreach ($samisattributeslist as $samisattributes) {
                    // We don't need to deal with empty arrays.
                    if ($samisattributes instanceof \local_bath_grades_transfer_samis_attributes == false) {
                        return false;
                    }
                    $remotedata = $this->samisdata->get_remote_assessment_details_rest($samisattributes);
                    $remotedata = array_pop($remotedata);
                    $localdata = $this->get_local_assessment_details($samisattributes);
                    $remoteassessments = array_map("self::lookup_transform", $remotedata); // Key fields for comparison.
                    $localassessments = array_map("self::lookup_transform", $localdata); // Key fields for comparison.

                    // Expire obsolete lookups.
                    $update = array();
                    $update['expired'] = time();
                    $expirelookups = array_diff($localassessments, $remoteassessments); // Assessments in local but not in remote.
                    foreach ($expirelookups as $k => $v) {
                        $lookupcount = $DB->get_record_sql("
                        SELECT COUNT(*) AS total, lookupid FROM {local_bath_grades_lookup_occ}
                        WHERE lookupid = ( 
                          SELECT lookupid 
                          FROM {local_bath_grades_lookup_occ}
                          WHERE id = " . $k . "
                          )
                        ");

                        // Remove the occurrence.
                        $conditions = array();
                        $conditions["id"] = $k;
                        $DB->delete_records("local_bath_grades_lookup_occ", $conditions);

                        if ($lookupcount->total == 1) {
                            // Only expire the lookup if it only has one occurrence.
                            $update['id'] = $lookupcount->lookupid;
                            $DB->update_record('local_bath_grades_lookup', $update);
                        }
                    }

                    // Add new lookups.
                    $addlookups = array_diff($remoteassessments, $localassessments); // Assessments in remote but not in local.
                    foreach ($addlookups as $k => $addlookup) {
                        $lookup = array_merge($remotedata[$k], (array)$samisattributes);
                        // Prepare lookup data - occurrence is added to different table so handled separately.
                        $occurrence = $lookup["mavoccur"];
                        unset ($lookup["mavoccur"]);
                        $lookup["samisassessmentid"] = $lookup["mapcode"] . '_' . $lookup["mabseq"];
                        $lookup["timecreated"] = time();

                        // Check if lookup record already exists (ignoring occurrence).
                        if (!$id = $DB->get_field("local_bath_grades_lookup", "id", array(
                            // Can't find lookup record - needs adding.
                            "expired" => 0, // Fix for when lookup is deleted and then re-instated.
                            "mapcode" => $lookup["mapcode"],
                            "periodslotcode" => $lookup["periodslotcode"],
                            "mabseq" => $lookup["mabseq"],
                            "mabpnam" => $lookup["mabpnam"],
                            "academicyear" => $lookup["academicyear"]))
                        ) {
                            $id = $DB->insert_record('local_bath_grades_lookup', $lookup);
                        }

                        $lookupoccurrence = array(
                            "lookupid" => $id,
                            "mavoccur" => $occurrence);

                        // Check if occurrence doesn't already exists.
                        if (!$DB->get_field("local_bath_grades_lookup_occ", "id", $lookupoccurrence)) {
                            // Can't find lookup occurence record - needs adding..
                            $DB->insert_record('local_bath_grades_lookup_occ', $lookupoccurrence);
                        }
                    }

                    // Check for updates in lookups.
                    $checkupdates = array_intersect($localassessments, $remoteassessments);
                    foreach ($checkupdates as $localkey => $checkupdate) {
                        $remotekey = array_search($checkupdate, $remoteassessments);
                        if ((array)$localdata[$localkey] != $remotedata[$remotekey]) {

                            // At least one field has changed so update.
                            $localdata[$localkey] = $remotedata[$remotekey];
                            $localdata[$localkey]["id"] = $DB->get_field("local_bath_grades_lookup_occ",
                                "lookupid", array("id" => $localkey));
                            unset($localdata[$localkey]["mavoccur"]);
                            $DB->update_record('local_bath_grades_lookup', $localdata[$localkey]);
                        }
                    }
                }
                return true;
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }

    /** Serialises the unique key fields of a mapping for easy comparison
     * Used as callback function in sync_remote_assessments
     * @param $mapping array
     * @return string
     */
    private static function lookup_transform($mapping) {
        $mapping = (array)$mapping;
        $a = array();
        $a["mapcode"] = $mapping["mapcode"];
        $a["mabseq"] = $mapping["mabseq"];
        $a["mavoccur"] = $mapping["mavoccur"];
        return serialize($a);
    }
}