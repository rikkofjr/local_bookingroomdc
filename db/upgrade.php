<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_bookingroomdc_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();


    //Pembuatan table untuk absensi 
    if ($oldversion < 2026101600) {

        // Buat objek tabel baru local_bookingroomdc_attendances
        $table = new xmldb_table('local_bookingroomdc_attendances');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('bookingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('institution', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('role_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'student');
        $table->add_field('checkin_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'qr_scan');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_booking', XMLDB_KEY_FOREIGN, ['bookingid'], 'local_bookingroomdc_bookings', ['id']);
        $table->add_index('booking_user_idx', XMLDB_INDEX_NOTUNIQUE, ['bookingid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101600, 'local', 'bookingroomdc');
    }

    //course id menjadi tidak wajib karna bisa pilih meeting atau training
    if ($oldversion < 2026110101) {

        // Mendefinisikan tabel dan field courseid yang akan diubah
        $table = new xmldb_table('local_bookingroomdc_bookings');
        
        // Perbaikan: Ubah XMLDB_NOTNULL_FALSE menjadi false
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, false, null, '0', 'userid');

        // Jika field sudah ada, ubah, jika belum buat
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        // Simpan titik upgrade plugin ke versi 2026110100
        upgrade_plugin_savepoint(true, 2026110101, 'local', 'bookingroomdc');
    }

    // 
    if ($oldversion < 2026112000) {

        // 1. Ubah field courseid pada local_bookingroomdc_bookings menjadi opsional (NOTNULL false, DEFAULT 0).
        $bookingstable = new xmldb_table('local_bookingroomdc_bookings');
        $coursefield   = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, false, null, '0', 'userid');

        if ($dbman->field_exists($bookingstable, $coursefield)) {
            $dbman->change_field_notnull($bookingstable, $coursefield);
            $dbman->change_field_default($bookingstable, $coursefield);
        }

        // 2. Tambah field groupid pada local_bookingroomdc_bookings setelah courseid.
        $groupfield = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, false, null, '0', 'courseid');

        if (!$dbman->field_exists($bookingstable, $groupfield)) {
            $dbman->add_field($bookingstable, $groupfield);
        }

        // 3. Buat tabel local_bookingroomdc_cal_events jika belum ada.
        $caltable = new xmldb_table('local_bookingroomdc_cal_events');

        $caltable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $caltable->add_field('bookingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $caltable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $caltable->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $caltable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $caltable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $caltable->add_key('fk_booking_cal', XMLDB_KEY_FOREIGN, ['bookingid'], 'local_bookingroomdc_bookings', ['id']);

        $caltable->add_index('booking_cal_idx', XMLDB_INDEX_NOTUNIQUE, ['bookingid', 'userid']);
        $caltable->add_index('eventid_idx', XMLDB_INDEX_NOTUNIQUE, ['eventid']);

        if (!$dbman->table_exists($caltable)) {
            $dbman->create_table($caltable);
        }

        // Simpan savepoint upgrade untuk versi 2026112000.
        upgrade_plugin_savepoint(true, 2026112000, 'local', 'bookingroomdc');
    }

    return true;
}