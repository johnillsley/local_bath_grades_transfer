<?php
/**
 * Observer class to deal with events
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <ha386@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$observers = array(
    // Grade transfer queued event.
    array(
        'eventname' => 'core\event\course_module_deleted',
        'callback' => 'local_bath_grades_transfer_observer::course_module_deleted',
    )
);