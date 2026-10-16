<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/bookingroomdc/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Booking Room DC');
$PAGE->set_heading('Sistem Pemesanan Ruangan DC');

echo $OUTPUT->header();

$canmanage = has_capability('local/bookingroomdc:managerooms', $context);
$canbook   = has_capability('local/bookingroomdc:bookroom', $context);

echo '<div class="jumbotron p-4 mb-4 bg-light rounded">';
echo '<h2>Selamat datang di Booking Room DC</h2>';
echo '<p class="lead">Layanan pemesanan ruangan untuk kegiatan pembelajaran offline LMS.</p>';
echo '<div class="mt-3">';

if ($canbook) {
    echo '<a href="' . new moodle_url('/local/bookingroomdc/book.php') . '" class="btn btn-primary mr-2">Buat Booking Baru</a>';
}
echo '<a href="' . new moodle_url('/local/bookingroomdc/calendar.php') . '" class="btn btn-info mr-2">Lihat Jadwal & Kalender</a>';

if ($canmanage) {
    echo '<a href="' . new moodle_url('/local/bookingroomdc/admin/index.php') . '" class="btn btn-dark">Admin Panel</a>';
}

echo '</div>';
echo '</div>';

// Menampilkan Riwayat Booking Milik User yang Sedang Login
echo '<h3>Booking Saya</h3>';
$userbookings = $DB->get_records_sql(
    "SELECT b.*, r.name as roomname, c.fullname as coursename 
       FROM {local_bookingroomdc_bookings} b
       JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
       JOIN {course} c ON c.id = b.courseid
      WHERE b.userid = :userid
   ORDER BY b.timestart DESC",
    ['userid' => $USER->id]
);

if ($userbookings) {
    echo '<table class="table table-striped align-middle"><thead><tr><th>Kegiatan</th><th>Ruangan</th><th>Course</th><th>Waktu</th><th>Status</th><th>Aksi / Dashboard</th></tr></thead><tbody>';
    foreach ($userbookings as $b) {
        $time = userdate($b->timestart) . ' - ' . userdate($b->timeend);
        $badgeclass = ($b->status === 'confirmed') ? 'badge-success' : 'badge-danger';
        
        // Link menuju Dashboard Kegiatan (view.php)
        $viewurl = new moodle_url('/local/bookingroomdc/view.php', ['id' => $b->id]);
        
        echo "<tr>
                <td><a href='{$viewurl}' class='font-weight-bold'>{$b->eventname}</a></td>
                <td>{$b->roomname}</td>
                <td>{$b->coursename}</td>
                <td>{$time}</td>
                <td><span class='badge {$badgeclass}'>{$b->status}</span></td>
                <td>
                    <a href='{$viewurl}' class='btn btn-sm btn-outline-primary'>
                        Kelola & Presensi
                    </a>
                </td>
              </tr>";
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted">Anda belum memiliki riwayat pemesanan ruangan.</p>';
}

echo $OUTPUT->footer();