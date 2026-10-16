<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/helper.php');

require_login();
$context = context_system::instance();
require_capability('local/bookingroomdc:bookroom', $context);

$PAGE->set_url(new moodle_url('/local/bookingroomdc/book.php'));
$PAGE->set_context($context);
$PAGE->set_title('Form Booking Ruangan');
$PAGE->set_heading('Booking Room DC');

// PENANGANAN SUBMIT FORM SIMPAN BOOKING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_booking') {
    require_sesskey();

    $eventname   = required_param('eventname', PARAM_TEXT);
    $courseid    = required_param('courseid', PARAM_INT);
    $capacity    = required_param('capacity', PARAM_INT);
    $roomid      = required_param('roomid', PARAM_INT);
    $timestart   = required_param('timestart', PARAM_TEXT);
    $timeend     = required_param('timeend', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_CLEANHTML);

    $tstart = strtotime($timestart);
    $tend   = strtotime($timeend);

    if ($tend <= $tstart) {
        redirect(new moodle_url('/local/bookingroomdc/book.php'), 'Waktu selesai harus lebih besar dari waktu mulai.', null, \core\output\notification::NOTIFY_ERROR);
    }
    if (date('Y-m-d', $tstart) !== date('Y-m-d', $tend)) {
        redirect(new moodle_url('/local/bookingroomdc/book.php'), 'Waktu mulai dan waktu selesai harus pada tanggal yang sama.', null, \core\output\notification::NOTIFY_ERROR);
    }

    // Validasi Ulang Server-Side
    $rooms = \local_bookingroomdc\helper::get_rooms_with_availability($capacity, $tstart, $tend);
    if (!isset($rooms[$roomid]) || !$rooms[$roomid]->available) {
        redirect(new moodle_url('/local/bookingroomdc/book.php'), 'Maaf, ruangan yang Anda pilih tidak tersedia atau bentrok.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $booking = new stdClass();
    $booking->roomid      = $roomid;
    $booking->userid      = $USER->id;
    $booking->courseid    = $courseid;
    $booking->eventname   = $eventname;
    $booking->capacity    = $capacity;
    $booking->timestart   = $tstart;
    $booking->timeend     = $tend;
    $booking->description = $description;
    $booking->status      = 'confirmed';
    $booking->timecreated = time();

    $DB->insert_record('local_bookingroomdc_bookings', $booking);

    redirect(new moodle_url('/local/bookingroomdc/calendar.php'), 'Booking Ruangan Berhasil Disimpan!', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Ambil Seluruh Course Aktif untuk Autocomplete Dropdown
$courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname ASC', 'id, fullname');

echo $OUTPUT->header();
?>

<!-- Load Select2 CSS & JS via CDN untuk Autocomplete Search Course -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fa fa-calendar-plus-o mr-2"></i>Form Pemesanan Ruangan DC</h5>
    </div>
    <div class="card-body p-4">
        <form method="post" action="<?php echo new moodle_url('/local/bookingroomdc/book.php'); ?>" id="booking-form">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="save_booking">
            <input type="hidden" name="roomid" id="selected_room_id" value="" required>

            <!-- 1. Nama Kegiatan -->
            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Nama Kegiatan <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="text" class="form-control" name="eventname" placeholder="Contoh: Pembelajaran MDP Section Head 2026-01" required>
                </div>
            </div>

            <!-- 2. Course LMS (Searchable Autocomplete Dropdown) -->
            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Course LMS Terkait <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <select class="form-control select2-search" name="courseid" style="width: 100%;" required>
                        <option value="">-- Cari atau Pilih Course --</option>
                        <?php foreach ($courses as $cid => $cname): ?>
                            <option value="<?php echo $cid; ?>"><?php echo s($cname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. Kapasitas Peserta -->
            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Jumlah Kapasitas Peserta <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="number" class="form-control" id="input_capacity" name="capacity" min="1" placeholder="Contoh: 15" required>
                </div>
            </div>

            <!-- 4. Waktu Mulai & Selesai (Native HTML5 Datetime Input) -->
            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Waktu Mulai <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="datetime-local" class="form-control" id="input_timestart" name="timestart" required>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Waktu Selesai <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="datetime-local" class="form-control" id="input_timeend" name="timeend" required>
                </div>
            </div>

            <!-- 5. Detail Keterangan (Textarea) -->
            <div class="form-group row">
                <label class="col-md-3 col-form-label font-weight-bold">Detail Keterangan</label>
                <div class="col-md-9">
                    <textarea class="form-control" name="description" rows="3" placeholder="Tuliskan kebutuhan fasilitas tambahan jika ada..."></textarea>
                </div>
            </div>

            <hr class="my-4">

            <!-- 6. TOMBOL CEK KETERSEDIAAN RUANGAN -->
            <div class="form-group row">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <button type="button" id="btn-check-rooms" class="btn btn-info btn-lg font-weight-bold shadow-sm">
                        <i class="fa fa-search mr-2"></i> Cek Ketersediaan Ruangan
                    </button>
                    <span id="check-status-msg" class="d-block mt-2"></span>
                </div>
            </div>

            <!-- 7. SEKSI CARD PILIHAN RUANGAN -->
            <div class="form-group row my-4" id="room-selection-container" style="display: none;">
                <label class="col-md-3 col-form-label font-weight-bold">Pilih Ruangan <span class="text-danger">*</span></label>
                <div class="col-md-9" id="room-cards-wrapper">
                    <!-- Cards dirender via AJAX -->
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="form-group row mt-4">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <button type="submit" class="btn btn-primary btn-lg font-weight-bold" id="btn-submit-booking" disabled>
                        <i class="fa fa-check-circle mr-1"></i> Simpan Booking Ruangan
                    </button>
                    <a href="<?php echo new moodle_url('/local/bookingroomdc/index.php'); ?>" class="btn btn-secondary btn-lg ml-2">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .opacity-50 { opacity: 0.55; }
    .custom-room-card { border: 2px solid #0f6cbf !important; }
    .custom-room-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .selected-card {
        background-color: #e8f4ff !important;
        border-color: #28a745 !important;
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Inisialisasi Autocomplete Search pada Dropdown Course dengan Select2
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('.select2-search').select2({
            placeholder: "-- Cari nama course LMS --",
            allowClear: true
        });
    }

    // Set Default Datetime Sekarang dan +2 Jam
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('input_timestart').value = now.toISOString().slice(0, 16);

    var end = new Date(now.getTime() + (2 * 60 * 60 * 1000));
    document.getElementById('input_timeend').value = end.toISOString().slice(0, 16);

    // Handler Tombol Cek Ruangan AJAX
    var btnCheck = document.getElementById("btn-check-rooms");
    btnCheck.addEventListener("click", function() {
        var capacity  = document.getElementById("input_capacity").value || 0;
        var timestart = document.getElementById("input_timestart").value;
        var timeend   = document.getElementById("input_timeend").value;

        var statusMsg = document.getElementById("check-status-msg");
        var container = document.getElementById("room-selection-container");
        var wrapper   = document.getElementById("room-cards-wrapper");
        var btnSubmit = document.getElementById("btn-submit-booking");

        if (!timestart || !timeend) {
            statusMsg.innerHTML = '<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle"></i> Silakan isi waktu mulai dan selesai terlebih dahulu.</span>';
            return;
        }

        var tStartSec = new Date(timestart).getTime() / 1000;
        var tEndSec   = new Date(timeend).getTime() / 1000;

        if (tEndSec <= tStartSec) {
            statusMsg.innerHTML = '<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle"></i> Waktu selesai harus lebih besar dari waktu mulai.</span>';
            return;
        }

        // Pengecekan Tanggal Sama (YYYY-MM-DD)
        var startDateStr = timestart.split('T')[0];
        var endDateStr   = timeend.split('T')[0];

        if (startDateStr !== endDateStr) {
            statusMsg.innerHTML = '<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle"></i> Waktu Mulai dan Waktu Selesai harus berada ditanggal yang sama (tidak boleh beda hari).</span>';
            container.style.display = "none";
            btnSubmit.disabled = true;
            return;
        }

        statusMsg.innerHTML = '<span class="text-info"><i class="fa fa-spinner fa-spin"></i> Memeriksa ketersediaan ruangan...</span>';

        fetch(M.cfg.wwwroot + "/local/bookingroomdc/check_availability.php?capacity=" + capacity + "&timestart=" + tStartSec + "&timeend=" + tEndSec)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status === "error") {
                statusMsg.innerHTML = '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + data.message + '</span>';
                container.style.display = "none";
                btnSubmit.disabled = true;
                return;
            }

            statusMsg.innerHTML = '<span class="text-success font-weight-bold"><i class="fa fa-check-circle"></i> Silakan klik salah satu ruangan yang berstatus TERSEDIA di bawah ini.</span>';
            
            var grouped = {};
            data.rooms.forEach(function(room) {
                var loc = room.location ? room.location.trim() : "Lainnya";
                if (!grouped[loc]) grouped[loc] = [];
                grouped[loc].push(room);
            });

            var htmlOutput = "";
            for (var location in grouped) {
                var locLower = location.toLowerCase();
                var iconClass = "fa-map-marker-alt text-secondary";

                if (locLower.indexOf("lantai") !== -1 || locLower.indexOf("dc") !== -1) {
                    iconClass = "fa-building text-primary";
                } else if (locLower.indexOf("zoom") !== -1 || locLower.indexOf("online") !== -1) {
                    iconClass = "fa-video text-info";
                }

                htmlOutput += '<div class="room-location-group mb-3 p-3 border rounded bg-light">';
                htmlOutput += '<h6 class="font-weight-bold text-dark mb-3"><i class="fa ' + iconClass + ' mr-2"></i>' + location + '</h6>';
                htmlOutput += '<div class="row row-cols-1 row-cols-md-2 g-2">';

                grouped[location].forEach(function(r) {
                    var disabled = !r.available;
                    var cardClass = disabled ? "bg-white border-secondary opacity-50" : "bg-white custom-room-card";
                    var badgeClass = disabled ? "badge-danger" : "badge-success";
                    var cursorStyle = disabled ? "not-allowed" : "pointer";
                    var disableAttr = disabled ? "disabled" : "";

                    htmlOutput += '<div class="col mb-2">';
                    htmlOutput += '  <label class="w-100 m-0 card h-100 room-card-item ' + cardClass + '" id="card-room-' + r.id + '" style="cursor: ' + cursorStyle + '; transition: all 0.2s;">';
                    htmlOutput += '    <div class="card-body p-3 d-flex align-items-center justify-content-between">';
                    htmlOutput += '      <div class="d-flex align-items-center">';
                    htmlOutput += '        <input type="radio" name="temp_room_radio" value="' + r.id + '" class="mr-2" ' + disableAttr + ' onchange="selectRoom(' + r.id + ')">';
                    htmlOutput += '        <div>';
                    htmlOutput += '          <strong class="d-block text-dark">' + r.name + '</strong>';
                    htmlOutput += '          <small class="text-muted"><i class="fa fa-users mr-1"></i>Maks ' + r.capacity + ' orang</small>';
                    htmlOutput += '        </div>';
                    htmlOutput += '      </div>';
                    htmlOutput += '      <span class="badge ' + badgeClass + ' ml-2 p-2">' + r.reason_short + '</span>';
                    htmlOutput += '    </div>';
                    htmlOutput += '  </label>';
                    htmlOutput += '</div>';
                });

                htmlOutput += '</div></div>';
            }

            wrapper.innerHTML = htmlOutput;
            container.style.display = "flex";
        })
        .catch(function(err) {
            statusMsg.innerHTML = '<span class="text-danger"><i class="fa fa-times-circle"></i> Gagal memeriksa ketersediaan ruangan.</span>';
            container.style.display = "none";
        });
    });
});

function selectRoom(roomId) {
    document.getElementById("selected_room_id").value = roomId;
    document.getElementById("btn-submit-booking").disabled = false;

    // Highlight Card yang dipilih
    document.querySelectorAll('.room-card-item').forEach(function(el) {
        el.classList.remove('selected-card');
    });
    var selectedCard = document.getElementById('card-room-' + roomId);
    if (selectedCard) {
        selectedCard.classList.add('selected-card');
    }
}
</script>

<?php
echo $OUTPUT->footer();