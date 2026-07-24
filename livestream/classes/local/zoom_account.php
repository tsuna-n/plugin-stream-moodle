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
 * Stores each teacher's personal Zoom Server-to-Server OAuth app credentials.
 *
 * Teachers may hold entirely separate Zoom accounts/organisations, so unlike
 * the OBS media server (one shared, site-configured service) Zoom credentials
 * are per-user rather than a single site-wide admin setting.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_account {

    /**
     * Returns a user's stored Zoom credentials, or null if they have none.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $userid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('livestream_zoom_account', ['userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Whether a user has configured their own Zoom credentials.
     *
     * @param int $userid
     * @return bool
     */
    public static function exists(int $userid): bool {
        global $DB;

        return $DB->record_exists('livestream_zoom_account', ['userid' => $userid]);
    }

    /**
     * Creates or updates a user's Zoom credentials.
     *
     * @param int $userid
     * @param string $accountid
     * @param string $clientid
     * @param string $clientsecret
     */
    public static function save(int $userid, string $accountid, string $clientid, string $clientsecret): void {
        global $DB;

        $existing = $DB->get_record('livestream_zoom_account', ['userid' => $userid]);
        $now = time();

        if ($existing) {
            $existing->accountid = $accountid;
            $existing->clientid = $clientid;
            $existing->clientsecret = $clientsecret;
            $existing->timemodified = $now;
            $DB->update_record('livestream_zoom_account', $existing);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->accountid = $accountid;
            $record->clientid = $clientid;
            $record->clientsecret = $clientsecret;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('livestream_zoom_account', $record);
        }
    }

    /**
     * Removes a user's Zoom credentials.
     *
     * @param int $userid
     */
    public static function delete(int $userid): void {
        global $DB;

        $DB->delete_records('livestream_zoom_account', ['userid' => $userid]);
    }
}
