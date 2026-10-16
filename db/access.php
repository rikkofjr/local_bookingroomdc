<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/bookingroomdc:managerooms' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PREVENT,
        ],
    ],
    'local/bookingroomdc:bookroom' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];