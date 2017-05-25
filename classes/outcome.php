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

class local_bath_grades_transfer_outcome
{
    public $id;
    public $outcomes = array('GRADE_MISSING', 'GRADE_NOT_OUT_OF_100', 'NOT_IN_SITS_STRUCTURE', 'GRADE_ALREADY_EXISTS',
        'TRANSFER_SUCCESSFUL', 'TRANSFER_FAILED');
    private static $table = 'local_bath_grades_outcome';

    public function get_all_outcomes() {
        return $this->outcomes;
    }

    public function set_outcome($outcome) {
        global $DB;
        $outcomevalue = $this->outcomes[$outcome];
    }
}