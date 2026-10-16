<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:managerooms', $context);

$PAGE->set_url(new moodle_url('/local/bookingroomdc/admin/manage_rooms.php'));
$PAGE->set_context($context);
$PAGE->set_title('Kelola Ruangan - Admin DC');
$PAGE->set_heading('Manajemen Ruangan Booking DC');

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

// Aksi Toggle Status
if ($action === 'toggle' && $id) {
    require_sesskey();
    $room = $DB->get_record('local_bookingroomdc_rooms', ['id' => $id], '*', MUST_EXIST);
    $room->status = $room->status ? 0 : 1;
    $DB->update_record('local_bookingroomdc_rooms', $room);
    
    $statusmsg = $room->status ? 'Ruangan berhasil diaktifkan.' : 'Ruangan berhasil dinonaktifkan.';
    redirect(new moodle_url('/local/bookingroomdc/admin/manage_rooms.php'), $statusmsg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Processing Tambah Ruangan Baru
if ($data = data_submitted()) {
    require_sesskey();
    if (!empty($data->name) && !empty($data->capacity)) {
        $newroom = (object)[
            'name'        => clean_param($data->name, PARAM_TEXT),
            'capacity'    => clean_param($data->capacity, PARAM_INT),
            'location'    => clean_param($data->location, PARAM_TEXT),
            'status'      => 1,
            'timecreated' => time(),
        ];
        $DB->insert_record('local_bookingroomdc_rooms', $newroom);
        redirect(new moodle_url('/local/bookingroomdc/admin/manage_rooms.php'), 'Ruangan baru berhasil ditambahkan!', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
?>

<div class="row mb-4">
    <!-- Card Form Tambah Ruangan -->
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="fa fa-plus-circle mr-2"></i>
                <h5 class="card-title mb-0">Tambah Ruangan Baru</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="roomname" class="font-weight-bold">Nama Ruangan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="roomname" name="name" placeholder="Contoh: Room Positivity" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="capacity" class="font-weight-bold">Kapasitas (Orang) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="capacity" name="capacity" min="1" placeholder="Contoh: 20" required>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="location" class="font-weight-bold">Lokasi / Keterangan</label>
                            <input type="text" class="form-control" id="location" name="location" placeholder="Contoh: DC Lantai 2">
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fa fa-save mr-1"></i> Simpan Ruangan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Card Daftar Ruangan -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 text-dark"><i class="fa fa-building mr-2"></i>Daftar Ruangan Terdaftar</h5>
    </div>
    <div class="card-body p-0">
        <?php
        $rooms = $DB->get_records('local_bookingroomdc_rooms');
        if ($rooms) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-hover table-striped align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Nama Ruangan</th>
                            <th>Kapasitas</th>
                            <th>Lokasi</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi Toggle</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($rooms as $r) {
                if ($r->status) {
                    $badge  = '<span class="badge badge-pill badge-success px-3 py-2"><i class="fa fa-check-circle mr-1"></i> Active</span>';
                    $btncls = 'btn-outline-danger';
                    $btntext = 'Disable';
                    $icon    = 'fa-toggle-off';
                } else {
                    $badge  = '<span class="badge badge-pill badge-secondary px-3 py-2"><i class="fa fa-minus-circle mr-1"></i> Disabled</span>';
                    $btncls = 'btn-outline-success';
                    $btntext = 'Enable';
                    $icon    = 'fa-toggle-on';
                }

                $toggleurl = new moodle_url('/local/bookingroomdc/admin/manage_rooms.php', [
                    'action'  => 'toggle', 
                    'id'      => $r->id, 
                    'sesskey' => sesskey()
                ]);

                $locationstr = !empty($r->location) ? s($r->location) : '<em class="text-muted">Tidak ada lokasi</em>';

                echo "<tr>
                        <td class='font-weight-bold align-middle'>".s($r->name)."</td>
                        <td class='align-middle'><i class='fa fa-users text-muted mr-1'></i> {$r->capacity} orang</td>
                        <td class='align-middle'>{$locationstr}</td>
                        <td class='text-center align-middle'>{$badge}</td>
                        <td class='text-center align-middle'>
                            <a href='{$toggleurl}' class='btn btn-sm {$btncls}'>
                                <i class='fa {$icon} mr-1'></i> {$btntext}
                            </a>
                        </td>
                      </tr>";
            }
            echo '</tbody></table></div>';
        } else {
            echo '<div class="p-4 text-center text-muted"><i class="fa fa-info-circle fa-2x mb-2 d-block"></i>Belum ada ruangan yang ditambahkan.</div>';
        }
        ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();