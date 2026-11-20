<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Mengambil data detail booking beserta informasi ruangan, course, dan pemesan.
 * 
 * @param int $bookingid
 * @return stdClass
 */
function local_bookingroomdc_get_booking_detail($bookingid) {
    global $DB;

    $sql = "SELECT b.*, r.name as roomname, r.location as roomlocation, r.capacity as roomcapacity,
                   c.fullname as coursename, u.firstname, u.lastname, u.email
              FROM {local_bookingroomdc_bookings} b
              JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
         LEFT JOIN {course} c ON c.id = b.courseid
              JOIN {user} u ON u.id = b.userid
             WHERE b.id = :id";

    return $DB->get_record_sql($sql, ['id' => $bookingid], MUST_EXIST);
}

/**
 * Validasi token keamanan QR code berdasarkan ID dan waktu mulai booking.
 * 
 * @횽 int $bookingid
 * @param int $timestart
 * @param string $token
 * @return bool
 */
function local_bookingroomdc_validate_qr_token($bookingid, $timestart, $token) {
    global $CFG;
    $expectedtoken = md5($bookingid . $timestart . get_site_identifier());
    return hash_equals($expectedtoken, $token);
}

/**
 * Menyimpan data kehadiran (presensi) ke database.
 * 
 * @param int $bookingid
 * @param int $userid
 * @param string $fullname
 * @param string $institution
 * @param string $roletype
 * @param string $checkintype ('qr_scan' atau 'manual_add')
 * @return bool|int
 */
function local_bookingroomdc_add_attendance($bookingid, $userid, $fullname, $institution, $roletype, $checkintype = 'qr_scan') {
    global $DB;

    // Jika user internal, cek apakah sudah pernah absen sebelumnya
    if ($userid > 0) {
        $existing = $DB->get_record('local_bookingroomdc_attendances', [
            'bookingid' => $bookingid,
            'userid'    => $userid
        ]);
        if ($existing) {
            return false; // Sudah pernah absen
        }
    }

    $attendance = new stdClass();
    $attendance->bookingid    = $bookingid;
    $attendance->userid       = $userid;
    $attendance->fullname     = $fullname;
    $attendance->institution  = $institution;
    $attendance->role_type    = $roletype;
    $attendance->checkin_type = $checkintype;
    $attendance->timecreated  = time();

    return $DB->insert_record('local_bookingroomdc_attendances', $attendance);
}
/**
 * Memeriksa apakah hari ini (waktu sekarang) sama dengan tanggal pelaksanaan booking.
 * 
 * @param int $timestart Timestamp waktu mulai booking
 * @return bool True jika di hari yang sama, False jika beda hari
 */
function local_bookingroomdc_is_same_day($timestart) {
    // Format tanggal 'Y-m-d' untuk dibandingkan
    $bookingdate = date('Y-m-d', $timestart);
    $today       = date('Y-m-d', time());

    return ($bookingdate === $today);
}
/**
 * Menyinkronkan event kalender Moodle untuk PIC booking dan seluruh anggota group (jika ada).
 *
 * @param stdClass $booking
 * @return void
 */
function local_bookingroomdc_sync_booking_calendar(stdClass $booking): void {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/calendar/lib.php');

    // Bersihkan event lama terlebih dahulu agar tidak terjadi duplikasi saat edit/reschedule.
    local_bookingroomdc_delete_booking_calendar((int)$booking->id);

    if (isset($booking->status) && $booking->status !== 'confirmed') {
        return;
    }

    $targetuserids = [];
    if (!empty($booking->userid)) {
        $targetuserids[(int)$booking->userid] = (int)$booking->userid;
    }

    // Ambil anggota group jika booking terikat ke course dan group tertentu.
    if (!empty($booking->courseid) && !empty($booking->groupid)) {
        $members = $DB->get_records('groups_members', ['groupid' => (int)$booking->groupid], '', 'id, userid');
        foreach ($members as $member) {
            if (!empty($member->userid)) {
                $targetuserids[(int)$member->userid] = (int)$member->userid;
            }
        }
    }

    if (empty($targetuserids)) {
        return;
    }

    $room = $DB->get_record('local_bookingroomdc_rooms', ['id' => (int)$booking->roomid], 'id, name, location');
    $roomname = $room ? format_string($room->name) : 'Ruangan DC';

    $picname = '-';
    if (!empty($booking->userid)) {
        $picuser = $DB->get_record('user', ['id' => (int)$booking->userid], 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');
        if ($picuser) {
            $picname = fullname($picuser);
        }
    }

    $duration = max(0, (int)$booking->timeend - (int)$booking->timestart);

    $descparts = [
        'Kegiatan: ' . s($booking->eventname),
        'Ruangan: ' . s($roomname),
        'PIC: ' . s($picname),
    ];
    $descriptionhtml = implode('<br>', $descparts);

    $now = time();

    foreach ($targetuserids as $uid) {
        $eventdata = (object)[
            'name'         => $booking->eventname . ' — ' . ($room ? $room->name : 'Room DC'),
            'description'  => [
                'text'   => $descriptionhtml,
                'format' => FORMAT_HTML,
            ],
            'eventtype'    => 'user',
            'userid'       => $uid,
            'courseid'     => 0,
            'groupid'      => 0,
            'modulename'   => '',
            'instance'     => 0,
            'timestart'    => (int)$booking->timestart,
            'timeduration' => $duration,
            'visible'      => 1,
        ];

        try {
            $calendarevent = \calendar_event::create($eventdata, false);
            if (!empty($calendarevent->id)) {
                $DB->insert_record('local_bookingroomdc_cal_events', (object)[
                    'bookingid'   => (int)$booking->id,
                    'userid'      => $uid,
                    'eventid'     => (int)$calendarevent->id,
                    'timecreated' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            debugging('Gagal membuat calendar event booking #' . $booking->id . ' untuk user #' . $uid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

/**
 * Menghapus seluruh event kalender Moodle yang terhubung dengan booking tertentu.
 *
 * @param int $bookingid
 * @return void
 */
function local_bookingroomdc_delete_booking_calendar(int $bookingid): void {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $mappings = $DB->get_records('local_bookingroomdc_cal_events', ['bookingid' => $bookingid]);
    if (!$mappings) {
        return;
    }

    foreach ($mappings as $map) {
        try {
            if ($DB->record_exists('event', ['id' => $map->eventid])) {
                $event = \calendar_event::load($map->eventid);
                $event->delete(true);
            }
        } catch (\Throwable $e) {
            debugging('Gagal menghapus calendar event #' . $map->eventid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    $DB->delete_records('local_bookingroomdc_cal_events', ['bookingid' => $bookingid]);
}