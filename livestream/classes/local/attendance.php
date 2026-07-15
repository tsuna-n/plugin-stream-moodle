<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_livestream\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Session/attendance bookkeeping, driven by the same live-status probe the
 * player already polls every few seconds. A "session" is one live broadcast
 * (go-live to go-offline); attendance rows record when each viewer was first
 * and most recently seen watching it live.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance {

    /**
     * Called on every liveness probe for an activity. Opens/closes the
     * session on a live<->offline transition, and upserts the viewer's
     * attendance row while live.
     *
     * @param int $livestreamid the livestream instance id
     * @param int|null $userid the viewing user, or null/0 for a non-logged-in caller
     * @param bool $live whether the stream is currently live
     */
    public static function touch(int $livestreamid, ?int $userid, bool $live): void {
        global $DB;

        $opensession = $DB->get_record('livestream_session', ['livestreamid' => $livestreamid, 'endtime' => 0]);

        if ($live) {
            if (!$opensession) {
                $opensession = new \stdClass();
                $opensession->livestreamid = $livestreamid;
                $opensession->starttime = time();
                $opensession->endtime = 0;
                $opensession->id = $DB->insert_record('livestream_session', $opensession);
            }

            if (!empty($userid)) {
                self::record_attendance((int) $opensession->id, $userid);
            }
            return;
        }

        if ($opensession) {
            $opensession->endtime = time();
            $DB->update_record('livestream_session', $opensession);
            // Chat is scoped to a single live session -- nothing is meant to
            // survive past the broadcast it belonged to.
            $DB->delete_records('livestream_chat', ['livestreamid' => $livestreamid]);
        }
    }

    /**
     * Upserts a viewer's attendance row for a session.
     *
     * @param int $sessionid the open session id
     * @param int $userid the viewing user
     */
    private static function record_attendance(int $sessionid, int $userid): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('livestream_attendance', ['sessionid' => $sessionid, 'userid' => $userid]);
        if ($existing) {
            $existing->lastseen = $now;
            $DB->update_record('livestream_attendance', $existing);
            return;
        }

        $record = new \stdClass();
        $record->sessionid = $sessionid;
        $record->userid = $userid;
        $record->firstseen = $now;
        $record->lastseen = $now;
        try {
            $DB->insert_record('livestream_attendance', $record);
        } catch (\dml_exception $e) {
            // Two heartbeats from the same user raced to create the first
            // row for this session; the other request's insert already
            // covers this poll, nothing further to do.
            debugging('mod_livestream: attendance upsert race for session ' . $sessionid . ', user ' . $userid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
