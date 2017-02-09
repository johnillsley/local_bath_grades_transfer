<?php
namespace block_bath_samis_grades_transfer\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 09/02/2017
 * Time: 14:57
 */
class assessment_mapping_saved extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVE;
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
        return "The user with id '$this->userid' modified the assessment in SAMIS '$this->courseid'.";
    }

}