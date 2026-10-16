<?php
require_once(__DIR__ . '/../../config.php');

$id    = required_param('id', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

// Ambil Data Booking
$booking = $DB->get_record('local_bookingroomdc_bookings', ['id' => $id], '*', MUST_EXIST);

// Validasi Token Keamanan
$expectedtoken = md5($booking->id . $booking->timestart . $CFG->passwordsaltmain);
if ($token !== $expectedtoken) {
    print_error('invalidtoken', 'local_bookingroomdc', '', 'Token tautan presensi tidak valid atau telah kadaluwarsa.');
}

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/bookingroomdc/scan.php', ['id' => $id, 'token' => $token]));
$PAGE->set_context($context);
$PAGE->set_title('Presensi Kehadiran: ' . $booking->eventname);
$PAGE->set_heading('Form Presensi Pelatihan');

$isloggedin = isloggedin() && !isguestuser();

// PENANGANAN SUBMIT PRESENSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_presence') {
    require_sesskey();

    if ($isloggedin) {
        $fullname    = fullname($USER);
        $institution = $USER->department ?: ($USER->institution ?: 'Internal LMS');
        $userid      = $USER->id;
    } else {
        $fullname    = required_param('fullname', PARAM_TEXT);
        $institution = optional_param('institution', 'Vendor/Tamu Eksternal', PARAM_TEXT);
        $userid      = 0;
    }

    $role_type = required_param('role_type', PARAM_ALPHA);

    // Cek apakah user internal sudah pernah presensi di booking ini
    if ($userid > 0) {
        $existing = $DB->get_record('local_bookingroomdc_attendances', [
            'bookingid' => $booking->id,
            'userid'    => $userid
        ]);
        if ($existing) {
            redirect(new moodle_url('/local/bookingroomdc/view_details.php', ['id' => $booking->id]), 
                'Anda sudah melakukan presensi sebelumnya pada kelas ini.', null, \core\output\notification::NOTIFY_INFO);
        }
    }

    $attendance = new stdClass();
    $attendance->bookingid    = $booking->id;
    $attendance->userid       = $userid;
    $attendance->fullname     = $fullname;
    $attendance->institution  = $institution;
    $attendance->role_type    = $role_type;
    $attendance->checkin_type = 'qr_scan';
    $attendance->timecreated  = time();

    $DB->insert_record('local_bookingroomdc_attendances', $attendance);

    redirect(new moodle_url('/local/bookingroomdc/view_details.php', ['id' => $booking->id]), 
        'Presensi berhasil dicatat! Terima kasih.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
?>

<div class="row justify-content-center my-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-1 font-weight-bold"><?php echo s($booking->eventname); ?></h4>
                <p class="mb-0 small">Form Presensi Kehadiran Ruangan Pelatihan</p>
            </div>
            <div class="card-body p-4">

                <form method="post" action="<?php echo $PAGE->url; ?>">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="action" value="submit_presence">

                    <?php if ($isloggedin): ?>
                        <!-- USER INTERNAL LMS (AUTOMATIC) -->
                        <div class="alert alert-info">
                            <strong>Pengguna LMS Terdeteksi:</strong><br>
                            <?php echo fullname($USER); ?> (<?php echo s($USER->email); ?>)
                        </div>
                    <?php else: ?>
                        <!-- TAMU / VENDOR EKSTERNAL -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" placeholder="Masukkan nama lengkap Anda" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Instansi / Perusahaan / Divisi</label>
                            <input type="text" name="institution" class="form-control" placeholder="Contoh: PT Vendor Utama / Divisi HC">
                        </div>
                    <?php endif; ?>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold">Peran Kehadiran <span class="text-danger">*</span></label>
                        <select name="role_type" class="form-control" required>
                            <option value="student">Peserta Pelatihan</option>
                            <option value="observer">Observer / Evaluator</option>
                            <option value="superior">Atasan / Manajemen</option>
                            <option value="vendor">Vendor / Pemateri Luar</option>
                            <option value="guest">Tamu Eksternal</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success btn-block btn-lg font-weight-bold">
                        Konfirmasi Kehadiran Saya
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();