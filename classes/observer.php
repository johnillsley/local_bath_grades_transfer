<?php
/*// This file is part of Moodle - http://moodle.org/
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
defined('MOODLE_INTERNAL') || die;

/**
 * Class local_bath_grades_transfer_observer
 */
defined('MOODLE_INTERNAL') || die();

class local_bath_grades_transfer_observer
{
    /**
     * Action to take when a course module is deleted
     * Idea here is to "expire" the mapping as soon as the assign or quiz is deleted so it
     * can be re-used again
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $CFG, $DB;
        $eventdata = $event->get_data();
        $coursemoduleid = $eventdata['contextinstanceid'];
        // Expire the relevant assessment mapping too.
        $assessmentmapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($coursemoduleid);
        if ($assessmentmapping) {
            $assessmentmapping->expire_mapping(true);
            $DB->update_record('local_bath_grades_mapping', $assessmentmapping);
        }
    }
}