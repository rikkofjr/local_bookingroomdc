<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id    = required_param('id', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);
$mode  = optional_param('mode', '', PARAM_ALPHA);

$booking = local_bookingroomdc_get_booking_detail($id);

// 1. Validasi Token QR
if (!local_bookingroomdc_validate_qr_token($booking->id, $booking->timestart, $token)) {
    print_error('invalidtoken', 'local_bookingroomdc', '', 'Token tautan presensi tidak valid atau telah kadaluwarsa.');
}
// 2. Validasi Wajib di Hari yang Sama (Same-day check)
if (!local_bookingroomdc_is_same_day($booking->timestart)) {
    $datakegiatan = userdate($booking->timestart, '%d %B %Y');
    print_error('nottheseday', 'local_bookingroomdc', '', "Presensi untuk kegiatan ini hanya dapat dilakukan pada hari pelaksanaan, yaitu tanggal {$datakegiatan}.");
}

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/bookingroomdc/scan.php', ['id' => $id, 'token' => $token]));
$PAGE->set_context($context);
$PAGE->set_title('Presensi Kehadiran: ' . $booking->eventname);
$PAGE->set_heading('Form Presensi Pelatihan');

$isloggedin = isloggedin() && !isguestuser();

// JIKA SUDAH LOGIN LMS DAN BELUM MEMILIH MODE TAMU, OTOMATIS SET KE MODE INTERNAL
if ($isloggedin && empty($mode)) {
    $mode = 'internal';
}

// PENANGANAN SUBMIT PRESENSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_presence') {
    require_sesskey();

    $submissionmode = required_param('submissionmode', PARAM_ALPHA);

    if ($submissionmode === 'internal') {
        if (!$isloggedin) {
            redirect($PAGE->url, 'Sesi login Anda berakhir. Silakan login kembali.', null, \core\output\notification::NOTIFY_ERROR);
        }
        $fullname    = fullname($USER);
        $institution = $USER->department ?: ($USER->institution ?: 'Internal LMS');
        $userid      = $USER->id;
    } else {
        $fullname    = required_param('fullname', PARAM_TEXT);
        $institution = optional_param('institution', 'Vendor/Tamu Eksternal', PARAM_TEXT);
        $userid      = 0;
    }

    $role_type = required_param('role_type', PARAM_ALPHA);

    // Simpan presensi via fungsi helper di lib.php
    $result = local_bookingroomdc_add_attendance($booking->id, $userid, $fullname, $institution, $role_type, 'qr_scan');

    if ($result === false) {
        redirect(new moodle_url('/local/bookingroomdc/view_details.php', ['id' => $booking->id]), 
            'Anda sudah pernah melakukan presensi sebelumnya pada kelas ini.', null, \core\output\notification::NOTIFY_INFO);
    }

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
                <p class="mb-0 small">Form Presensi Kehadiran Pelatihan</p>
            </div>
            <div class="card-body p-4">

                <?php if (empty($mode)): ?>
                    <!-- TAMPILAN PILIHAN UTAMA (Hanya muncul jika BELUM login) -->
                    <div class="text-center py-3">
                        <h5 class="font-weight-bold text-dark mb-4">Anda hadir sebagai siapa?</h5>
                        
                        <?php 
                            $scanurl      = new moodle_url('/local/bookingroomdc/scan.php', ['id' => $id, 'token' => $token, 'mode' => 'internal']);
                            
                            // Jika belum login, arahkan ke form login Moodle dan bawa URL tujuan (wantsurl)
                            if (!$isloggedin) {
                                $internallink = new moodle_url('/login/index.php', ['wantsurl' => $scanurl->out_as_local_url(false)]);
                            } else {
                                $internallink = $scanurl;
                            }
                        ?>
                        <div class="row">
                            <div class="col">
                                <a href="<?php echo $internallink; ?>" class="btn btn-primary btn-lg btn-block mb-3 py-3 font-weight-bold shadow-sm">
                                    <i class="fa fa-solid fa-user-lock"></i><br/>
                                    Karyawan Internal
                                    <small class="d-block font-weight-normal mt-1 opacity-8">Login menggunakan akun LMS perusahaan</small>
                                </a>
                            </div>
                            <div class="col">
                                <!-- Tombol 2: Tamu / Vendor -->
                                <a href="<?php echo new moodle_url('/local/bookingroomdc/scan.php', ['id' => $id, 'token' => $token, 'mode' => 'guest']); ?>" class="btn btn-outline-secondary btn-lg btn-block py-3 font-weight-bold shadow-sm">
                                    <i class="fa fa-solid fa-person-chalkboard"></i><br/>
                                    Tamu / Vendor
                                    <small class="d-block font-weight-normal mt-1 text-muted">Isi data mandiri tanpa akun LMS</small>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($mode === 'internal'): ?>
                    <!-- FORM KHUSUS KARYAWAN INTERNAL (Otomatis dilempar kesini jika sudah login) -->
                    <div class="alert alert-info mb-3">
                        <strong>Status: Karyawan Internal</strong><br>
                        Masuk sebagai: <strong><?php echo fullname($USER); ?></strong> (<?php echo s($USER->email); ?>)
                    </div>

                    <form method="post" action="<?php echo $PAGE->url; ?>">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <input type="hidden" name="action" value="submit_presence">
                        <input type="hidden" name="submissionmode" value="internal">

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">Peran Kehadiran <span class="text-danger">*</span></label>
                            <select name="role_type" class="form-control" required>
                                <option value="student">Peserta Pelatihan</option>
                                <option value="observer">Observer Internal</option>
                                <option value="superior">Atasan / Manajemen</option>
                                <option value="guest">Lainnya</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success btn-block btn-lg font-weight-bold">
                            Konfirmasi Kehadiran Saya
                        </button>
                    </form>

                <?php elseif ($mode === 'guest'): ?>
                    <!-- FORM KHUSUS TAMU / VENDOR -->
                    <form method="post" action="<?php echo $PAGE->url; ?>">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <input type="hidden" name="action" value="submit_presence">
                        <input type="hidden" name="submissionmode" value="guest">

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" placeholder="Masukkan nama lengkap Anda" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Instansi / Perusahaan / Divisi <span class="text-danger">*</span></label>
                            <input type="text" name="institution" class="form-control" placeholder="Contoh: PT Vendor Utama / Konsultan Luar" required>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">Peran Kehadiran <span class="text-danger">*</span></label>
                            <select name="role_type" class="form-control" required>
                                <option value="vendor">Vendor / Pemateri Luar</option>
                                <option value="guest">Tamu Eksternal</option>
                                <option value="observer">Observer Eksternal</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success btn-block btn-lg font-weight-bold">
                            Konfirmasi Kehadiran Tamu
                        </button>
                        <a href="<?php echo new moodle_url('/local/bookingroomdc/scan.php', ['id' => $id, 'token' => $token]); ?>" class="btn btn-link btn-block mt-2">Kembali ke Pilihan Awal</a>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();