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

class local_bath_grades_transfer_samis_attributes
{

    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     */
    public $samis_unit_code;
    /**
     * @var
     */
    public $academic_year;
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
     * @param $samis_unit_code
     * @param $academic_year
     * @param $periodslotcode
     * @param $occurrence
     * @param null $mab_sequence
     */
    public function __construct( $samis_unit_code, $academic_year, $periodslotcode, $occurrence ) {

        $this->samis_unit_code  = $samis_unit_code;
        $this->academic_year    = $academic_year;
        $this->periodslotcode   = $periodslotcode;

        //$this->samis_code = $samis_code;
        //$this->period_code = $period_code;

        if ($occurrence = 'All') {
            $this->occurrence = 'A';
        } else {
            $this->occurrence = $occurrence;
        }
    }

    static function attributes_list( $current_year ) {
        global $DB;

        $all_units = $DB->get_records_sql( "
            SELECT DISTINCT 
              samis_unit_code
            , periodslotcode
            , academic_year
            , occurrence
            FROM {local_bath_grades_lookup}
            WHERE academic_year = '".$current_year."'
            AND expired IS NULL
            ");

        $samis_attributes_list = array();
        foreach( $all_units as $unit ) {
            $samis_attributes_list[] = new self( $unit->samis_unit_code, $unit->academic_year, $unit->periodslotcode, $unit->occurrence);
        }
        return $samis_attributes_list;
    }
}