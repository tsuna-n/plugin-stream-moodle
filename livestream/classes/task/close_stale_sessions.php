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

namespace mod_livestream\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Safety net for closing "stuck open" broadcast sessions.
 *
 * Sessions normally close themselves the moment any viewer's status poll
 * observes the stream has gone offline (see get_stream_status.php). This
 * task only matters for the edge case where every viewer closes their tab
 * before one more poll happens to notice -- without it, that session (and
 * its chat) would stay open indefinitely.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_stale_sessions extends \core\task\scheduled_task {

    /**
     * Returns the task name shown in the scheduled tasks admin screen.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_close_stale_sessions', 'mod_livestream');
    }

    /**
     * Re-probes every still-open session directly and closes it if the
     * stream is genuinely no longer live.
     */
    public function execute(): void {
        global $DB;

        $config = get_config('mod_livestream');
        if (empty($config->hlsbaseurl)) {
            return;
        }

        $sql = "SELECT s.id, s.livestreamid, l.streamkey
                  FROM {livestream_session} s
                  JOIN {livestream} l ON l.id = s.livestreamid
                 WHERE s.endtime = 0";
        $opensessions = $DB->get_records_sql($sql);

        foreach ($opensessions as $session) {
            if (\mod_livestream\local\mediamtx_client::is_live($config->hlsbaseurl, $session->streamkey)) {
                continue;
            }
            $DB->set_field('livestream_session', 'endtime', time(), ['id' => $session->id]);
            $DB->delete_records('livestream_chat', ['livestreamid' => $session->livestreamid]);
        }
    }
}
