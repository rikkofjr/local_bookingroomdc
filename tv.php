<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:bookroom', $context);

$PAGE->set_url(new moodle_url('/local/bookingroomdc/tv.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Jadwal Ruangan - Display TV');
$PAGE->set_heading('Jadwal Ruangan');

$now = time();
$startofday = usergetmidnight($now);
$endofday   = $startofday + DAYSECS - 1;

$sql = "SELECT b.id, b.eventname, b.timestart, b.timeend, b.status,
               r.name AS roomname, r.location AS roomlocation,
               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
          FROM {local_bookingroomdc_bookings} b
          JOIN {local_bookingroomdc_rooms} r ON r.id = b.roomid
          JOIN {user} u ON u.id = b.userid
         WHERE b.status = 'confirmed'
           AND b.timestart <= :endofday
           AND b.timeend >= :startofday
      ORDER BY b.timestart ASC, b.timeend ASC";

$bookings = $DB->get_records_sql($sql, [
    'startofday' => $startofday,
    'endofday'   => $endofday,
]);

$datelabel  = userdate($now, '%A, %d %B %Y');
$clocklabel = userdate($now, '%H:%M');

echo $OUTPUT->header();
?>

<style>
    body, .page-content, #region-main, #region-main-box {
        background-color: #0b1320 !important;
        color: #f1f5f9 !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
    }
    .tv-wrapper {
        min-height: 100vh;
        padding: 28px 36px;
        background: linear-gradient(180deg, #0f172a 0%, #090f1a 100%);
    }
    .tv-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        padding-bottom: 18px;
        margin-bottom: 24px;
    }
    .tv-title {
        font-size: 2.1rem;
        font-weight: 700;
        margin: 0;
        color: #ffffff;
    }
    .tv-date {
        font-size: 1.05rem;
        color: #94a3b8;
        margin-top: 4px;
    }
    .tv-right-box {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .tv-clock {
        font-size: 3.1rem;
        font-weight: 800;
        letter-spacing: 1px;
        color: #ffffff;
        line-height: 1;
    }
    .tv-fullscreen-btn {
        background: rgba(255, 255, 255, 0.08);
        color: #e2e8f0;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.9rem;
        cursor: pointer;
    }
    .tv-fullscreen-btn:hover {
        background: rgba(255, 255, 255, 0.16);
    }
    .tv-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    .tv-table thead th {
        color: #94a3b8;
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 10px 18px;
        border: none;
    }
    .tv-table tbody tr {
        background: rgba(30, 41, 59, 0.72);
        transition: opacity 0.3s ease, filter 0.3s ease;
    }
    .tv-table tbody tr td {
        padding: 18px;
        font-size: 1.25rem;
        vertical-align: middle;
        border-top: 1px solid rgba(148, 163, 184, 0.12);
        border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    }
    .tv-table tbody tr td:first-child {
        border-left: 1px solid rgba(148, 163, 184, 0.12);
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
        font-weight: 700;
        white-space: nowrap;
        color: #e2e8f0;
    }
    .tv-table tbody tr td:last-child {
        border-right: 1px solid rgba(148, 163, 184, 0.12);
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    .tv-row-ongoing {
        background: rgba(22, 101, 52, 0.45) !important;
    }
    .tv-row-ongoing td {
        border-color: rgba(74, 222, 128, 0.35) !important;
        color: #ffffff !important;
        font-weight: 600;
    }
    .tv-row-passed {
        opacity: 0.38;
        filter: blur(0.65px);
    }
    .tv-empty {
        text-align: center;
        padding: 64px 20px;
        font-size: 1.3rem;
        color: #94a3b8;
        background: rgba(30, 41, 59, 0.45);
        border-radius: 12px;
    }
    .tv-footer {
        margin-top: 24px;
        font-size: 0.85rem;
        color: #64748b;
    }
</style>

<div class="tv-wrapper" id="tv-screen">
    <div class="tv-header">
        <div>
            <h1 class="tv-title">Jadwal Penggunaan Ruangan</h1>
            <div class="tv-date" id="tv-date-label"><?php echo s($datelabel); ?></div>
        </div>
        <div class="tv-right-box">
            <div class="tv-clock" id="tv-clock-label"><?php echo s($clocklabel); ?></div>
            <button type="button" class="tv-fullscreen-btn" onclick="toggleTvFullscreen()"><i class="fa-solid fa-expand"></i></button>
        </div>
    </div>

    <table class="tv-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Kegiatan</th>
                <th>Ruang</th>
                <th>PIC</th>
            </tr>
        </thead>
        <tbody id="tv-bookings-body">
            <?php if ($bookings): ?>
                <?php foreach ($bookings as $b):
                    $ispassed  = ($now > (int)$b->timeend);
                    $isongoing = (!$ispassed && $now >= (int)$b->timestart && $now <= (int)$b->timeend);
                    $rowclass  = $ispassed ? 'tv-row-passed' : ($isongoing ? 'tv-row-ongoing' : '');
                    $timefmt   = userdate((int)$b->timestart, '%H:%M') . ' – ' . userdate((int)$b->timeend, '%H:%M');
                    $roomtext  = format_string($b->roomname);
                    if (!empty($b->roomlocation)) {
                        $roomtext .= ' (' . format_string($b->roomlocation) . ')';
                    }
                ?>
                    <tr class="<?php echo $rowclass; ?>">
                        <td><?php echo s($timefmt); ?></td>
                        <td><?php echo s(format_string($b->eventname)); ?></td>
                        <td><?php echo s($roomtext); ?></td>
                        <td><?php echo s(fullname($b)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="tv-empty">Tidak ada jadwal penggunaan ruangan untuk hari ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="tv-footer">
        Diperbarui otomatis setiap 60 detik · Waktu mengikuti server Moodle
    </div>
</div>

<script>
function toggleTvFullscreen() {
    var elem = document.getElementById('tv-screen') || document.documentElement;
    if (!document.fullscreenElement) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function renderTvBookings(data) {
    if (data.date_label) {
        document.getElementById('tv-date-label').textContent = data.date_label;
    }
    if (data.clock_label) {
        document.getElementById('tv-clock-label').textContent = data.clock_label;
    }

    var tbody = document.getElementById('tv-bookings-body');
    var list = Array.isArray(data.bookings) ? data.bookings : [];

    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="tv-empty">Tidak ada jadwal penggunaan ruangan untuk hari ini.</td></tr>';
        return;
    }

    var html = '';
    list.forEach(function(item) {
        var rowClass = item.ispassed ? 'tv-row-passed' : (item.isongoing ? 'tv-row-ongoing' : '');
        html += '<tr class="' + rowClass + '">';
        html += '<td>' + escapeHtml(item.time) + '</td>';
        html += '<td>' + escapeHtml(item.eventname) + '</td>';
        html += '<td>' + escapeHtml(item.room) + '</td>';
        html += '<td>' + escapeHtml(item.pic) + '</td>';
        html += '</tr>';
    });

    tbody.innerHTML = html;
}

function refreshTvSchedule() {
    fetch(M.cfg.wwwroot + '/local/bookingroomdc/tv_data.php', { cache: 'no-store' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            renderTvBookings(data);
        })
        .catch(function() {
            // Abaikan error jaringan sesaat agar tampilan TV tetap bertahan.
        });
}

setInterval(refreshTvSchedule, 60000);
</script>

<?php
echo $OUTPUT->footer();