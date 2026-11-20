<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:bookroom', $context);

header('Content-Type: application/json; charset=utf-8');

$now = time();

// Hitung awal & akhir hari ini menurut pengaturan waktu/timezone Moodle.
$startofday = usergetmidnight($now);
$endofday   = $startofday + DAYSECS - 1;

$sql = "SELECT b.id, b.eventname, b.timestart, b.timeend, b.status,
               r.name AS roomname, r.location AS roomlocation,
               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
          FROM {local_bookingroomdc_bookings} b
          JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
          JOIN {user} u ON u.id = b.userid
         WHERE b.status = 'confirmed'
           AND b.timestart <= :endofday
           AND b.timeend >= :startofday
      ORDER BY b.timestart ASC, b.timeend ASC";

$rows = $DB->get_records_sql($sql, [
    'startofday' => $startofday,
    'endofday'   => $endofday,
]);

$items = [];
foreach ($rows as $row) {
    $ispassed  = ($now > (int)$row->timeend);
    $isongoing = (!$ispassed && $now >= (int)$row->timestart && $now <= (int)$row->timeend);

    $timeformatted = userdate((int)$row->timestart, '%H:%M') . ' – ' . userdate((int)$row->timeend, '%H:%M');
    $roomtext = format_string($row->roomname);
    if (!empty($row->roomlocation)) {
        $roomtext .= ' (' . format_string($row->roomlocation) . ')';
    }

    $items[] = [
        'id'        => (int)$row->id,
        'time'      => $timeformatted,
        'eventname' => format_string($row->eventname),
        'room'      => $roomtext,
        'pic'       => fullname($row),
        'ispassed'  => $ispassed,
        'isongoing' => $isongoing,
    ];
}

echo json_encode([
    'server_time' => $now,
    'date_label'  => userdate($now, '%A, %d %B %Y'),
    'clock_label' => userdate($now, '%H:%M'),
    'bookings'    => $items,
]);