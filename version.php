<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 09/02/2017
 * Time: 11:23
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015111600;        // The current module version (YYYYMMDDXX)
$plugin->requires  = 2015111000;        // Requires this Moodle version.
$plugin->component = 'local_bath_grades_transfer';
$plugin->cron      = 60;                // Give as a chance every minute.