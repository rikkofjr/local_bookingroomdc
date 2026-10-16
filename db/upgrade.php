<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_bookingroomdc_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

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

    return true;
}