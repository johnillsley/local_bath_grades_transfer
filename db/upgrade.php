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
function xmldb_local_bath_grades_transfer_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2018011500) {

        // Define table local_scd_failed_transfer to be created.
        $table = new xmldb_table('local_bath_grades_lookup');

        // Adding fields to table local_scd_failed_transfer.
        $table->add_field('mabpnam', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'Y');

        // Bath_send_completion_data savepoint reached.
        upgrade_plugin_savepoint(true, 2018011501, 'local', 'bath_grades_transfer');
    }

    return true;
}

