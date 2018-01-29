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

/**
 * Global Settings for the plugin
 */
defined('MOODLE_INTERNAL') || die;
if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category('bath_grades_transfer',
        get_string('pluginname', 'local_bath_grades_transfer')
    ));
    $settings = new admin_settingpage('local_bath_grades_transfer', get_string('pluginname', 'local_bath_grades_transfer'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_heading('local_bath_grades_transfer/samis_api_heading',
        get_string('samis_api_heading', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configtext('local_bath_grades_transfer/samis_api_url',
        get_string('samis_api_url', 'local_bath_grades_transfer'),
        get_string('samis_api_url_desc', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configtext('local_bath_grades_transfer/samis_api_user',
        get_string('samis_api_user', 'local_bath_grades_transfer'),
        get_string('samis_api_user_desc', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configpasswordunmask('local_bath_grades_transfer/samis_api_password',
        get_string('samis_api_password', 'local_bath_grades_transfer'),
        get_string('samis_api_password_desc', 'local_bath_grades_transfer'), ''));
    $options = array('mod_assign' => 'Assignment', 'mod_quiz' => 'Quiz');
    $settings->add(new admin_setting_configmulticheckbox('local_bath_grades_transfer/bath_grades_transfer_use',
        get_string('mod_choices', 'local_bath_grades_transfer'),
        get_string('mod_choices_desc', 'local_bath_grades_transfer'), array($options['mod_assign']), $options));
    $settings->add(new admin_setting_configcheckbox('local_bath_grades_transfer/default_mapping_only',
        get_string('default_mapping_only', 'local_bath_grades_transfer'),
        get_string('default_mapping_only_desc', 'local_bath_grades_transfer'), '1', 1, 0));

}
global $PAGE;
$PAGE->requires->js_amd_inline("
    require(['jquery','core/config'], function($,config) {
    //Create new button
    var button = \"<button id='test-samis-connection' class='btn'>Test SAMIS Connection</button>\";
    var URL = config.wwwroot + '/local/bath_grades_transfer/test_connection.php';
    $('#admin-samis_api_password').append(button);
    //Test Connection to SAMIS Web Service
    $('#test-samis-connection').click(function(e){
        e.preventDefault();
       test_connection();
    });
    var test_connection = function () {
        $.ajax({url: URL,
            timeout:1000,
            type: 'GET',
            data: {}
        }).done(function(status){
        //Show status in html
        if(status.connected){
        var status_html = '<div class=\'alert alert-success\'>
        <i class=\"fa fa-check-circle\" aria-hidden=\"true\"></i>  Connected OK </div>';
            $('#test-samis-connection').after(status_html);
        }
        else{
        var status_html = '<div class=\'alert alert-danger\'>
        <i class=\"fa fa-times\" aria-hidden=\"true\"></i>'+status.error+'</div>';
            $('#test-samis-connection').after(status_html);
        }
        });
    }
});
");
