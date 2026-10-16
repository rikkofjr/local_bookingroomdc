<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$id = required_param('id', PARAM_INT);

// Ambil Data Detail Booking beserta Ruangan, Course, dan Pemesan
$sql = "SELECT b.*, r.name as roomname, r.location as roomlocation, r.capacity as roomcapacity,
               c.fullname as coursename, u.firstname, u.lastname, u.email
          FROM {local_bookingroomdc_bookings} b
          JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
          JOIN {course} c ON c.id = b.courseid
          JOIN {user} u ON u.id = b.userid
         WHERE b.id = :id";

$booking = $DB->get_record_sql($sql, ['id' => $id], MUST_EXIST);

$context   = context_system::instance();
$canmanage = has_capability('local/bookingroomdc:managerooms', $context);
$isowner   = ($USER->id == $booking->userid);

// Hak akses mengelola presensi manual (Hanya Pembuat Booking atau Admin)
$canedit = ($isowner || $canmanage);

$baseurl = new moodle_url('/local/bookingroomdc/view_details.php', ['id' => $id]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title('Dashboard Kegiatan: ' . $booking->eventname);
$PAGE->set_heading('Dashboard & Presensi Pelatihan');

// PENANGANAN INPUT PESERTA/TAMU MANUAL OLEH PEMBUAT / ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_manual_attendance') {
    require_sesskey();

    if (!$canedit) {
        redirect($baseurl, 'Anda tidak memiliki izin untuk menambah peserta.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $fullname    = required_param('fullname', PARAM_TEXT);
    $institution = optional_param('institution', '', PARAM_TEXT);
    $role_type   = required_param('role_type', PARAM_ALPHA);

    $attendance = new stdClass();
    $attendance->bookingid    = $booking->id;
    $attendance->userid       = 0; // Guest / Vendor / Manual Entry
    $attendance->fullname     = $fullname;
    $attendance->institution  = $institution;
    $attendance->role_type    = $role_type;
    $attendance->checkin_type = 'manual_add';
    $attendance->timecreated  = time();

    $DB->insert_record('local_bookingroomdc_attendances', $attendance);

    redirect($baseurl, 'Peserta/Tamu berhasil ditambahkan ke daftar presensi!', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Generate Token Keamanan QR Code
$qrtoken = md5($booking->id . $booking->timestart . $CFG->passwordsaltmain);
$qrurl   = new moodle_url('/local/bookingroomdc/qr.php', ['id' => $booking->id, 'token' => $qrtoken]);
$scanurl = new moodle_url('/local/bookingroomdc/scan.php', ['id' => $booking->id, 'token' => $qrtoken]);

// Ambil Daftar Presensi Kehadiran
$attendances = $DB->get_records('local_bookingroomdc_attendances', ['bookingid' => $booking->id], 'timecreated DESC');
$totalattended = count($attendances);

echo $OUTPUT->header();
?>

<!-- HEADER DASHBOARD KEGIATAN -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <span class="badge badge-primary px-3 py-2 mb-2"><?php echo s($booking->status); ?></span>
                <h2 class="font-weight-bold text-dark mb-1"><?php echo s($booking->eventname); ?></h2>
                <p class="text-muted mb-2">
                    Course LMS: <a href="<?php echo new moodle_url('/course/view.php', ['id' => $booking->courseid]); ?>" target="_blank" class="font-weight-bold"><?php echo s($booking->coursename); ?></a>
                </p>
            </div>
            <div>
                <a href="<?php echo new moodle_url('/local/bookingroomdc/index.php'); ?>" class="btn btn-outline-secondary">Kembali ke Booking Saya</a>
            </div>
        </div>
        <hr>
        <div class="row text-center text-md-left">
            <div class="col-md-3 mb-2">
                <small class="text-muted d-block">Ruangan</small>
                <strong><?php echo s($booking->roomname); ?></strong>
                <small class="d-block text-muted">(<?php echo s($booking->roomlocation ?: 'Lainnya'); ?>)</small>
            </div>
            <div class="col-md-3 mb-2">
                <small class="text-muted d-block">Waktu Pelaksanaan</small>
                <strong><?php echo userdate($booking->timestart, '%d %b %Y'); ?></strong>
                <small class="d-block text-muted"><?php echo userdate($booking->timestart, '%H:%M'); ?> - <?php echo userdate($booking->timeend, '%H:%M WIB'); ?></small>
            </div>
            <div class="col-md-3 mb-2">
                <small class="text-muted d-block">Pemesan / Pemohon</small>
                <strong><?php echo s($booking->firstname . ' ' . $booking->lastname); ?></strong>
            </div>
            <div class="col-md-3 mb-2">
                <small class="text-muted d-block">Kapasitas vs Kehadiran</small>
                <strong class="text-primary font-weight-bold"><?php echo $totalattended; ?> / <?php echo $booking->capacity; ?> Orang</strong>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- KANAN / KIOS QR CODE PRESENSI -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary text-white text-center py-3">
                <h5 class="card-title mb-0">QR Code Presensi Kelas</h5>
            </div>
            <div class="card-body text-center p-4">
                <p class="text-muted small">Tampilkan QR Code ini di proyektor atau minta peserta melakukan scan melalui smartphone.</p>
                
                <div class="p-3 bg-light border rounded d-inline-block my-2">
                    <img src="<?php echo $qrurl; ?>" alt="QR Code Presensi" id="qr-image-main" style="width: 220px; height: 220px;" class="img-fluid rounded bg-white p-2 border">
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-block btn-info font-weight-bold mb-2" onclick="toggleFullscreenQR()">
                        Mode Proyektor (Layar Penuh)
                    </button>
                    <a href="<?php echo new moodle_url('/local/bookingroomdc/print_qr.php', ['id' => $booking->id]); ?>" target="_blank" class="btn btn-block btn-outline-secondary">
                        Cetak Poster / Banner QR
                    </a>
                    <div class="input-group mt-3">
                        <input type="text" id="scan-link-input" class="form-control form-control-sm" value="<?php echo $scanurl->out(false); ?>" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-sm btn-primary" type="button" onclick="copyScanLink()">Salin Link</button>
                        </div>
                    </div>

                    <script>
                    function copyScanLink() {
                        var copyText = document.getElementById("scan-link-input");
                        copyText.select();
                        copyText.setSelectionRange(0, 99999);
                        navigator.clipboard.writeText(copyText.value);
                        alert("Tautan presensi berhasil disalin! Anda dapat membagikannya ke WhatsApp/grup kelas.");
                    }
                    </script>
                </div>
            </div>
        </div>
    </div>

    <!-- KIRI / TABEL REKAP PRESENSI & TAMBAH MANUAL -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 font-weight-bold text-dark">Daftar Kehadiran Real-time</h5>
                <?php if ($canedit): ?>
                    <button type="button" class="btn btn-sm btn-success font-weight-bold" data-toggle="modal" data-target="#addGuestModal">
                        + Tambah Peserta / Tamu Manual
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($attendances): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Nama Lengkap</th>
                                    <th>Instansi / Divisi</th>
                                    <th>Ket</th>
                                    <th>Metode Check-in</th>
                                    <th>Waktu Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $att): 
                                    $rolelabel = [
                                        'student'  => '<span class="badge badge-primary px-2 py-1">Peserta</span>',
                                        'observer' => '<span class="badge badge-info px-2 py-1">Observer</span>',
                                        'superior' => '<span class="badge badge-warning px-2 py-1">Atasan</span>',
                                        'vendor'   => '<span class="badge badge-dark px-2 py-1">Vendor</span>',
                                        'guest'    => '<span class="badge badge-secondary px-2 py-1">Tamu</span>',
                                	][$att->role_type] ?? '<span class="badge badge-light">' . s($att->role_type) . '</span>';

                                    $checkinlabel = ($att->checkin_type === 'qr_scan') 
                                        ? '<small class="text-success">Self Scan QR</small>' 
                                        : '<small class="text-muted">Manual Admin/Pembuat</small>';
                                ?>
                                    <tr>
                                        <td class="font-weight-bold"><?php echo s($att->fullname); ?></td>
                                        <td><?php echo s($att->institution ?: '-'); ?></td>
                                        <td><?php echo $rolelabel; ?></td>
                                        <td><?php echo $checkinlabel; ?></td>
                                        <td><small><?php echo userdate($att->timecreated, '%H:%M:%S WIB'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        Belum ada peserta, atasan, observer, maupun tamu vendor yang melakukan presensi.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL POPUP: TAMBAH PESERTA / TAMU MANUAL -->
<?php if ($canedit): ?>
<div class="modal fade" id="addGuestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo $baseurl; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="add_manual_attendance">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Tambah Peserta / Tamu Manual</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control" placeholder="Contoh: Budi Santoso / PT Vendor Akses" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Instansi / Perusahaan / Divisi</label>
                        <input type="text" name="institution" class="form-control" placeholder="Contoh: PT Mitra Solusi / Divisi HC">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Peran dalam Kelas <span class="text-danger">*</span></label>
                        <select name="role_type" class="form-control" required>
                            <option value="student">Peserta Pelatihan</option>
                            <option value="observer">Observer / Evaluator</option>
                            <option value="superior">Atasan / Manajemen</option>
                            <option value="vendor">Vendor / Pemateri Luar</option>
                            <option value="guest">Tamu Eksternal</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Kehadiran</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL LAYAR PENUH PROYEKTOR QR CODE -->
<div id="fullscreen-qr-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #ffffff; z-index: 9999; text-align: center; padding: 40px;" onclick="toggleFullscreenQR()">
    <button type="button" class="btn btn-danger font-weight-bold" style="position: absolute; top: 20px; right: 20px;">Tutup (ESC)</button>
    <h1 class="font-weight-bold text-primary mb-2"><?php echo s($booking->eventname); ?></h1>
    <h3 class="text-dark mb-4"><?php echo s($booking->roomname); ?> — PRESENSI KEHADIRAN</h3>
    <img src="<?php echo $qrurl; ?>" style="width: 450px; height: 450px; max-width: 80vw; max-height: 60vh;" class="p-3 border rounded bg-white shadow-lg">
    <h2 class="font-weight-bold text-dark mt-4">SCAN QR CODE DENGAN SMARTPHONE ANDA</h2>
    <p class="text-muted">Gunakan kamera HP / Browser untuk melakukan presensi kehadiran kelas.</p>
</div>

<script>
function toggleFullscreenQR() {
    var overlay = document.getElementById('fullscreen-qr-overlay');
    if (overlay.style.display === 'none' || overlay.style.display === '') {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        document.getElementById('fullscreen-qr-overlay').style.display = 'none';
    }
});
</script>

<?php
echo $OUTPUT->footer();