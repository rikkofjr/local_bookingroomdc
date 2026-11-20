<?php
namespace local_bookingroomdc\event;

defined('MOODLE_INTERNAL') || die();

class booking_cancelled extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_bookingroomdc_bookings';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return 'Room booking cancelled';
    }

    public function get_description() {
        $roomid = $this->other['roomid'] ?? 0;
        return "The user with id '{$this->userid}' cancelled room booking with id '{$this->objectid}' " .
               "for room id '{$roomid}'.";
    }

    public function get_url() {
        return new \moodle_url('/local/bookingroomdc/view_details.php', ['id' => $this->objectid]);
    }
}