<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/bookingroomdc/calendar.php'));
$PAGE->set_context($context);
$PAGE->set_title('Kalender Booking Ruangan');
$PAGE->set_heading('Jadwal Penggunaan Ruangan DC');

// Ambil bulan & tahun dari URL (Default: Bulan & Tahun saat ini)
$month = optional_param('month', (int)date('n'), PARAM_INT);
$year  = optional_param('year', (int)date('Y'), PARAM_INT);

// Hitung rentang waktu awal & akhir bulan untuk Query SQL
$firstdayofmonth = mktime(0, 0, 0, $month, 1, $year);
$lastdayofmonth  = mktime(23, 59, 59, $month, (int)date('t', $firstdayofmonth), $year);

// Ambil Data Booking Aktif pada Bulan Tersebut
$sql = "SELECT b.*, r.name as roomname, r.location as roomlocation, c.fullname as coursename, u.firstname, u.lastname
          FROM {local_bookingroomdc_bookings} b
          JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
          JOIN {course} c ON c.id = b.courseid
          JOIN {user} u ON u.id = b.userid
         WHERE b.status = 'confirmed'
           AND b.timestart <= :endmonth AND b.timeend >= :startmonth
      ORDER BY b.timestart ASC";

$params = [
    'startmonth' => $firstdayofmonth,
    'endmonth'   => $lastdayofmonth
];

$bookings = $DB->get_records_sql($sql, $params);

// Grouping Booking berdasarkan Tanggal Hari (YYYY-MM-DD)
$eventsbydate = [];
if ($bookings) {
    foreach ($bookings as $b) {
        $eventdate = date('Y-m-d', $b->timestart);
        $eventsbydate[$eventdate][] = $b;
    }
}

// Navigasi Bulan Lalu & Bulan Depan
$prevmonth = $month - 1;
$prevyear  = $year;
if ($prevmonth < 1) {
    $prevmonth = 12;
    $prevyear--;
}

$nextmonth = $month + 1;
$nextyear  = $year;
if ($nextmonth > 12) {
    $nextmonth = 1;
    $nextyear++;
}

$prevurl = new moodle_url('/local/bookingroomdc/calendar.php', ['month' => $prevmonth, 'year' => $prevyear]);
$nexturl = new moodle_url('/local/bookingroomdc/calendar.php', ['month' => $nextmonth, 'year' => $nextyear]);

$monthname = date('F Y', $firstdayofmonth);

echo $OUTPUT->header();
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        
        <!-- Header Navigasi Kalender -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h3 class="mb-0 font-weight-bold text-dark"><i class="fa fa-calendar text-primary mr-2"></i><?php echo $monthname; ?></h3>
            <div>
                <a href="<?php echo new moodle_url('/local/bookingroomdc/book.php'); ?>" class="btn btn-primary mr-3"><i class="fa fa-plus-circle mr-1"></i> Buat Booking Baru</a>
                <div class="btn-group">
                    <a href="<?php echo $prevurl; ?>" class="btn btn-outline-secondary"><i class="fa fa-chevron-left"></i> Bulan Lalu</a>
                    <a href="<?php echo new moodle_url('/local/bookingroomdc/calendar.php'); ?>" class="btn btn-outline-secondary">Hari Ini</a>
                    <a href="<?php echo $nexturl; ?>" class="btn btn-outline-secondary">Bulan Depan <i class="fa fa-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Grid Kalender Bulanan -->
        <div class="table-responsive">
            <table class="table table-bordered calendar-grid mb-0">
                <thead class="bg-light text-center">
                    <tr>
                        <th style="width: 14.28%;">Senin</th>
                        <th style="width: 14.28%;">Selasa</th>
                        <th style="width: 14.28%;">Rabu</th>
                        <th style="width: 14.28%;">Kamis</th>
                        <th style="width: 14.28%;">Jumat</th>
                        <th style="width: 14.28%;" class="text-danger">Sabtu</th>
                        <th style="width: 14.28%;" class="text-danger">Minggu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dayofweek = (int)date('N', $firstdayofmonth);
                    $totaldays = (int)date('t', $firstdayofmonth);
                    $currentday = 1;

                    echo '<tr>';

                    // Sel Kosong Sebelum Tanggal 1
                    for ($i = 1; $i < $dayofweek; $i++) {
                        echo '<td class="bg-light opacity-50"></td>';
                    }

                    // Loop Membuat Sel Tanggal
                    while ($currentday <= $totaldays) {
                        if ($dayofweek > 7) {
                            $dayofweek = 1;
                            echo '</tr><tr>';
                        }

                        $datekey = sprintf('%04d-%02d-%02d', $year, $month, $currentday);
                        $istoday = ($datekey === date('Y-m-d')) ? 'bg-warning-light border-warning' : '';

                        echo "<td class='calendar-day-cell {$istoday}' style='height: 120px; vertical-align: top;'>";
                        echo "<div class='font-weight-bold mb-2 text-right text-muted'>{$currentday}</div>";

                        // Tampilkan Title Ruangan & Booking pada tanggal ini
                        if (isset($eventsbydate[$datekey])) {
                            foreach ($eventsbydate[$datekey] as $ev) {
                                $loclower = strtolower($ev->roomlocation ?? '');
                                $badgecls = (strpos($loclower, 'zoom') !== false) ? 'badge-info' : 'badge-primary';
                                $timefmt  = date('H:i', $ev->timestart) . ' - ' . date('H:i', $ev->timeend);
                                
                                $payload = htmlspecialchars(json_encode([
                                    'title'    => $ev->eventname,
                                    'room'     => $ev->roomname,
                                    'location' => $ev->roomlocation,
                                    'course'   => $ev->coursename,
                                    'user'     => $ev->firstname . ' ' . $ev->lastname,
                                    'time'     => userdate($ev->timestart) . ' s/d ' . userdate($ev->timeend),
                                    'capacity' => $ev->capacity,
                                    'desc'     => $ev->description
                                ]), ENT_QUOTES, 'UTF-8');

                                echo "<div class='mb-1'>";
                                echo "<a href='javascript:void(0);' class='badge {$badgecls} p-2 text-wrap text-left d-block font-weight-normal shadow-sm room-event-badge' 
                                         onclick='showBookingDetail(this)' data-json='{$payload}'>
                                        <i class='fa fa-building mr-1'></i> <strong>".s($ev->roomname)."</strong><br>
                                        <small><i class='fa fa-clock-o mr-1'></i>{$timefmt}</small>
                                      </a>";
                                echo "</div>";
                            }
                        }

                        echo "</td>";

                        $currentday++;
                        $dayofweek++;
                    }

                    // Sel Kosong Setelah Tanggal Akhir Bulan
                    while ($dayofweek <= 7) {
                        echo '<td class="bg-light opacity-50"></td>';
                        $dayofweek++;
                    }

                    echo '</tr>';
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal Pop-up Detail Pemesanan Ruangan -->
<div class="modal fade" id="bookingDetailModal" tabindex="-1" role="dialog" aria-hidden="true" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modal-event-title">Detail Pemesanan Ruangan</h5>
                <button type="button" class="close text-white" onclick="closeBookingDetail()" aria-label="Close" style="opacity: 1; outline: none;">
                    <span aria-hidden="true" style="font-size: 1.5rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark">
                <p class="mb-2"><i class="fa fa-building text-primary mr-2"></i> <strong>Ruangan:</strong> <span id="modal-room-name"></span></p>
                <p class="mb-2"><i class="fa fa-map-marker text-danger mr-2"></i> <strong>Lokasi:</strong> <span id="modal-room-location"></span></p>
                <p class="mb-2"><i class="fa fa-book text-info mr-2"></i> <strong>Course LMS:</strong> <span id="modal-course-name"></span></p>
                <p class="mb-2"><i class="fa fa-user text-success mr-2"></i> <strong>Pemesan:</strong> <span id="modal-user-name"></span></p>
                <p class="mb-2"><i class="fa fa-clock-o text-warning mr-2"></i> <strong>Waktu:</strong> <span id="modal-time"></span></p>
                <p class="mb-2"><i class="fa fa-users text-secondary mr-2"></i> <strong>Kapasitas:</strong> <span id="modal-capacity"></span> Orang</p>
                <hr>
                <p class="mb-1 font-weight-bold">Keterangan / Detail:</p>
                <div class="p-3 bg-light rounded text-muted" id="modal-description"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary px-4" onclick="closeBookingDetail()">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .calendar-grid td { overflow: hidden; }
    .bg-warning-light { background-color: #fff9e6 !important; }
    .room-event-badge:hover {
        opacity: 0.9;
        transform: scale(1.02);
        transition: all 0.2s;
    }
</style>

<script>
function showBookingDetail(element) {
    try {
        var data = JSON.parse(element.getAttribute('data-json'));
        document.getElementById('modal-event-title').textContent = data.title || 'Detail Booking';
        document.getElementById('modal-room-name').textContent = data.room || '-';
        document.getElementById('modal-room-location').textContent = data.location || '-';
        document.getElementById('modal-course-name').textContent = data.course || '-';
        document.getElementById('modal-user-name').textContent = data.user || '-';
        document.getElementById('modal-time').textContent = data.time || '-';
        document.getElementById('modal-capacity').textContent = data.capacity || '0';
        document.getElementById('modal-description').textContent = data.desc || 'Tidak ada keterangan tambahan.';

        var modal = document.getElementById('bookingDetailModal');
        modal.classList.add('show');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    } catch(e) {
        console.error("Gagal membaca data booking:", e);
    }
}

function closeBookingDetail() {
    var modal = document.getElementById('bookingDetailModal');
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
}

// Tutup modal jika pengguna mengklik area di luar kotak modal (backdrop)
window.addEventListener('click', function(event) {
    var modal = document.getElementById('bookingDetailModal');
    if (event.target === modal) {
        closeBookingDetail();
    }
});
</script>

<?php
echo $OUTPUT->footer();