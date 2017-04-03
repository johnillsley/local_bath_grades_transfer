<?php

/**
 * Global Settings for the plugin
 */

/* Choice of mod types */
defined('MOODLE_INTERNAL') || die;
if ($ADMIN->fulltree) {
    $ADMIN->add('root', new admin_category('bath_grades_transfer',
        get_string('pluginname', 'local_bath_grades_transfer')
    ));
    $settings = new admin_settingpage('local_bath_grades_transfer', get_string('pluginname', 'local_bath_grades_transfer'));
    $ADMIN->add('localplugins', $settings);


    $settings->add(new admin_setting_heading('local_bath_grades_transfer/samis_api_heading', get_string('samis_api_heading', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configtext('local_bath_grades_transfer/samis_api_url', get_string('samis_api_url', 'local_bath_grades_transfer'), get_string('samis_api_url_desc', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configtext('local_bath_grades_transfer/samis_api_user', get_string('samis_api_user', 'local_bath_grades_transfer'), get_string('samis_api_user_desc', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configpasswordunmask('local_bath_grades_transfer/samis_api_password', get_string('samis_api_password', 'local_bath_grades_transfer'), get_string('samis_api_password_desc', 'local_bath_grades_transfer'), ''));
    $settings->add(new admin_setting_configtext('local_bath_grades_transfer/samis_api_key', get_string('samis_api_key', 'local_bath_grades_transfer'), get_string('samis_api_key_desc', 'local_bath_grades_transfer'), ''));
    $options = array('mod_assign' => 'Assignment', 'mod_quiz' => 'Quiz');
    $settings->add(new admin_setting_configmulticheckbox('local_bath_grades_transfer/bath_grades_transfer_use', get_string('mod_choices', 'local_bath_grades_transfer'),
        get_string('mod_choices_desc', 'local_bath_grades_transfer'), array($options['mod_assign']), $options));

}