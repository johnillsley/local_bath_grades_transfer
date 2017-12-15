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
 * Grade transfer assessment class
 * This class is the parent class for access to grade transfer data
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class local_bath_grades_transfer_assessment {
    /**
     * local_bath_grades_transfer assessment constructor.
     * Gets connection to web services client
     */
    public function __construct() {
        $this->samisdata = new \local_bath_grades_transfer_external_data();
    }

    /** Get database record by Lookup ID
     * @param $id
     * @return mixed|null|boolean
     */
    protected static function get_by_id($id) {
        global $DB;

        if (empty($id) || empty(static::$table)) return false;
        $object = null;
        $record = null;

        if ($record = $DB->get_record(static::$table, ['id' => $id])) {
            $object = self::instantiate($record);
        } else {
            return false;
        }
        return $object;
    }

    /** See if an object has the class attribute present
     * @param $attribute
     * @return bool
     */
    protected function has_attribute($attribute) {
        $objectvars = get_object_vars($this);
        return array_key_exists($attribute, $objectvars);
    }

    /**
     * @param $record
     * @return local_bath_grades_transfer_assessment
     */
    public static function instantiate($record) {
        $object = new static;

        foreach ($record as $key => $value) {
            if ($object->has_attribute($key)) {
                $object->$key = $value;
            }
            // Add the attributes.
            /*
            $object->attributes = new \local_bath_grades_transfer_samis_attributes(
                $record->samisunitcode,
                $record->academicyear,
                $record->periodslotcode,
                $record->mabseq);
            */
        }
        return $object;
    }
}