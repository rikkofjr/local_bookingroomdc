<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:bookroom', $context);

$courseid = required_param('courseid', PARAM_INT);

header('Content-Type: application/json; charset=utf-8');

if ($courseid <= 0) {
    echo json_encode([]);
    die();
}

$groups = groups_get_all_groups($courseid);
$result = [];

if ($groups) {
    foreach ($groups as $g) {
        $result[] = [
            'id'   => (int)$g->id,
            'name' => format_string($g->name),
        ];
    }
}

echo json_encode($result);