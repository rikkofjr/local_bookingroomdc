<?php
defined('MOODLE_INTERNAL') || die();

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
              JOIN {course} c ON c.id = b.courseid
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