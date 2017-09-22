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
function xmldb_local_bath_grades_transfer_install() {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_bath_grades_outcome');
    $outcomes = array(
        1 => 'Grade was transferred successfully',
        2 => 'Grade is missing',
        3 => 'There was an error transferring grade',
        4 => 'Grade already exists in SAMIS',
        5 => 'Grade not found in Moodle course',
        6 => 'Grade is not out of 100',
        7 => 'Grade Structure is empty',
        8 => 'Added to Queue');
    if ($dbman->table_exists($table)) {
        // Add Data.
        foreach ($outcomes as $outcome) {
            $outcomeobj = new stdClass();
            $outcomeobj->outcome = $outcome;
            $DB->insert_record('local_bath_grades_outcome', $outcomeobj);

        }
    }

}