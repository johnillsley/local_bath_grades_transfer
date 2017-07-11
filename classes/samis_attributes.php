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
    public $samis_code;
    /**
     * @var
     */
    public $academic_year;
    /**
     * @var
     */
    public $period_code;
    /**
     * @var
     */
    public $occurrence;


    /**
     * local_bath_grades_transfer_samis_attributes constructor.
     * @param $samis_code
     * @param $academic_year
     * @param $period_code
     * @param $occurrence
     * @param null $mab_sequence
     */
    public function __construct($samis_code, $academic_year, $period_code, $occurrence) {
        $this->samis_code = $samis_code;
        $this->academic_year = str_replace('/','-',$academic_year);
        $this->period_code = $period_code;

        if ($occurrence = 'All') {
            $this->occurrence = 'A';
        } else {
            $this->occurrence = $occurrence;
        }
    }


}