<?php
namespace local_bookingroomdc\event;

defined('MOODLE_INTERNAL') || die();

class booking_created extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_bookingroomdc_bookings';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return 'Room booking created';
    }

    public function get_description() {
        $picid = $this->other['targetuserid'] ?? $this->userid;
        $roomid = $this->other['roomid'] ?? 0;
        return "The user with id '{$this->userid}' created room booking with id '{$this->objectid}' " .
               "for room id '{$roomid}' with PIC user id '{$picid}'.";
    }

    public function get_url() {
        return new \moodle_url('/local/bookingroomdc/view_details.php', ['id' => $this->objectid]);
    }
}