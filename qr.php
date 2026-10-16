<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$id    = required_param('id', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

$booking = $DB->get_record('local_bookingroomdc_bookings', ['id' => $id], '*', MUST_EXIST);

// Validasi Token Keamanan
$expectedtoken = md5($booking->id . $booking->timestart . get_site_identifier());
if ($token !== $expectedtoken) {
    throw new \moodle_exception('invalidtoken', 'local_bookingroomdc', '', 'Token QR Code tidak valid.');
}

// URL Tujuan saat QR di-scan
$scanurl = new moodle_url('/local/bookingroomdc/scan.php', [
    'id'    => $booking->id,
    'token' => $token
]);

// Redirect ke API QR Generator / Render SVG QR
$qrapi = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($scanurl->out(false));

header('Content-Type: image/png');
readfile($qrapi);
exit;