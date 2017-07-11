<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 31/03/2017
 * Time: 16:22
 */
namespace local_bath_grades_transfer\task;
class housekeep_lookup extends \core\task\scheduled_task
{
    public function get_name() {
        return get_string('pluginname', 'local_bath_grades_transfer');
    }
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/bath_grades_transfer/lib.php');
        //local_bath_grades_transfer_scheduled_task();
        $lib = new \local_bath_grades_transfer();
        $lib->housekeep_lookup();


    }
}