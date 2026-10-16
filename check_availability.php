<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/helper.php');

require_login();

$capacity  = required_param('capacity', PARAM_INT);
$timestart = required_param('timestart', PARAM_INT);
$timeend   = required_param('timeend', PARAM_INT);

// Validasi rentang waktu
if ($timeend <= $timestart) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
    ]);
    die();
}

$rooms = \local_bookingroomdc\helper::get_rooms_with_availability($capacity, $timestart, $timeend);

echo json_encode([
    'status' => 'success',
    'rooms'  => array_values($rooms)
]);