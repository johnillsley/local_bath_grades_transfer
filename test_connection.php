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
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');
require_login();
require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/api/rest_client.php');
// Test connection to SAMIS.
$restclient = new local_bath_grades_transfer_rest_client();
$restclient->test_connection();
 $status = new stdClass();
if ($restclient->isconnected) {
    $status->connected = true;

} else {
    $status->connected = false;
}
echo json_encode($status);