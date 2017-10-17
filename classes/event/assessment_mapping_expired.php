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

namespace local_bath_grades_transfer\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for event to be triggered when a Moodle Module has been deleted.
 *
 *
 * @package    core
 * @since      Moodle 3.1
 * @copyright  2017 onwards University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_mapping_expired extends \core\event\base
{
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['action'] = 'assessment_mapping_expired';
        $this->data['target'] = 'local_bath_grades_transfer_assessment';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return 'SAMIS Assessment Mapping Expired';

    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "SAMIS Assessment Mapping " . $this->other['mapping_title'] . " is now expired";
    }
}