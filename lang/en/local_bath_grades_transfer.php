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
$string['pluginname'] = 'Bath Grades Transfer';
$string['transfergrades'] = 'Transfer Grades to SAMIS';
$string['samis_api_heading'] = 'SAMIS API Settings';
$string['samis_api_key'] = 'SAMIS API Hash';
$string['samis_api_user'] = 'SAMIS API User';
$string['samis_api_user_desc'] = 'SAMIS API User';
$string['samis_api_password'] = 'SAMIS API Password';
$string['samis_api_password_desc'] = 'SAMIS API Password';
$string['samis_api_url'] = 'SAMIS API URI';
$string['samis_api_url_desc'] = 'SAMIS API URI to connect to ';
$string['samis_api_key_desc'] = 'SAMIS API key goes here';
$string['mod_choices'] = 'Enable in the following modules';
$string['mod_choices_desc'] = 'Enable in the following modules';
$string['bath_grade_transfer_time_start_help'] = 'Optional setting.  If set, Moodle will attempt to transfer grades after this date';
$string['samis_mapping_warning'] = 'Please ensure you have contacted the SAMIS team before setting up these assessments';
$string['bath_grade_transfer_time_start'] = 'Transfer from';
$string['bath_grade_transfer_samis_lookup_id'] = 'SAMIS Assessment to link to';
$string['bath_grade_transfer_samisassessmentid_help'] = $string['samis_mapping_warning'];
$string['bath_grade_transfer_samis_assessment_expired'] = 'Assessment Link <span class="label label-warning">{$a->mab_name}</span> has been removed as it no longer exists in SAMIS';
$string['bath_grade_transfer_not_samis_default_mapping'] = '<strong>Function not available: </strong> Course is not coded with a valid SAMIS unit code';
$string['bath_grade_transfer_settings_locked'] = 'Settings have been locked as at least one grade has been transferred';
$string['bath_grade_transfer_grade_not_hundred'] = 'Maximum grade must be set to 100 if using Grade Transfer functionality';
$string['default_mapping_only'] = 'Default mapping only';
$string['default_mapping_only_desc'] = 'Consider only default mappings or all mappings?';
$string['housekeep_lookup'] = 'Housekeep Lookup (Grades Transfer)';
$string['samis_assessment_end_date_not_set'] = 'Not Set';
$string['grades_transfer_header'] = 'Grades Transfer';
$string['no_lookup_records_found'] = ' No Lookup records were found';
$string['samis_assessment_mapping_option_label'] = '{$a->mabname} ( Wt: {$a->mabperc}% ) ';
$string['bath_grade_transfer_samis_unlock_assessment'] = 'Unlock mapping';
$string['bath_grade_transfer_samis_unlock_assessment_help'] = 'Unlock mapping';
$string['unlock_warning'] = 'This action cannot be undone. Are you sure you want to do this ?';
$string['bath_grade_transfer_samis_lookup_id_help'] = 'If planning to transfer grades from Moodle to SAMIS, select the relevant assessment item from the list.  Available assessments come from SAMIS and relate to the current academic year and period slot code only.';
// EVENTS.
$string['grade_transferred'] = 'Grade Transferred';
$string['assessmentmapped'] = ' SAMIS Assessment Mapped to Moodle Activity';
// Capabilities .
$string['bath_grades_transfer:create_assessment_mapping'] = 'Create new Grades Transfer Assessment Mapping';
$string['bath_grades_transfer:unlock_assessment_mapping'] = 'Unlock Assessment Mapping';