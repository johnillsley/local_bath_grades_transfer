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
 * Created by PhpStorm.
 * User: Administrator
 * Date: 31/03/2017
 * Time: 16:22
 */
namespace local_bath_grades_transfer\task;
defined('MOODLE_INTERNAL') || die();
class housekeep_lookup extends \core\task\scheduled_task
{
    public function get_name() {
        return get_string('pluginname', 'local_bath_grades_transfer');
    }
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/assessment_lookup.php');
        $assessmentlookup = new local_bath_grades_transfer_assessment_lookup();
        $assessmentlookup->sync_remote_assessments();
    }
}