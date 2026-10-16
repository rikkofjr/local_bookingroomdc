<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Fungsi ini dijalankan otomatis setelah tabel database plugin berhasil dibuat.
 */
function xmldb_local_bookingroomdc_install() {
    global $DB;

    $now = time();

    // Data seeder sesuai permintaan
    $rooms = [
        [
            'name'        => 'Team Work',
            'capacity'    => 20,
            'location'    => 'Gedung DC lantai 2',
            'status'      => 1, // Active
            'timecreated' => $now,
        ],
        [
            'name'        => 'Positivity',
            'capacity'    => 10,
            'location'    => 'Gedung DC lantai 1',
            'status'      => 1, // Active
            'timecreated' => $now,
        ],
        [
            'name'        => 'Compassion',
            'capacity'    => 20,
            'location'    => 'Gedung DC lantai 2',
            'status'      => 1, // Active
            'timecreated' => $now,
        ],
        [
            'name'        => 'Zoom 292',
            'capacity'    => 100,
            'location'    => 'Zoom',
            'status'      => 1, // Active
            'timecreated' => $now,
        ],
    ];

    // Masukkan data ruangan ke dalam tabel mdl_local_bookingroomdc_rooms
    foreach ($rooms as $room) {
        $DB->insert_record('local_bookingroomdc_rooms', (object)$room);
    }

    return true;
}