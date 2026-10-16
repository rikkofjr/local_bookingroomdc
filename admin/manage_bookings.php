<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:managerooms', $context);

// Ambil parameter Pencarian, Filter, dan Pagination
$search  = optional_param('search', '', PARAM_TEXT);
$roomid  = optional_param('roomid', 0, PARAM_INT);
$status  = optional_param('status', '', PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);
$perpage = 10; // Jumlah data per halaman

$baseurl = new moodle_url('/local/bookingroomdc/admin/manage_bookings.php', [
    'search' => $search,
    'roomid' => $roomid,
    'status' => $status,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title('Kelola Booking - Admin DC');
$PAGE->set_heading('Manajemen Pemesanan Ruangan');

// Penanganan Pembatalan Booking
$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

if ($action === 'cancel' && $id) {
    require_sesskey();
    $booking = $DB->get_record('local_bookingroomdc_bookings', ['id' => $id], '*', MUST_EXIST);
    $booking->status = 'cancelled';
    $DB->update_record('local_bookingroomdc_bookings', $booking);
    redirect($baseurl, 'Booking berhasil dibatalkan oleh Admin.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// ================= BUILD DYNAMIC SQL QUERY =================
$where  = ["1 = 1"];
$params = [];

if (!empty($search)) {
    $where[] = "(" . $DB->sql_like('b.eventname', ':search1', false) . " OR " .
                     $DB->sql_like('c.fullname', ':search2', false) . " OR " .
                     $DB->sql_like('u.firstname', ':search3', false) . " OR " .
                     $DB->sql_like('u.lastname', ':search4', false) . ")";
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
    $params['search4'] = '%' . $search . '%';
}

if (!empty($roomid)) {
    $where[] = "b.roomid = :roomid";
    $params['roomid'] = $roomid;
}

if (!empty($status)) {
    $where[] = "b.status = :status";
    $params['status'] = $status;
}

$wheresql = implode(" AND ", $where);

$countsql = "SELECT COUNT(b.id) 
               FROM {local_bookingroomdc_bookings} b
               JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
               JOIN {course} c ON c.id = b.courseid
               JOIN {user} u ON u.id = b.userid
              WHERE {$wheresql}";

$totalcount = $DB->count_records_sql($countsql, $params);

$selectsql = "SELECT b.*, r.name as roomname, c.fullname as coursename, u.firstname, u.lastname 
                FROM {local_bookingroomdc_bookings} b
                JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
                JOIN {course} c ON c.id = b.courseid
                JOIN {user} u ON u.id = b.userid
               WHERE {$wheresql}
            ORDER BY b.timestart DESC";

$bookings = $DB->get_records_sql($selectsql, $params, $page * $perpage, $perpage);
$allrooms = $DB->get_records_menu('local_bookingroomdc_rooms', null, 'name ASC', 'id, name');

?>

<!-- UI FORM FILTER & PENCARIAN -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="get" action="<?php echo new moodle_url('/local/bookingroomdc/admin/manage_bookings.php'); ?>" class="form-inline">
            <div class="form-group mr-2 mb-2">
                <input type="text" name="search" class="form-control" placeholder="Cari kegiatan/pemesan..." value="<?php echo s($search); ?>">
            </div>

            <div class="form-group mr-2 mb-2">
                <select name="roomid" class="form-control">
                    <option value="">-- Semua Ruangan --</option>
                    <?php foreach ($allrooms as $rid => $rname): ?>
                        <option value="<?php echo $rid; ?>" <?php echo ($roomid == $rid) ? 'selected' : ''; ?>><?php echo s($rname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mr-2 mb-2">
                <select name="status" class="form-control">
                    <option value="">-- Semua Status --</option>
                    <option value="confirmed" <?php echo ($status === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>
            <a href="<?php echo new moodle_url('/local/bookingroomdc/admin/manage_bookings.php'); ?>" class="btn btn-secondary mb-2">Reset</a>
        </form>
    </div>
</div>

<!-- DAFTAR DATA BOOKING -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if ($bookings): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Kegiatan</th>
                            <th>Ruangan</th>
                            <th>Course LMS</th>
                            <th>Pemesan</th>
                            <th>Waktu</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): 
                            $startstr = userdate($b->timestart, '%d %b %Y, %H:%M');
                            $endstr   = userdate($b->timeend, '%d %b %Y, %H:%M');

                            $time = $startstr . '<br><small class="text-muted">s/d ' . $endstr . '</small>';                            $cancelurl = new moodle_url('/local/bookingroomdc/admin/manage_bookings.php', [
                                'action'  => 'cancel',
                                'id'      => $b->id,
                                'sesskey' => sesskey()
                            ]);

                            $courseurl = new moodle_url('/course/view.php', ['id' => $b->courseid]);
                            $userurl   = new moodle_url('/user/view.php', ['id' => $b->userid]);

                            $badgeclass = ($b->status === 'confirmed') ? 'badge-success' : 'badge-danger';
                            $btn = ($b->status === 'confirmed') 
                                ? "<a href='{$cancelurl}' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Yakin ingin membatalkan booking ini?\")'>Batalkan</a>" 
                                : "-";
                        ?>
                            <tr>
                                <td class="font-weight-bold"><?php echo s($b->eventname); ?></td>
                                <td><?php echo s($b->roomname); ?></td>
                                <td><a href="<?php echo $courseurl; ?>" target="_blank"><?php echo s($b->coursename); ?></a></td>
                                <td><a href="<?php echo $userurl; ?>" target="_blank"><?php echo s($b->firstname . ' ' . $b->lastname); ?></a></td>
                                <td><?php echo $time; ?></td>
                                <td class="text-center"><span class="badge badge-pill <?php echo $badgeclass; ?> px-3 py-2"><?php echo s($b->status); ?></span></td>
                                <td class="text-center"><?php echo $btn; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- RENDER PAGINATION MOODLE -->
            <div class="p-3">
                <?php echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl); ?>
            </div>

        <?php else: ?>
            <div class="p-4 text-center text-muted">
                Tidak ada data pemesanan yang sesuai dengan filter.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();