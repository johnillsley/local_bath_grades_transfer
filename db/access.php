<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = array(
    'local/bath_grades_transfer:unlock_assessment_mapping' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ),
    )
);