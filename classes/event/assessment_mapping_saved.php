<?php
namespace local_bath_grades_transfer\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for event to be triggered when SAMIS assessment mapping attributes have been changed.
 *
 *
 * @package    core
 * @since      Moodle 3.1
 * @copyright  2017 onwards University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_mapping_saved extends \core\event\base
{
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['action'] = 'assessment_mapping_updated';
        $this->data['target'] = 'local_bath_grades_transfer_assessment';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('assessmentmapped', 'local_bath_grades_transfer');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "blah!";
        return "The user with id" . $this->userid . " has set SAMIS Assessment to " . $this->other['assessment_name'];
    }

}