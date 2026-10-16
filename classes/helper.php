<?php
namespace local_bookingroomdc;

defined('MOODLE_INTERNAL') || die();

class helper {

    public static function get_rooms_with_availability($capacity = 0, $timestart = 0, $timeend = 0) {
        global $DB;

        // Ambil semua ruangan aktif
        $rooms = $DB->get_records('local_bookingroomdc_rooms', ['status' => 1], 'location ASC, capacity ASC');

        if (!$rooms) {
            return [];
        }

        // Jika waktu belum dihitung, set ketersediaan standar
        if ($timestart <= 0 || $timeend <= 0) {
            foreach ($rooms as $room) {
                $room->available = true;
                $room->reason_short = 'Tersedia';
            }
            return $rooms;
        }

        // Cari ID ruangan yang terbentrok pada jam tersebut
        $sql = "SELECT DISTINCT b.roomid 
                  FROM {local_bookingroomdc_bookings} b
                 WHERE b.status = 'confirmed'
                   AND (:timestart < b.timeend AND :timeend > b.timestart)";

        $bookedroomids = $DB->get_fieldset_sql($sql, ['timestart' => $timestart, 'timeend' => $timeend]);

        foreach ($rooms as $room) {
            if (in_array($room->id, $bookedroomids)) {
                $room->available = false;
                $room->reason_short = 'Terpakai';
            } else if ($capacity > 0 && $room->capacity < $capacity) {
                $room->available = false;
                $room->reason_short = 'Kapasitas Kurang';
            } else {
                $room->available = true;
                $room->reason_short = 'Tersedia';
            }
        }

        return $rooms;
    }
}