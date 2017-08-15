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

class local_bath_grades_transfer_samis_attributes
{

    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     */
    public $samisunitcode;
    /**
     * @var
     */
    public $academicyear;
    /**
     * @var
     */
    public $periodslotcode;
    /**
     * @var
     */
    public $occurrence;


    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     * @param $samisunitcode
     * @param $academicyear
     * @param $periodslotcode
     * @param $occurrence
     * @param null $mab_sequence
     */
    public function __construct($samisunitcode, $academicyear, $periodslotcode, $occurrence) {

        $this->samisunitcode = $samisunitcode;
        $this->academicyear = $academicyear;
        $this->periodslotcode = $periodslotcode;
        if ($occurrence = 'All') {
            $this->occurrence = 'A';
        } else {
            $this->occurrence = $occurrence;
        }
    }

    public static function attributes_list($currentyear) {
        global $DB;

        $allunits = $DB->get_records_sql("
            SELECT DISTINCT 
              samisunitcode
            , periodslotcode
            , academicyear
            , occurrence
            FROM {local_bath_grades_lookup}
            WHERE academicyear = '" . $currentyear . "'
            AND expired IS NULL
            ");

        $samisattributeslist = array();
        foreach ($allunits as $unit) {
            $samisattributeslist[] = new self($unit->samisunitcode, $unit->academicyear, $unit->periodslotcode, $unit->occurrence);
        }
        return $samisattributeslist;
    }
}