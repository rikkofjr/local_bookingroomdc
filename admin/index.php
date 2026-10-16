<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:managerooms', $context);

$PAGE->set_url(new moodle_url('/local/bookingroomdc/admin/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Admin Dashboard - Booking Room DC');
$PAGE->set_heading('Panel Administrasi Pemesanan Ruangan');

echo $OUTPUT->header();

// Hitung statistik sederhana
$totalrooms    = $DB->count_records('local_bookingroomdc_rooms', ['status' => 1]);
$activebookings = $DB->count_records('local_bookingroomdc_bookings', ['status' => 'confirmed']);

?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Ruangan Aktif</h5>
                <h2 class="card-text"><?php echo $totalrooms; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Booking Aktif</h5>
                <h2 class="card-text"><?php echo $activebookings; ?></h2>
            </div>
        </div>
    </div>
</div>

<h3>Menu Pengelolaan</h3>
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-building"></i> Manajemen Ruangan</h5>
                <p class="card-text">Tambah ruangan baru, ubah kapasitas, atau nonaktifkan ruangan yang sedang dipelihara.</p>
                <a href="<?php echo new moodle_url('/local/bookingroomdc/admin/manage_rooms.php'); ?>" class="btn btn-outline-primary">Kelola Ruangan &raquo;</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-list-alt"></i> Kelola Pemesanan</h5>
                <p class="card-text">Lihat daftar pemesanan dari seluruh pengguna dan lakukan pembatalan booking jika diperlukan.</p>
                <a href="<?php echo new moodle_url('/local/bookingroomdc/admin/manage_bookings.php'); ?>" class="btn btn-outline-primary">Kelola Booking &raquo;</a>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo new moodle_url('/local/bookingroomdc/index.php'); ?>" class="btn btn-secondary">&laquo; Kembali ke Dashboard Utama</a>
</div>

<?php
echo $OUTPUT->footer();