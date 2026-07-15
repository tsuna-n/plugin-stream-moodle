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

namespace mod_livestream\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Reports whether an activity's HLS stream is live, and (when it is not)
 * the URL of its most recent recording so the page can offer a replay.
 *
 * Both checks run server-side so the browser never hits CORS issues
 * against the media server.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_stream_status extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the livestream activity'),
        ]);
    }

    /**
     * Checks the HLS manifest for the activity's stream key.
     *
     * @param int $cmid course module id
     * @return array live status
     */
    public static function execute(int $cmid): array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/filelib.php');

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'livestream');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/livestream:view', $context);

        $livestream = $DB->get_record('livestream', ['id' => $cm->instance], '*', MUST_EXIST);
        $config = get_config('mod_livestream');

        // Both OBS mode and Zoom-relayed streams arrive at the same HLS path,
        // so the liveness probe is identical for either type.
        $live = \mod_livestream\local\mediamtx_client::is_live($config->hlsbaseurl ?? '', $livestream->streamkey);

        // The player polls this continuously while live, so it doubles as the
        // "still watching" heartbeat for attendance. Excludes guests (no real
        // identity to record) and managers/teachers (their own preview isn't
        // an attendee) -- everyone else viewing counts.
        if (!isguestuser() && !has_capability('mod/livestream:managestream', $context)) {
            \mod_livestream\local\attendance::touch((int) $livestream->id, (int) $USER->id, $live);
        }

        // When offline, offer a replay. A manually entered recording URL always
        // wins; otherwise auto-discover the latest clip from the media server.
        $recordingurl = '';
        if (!$live) {
            if (!empty($livestream->recordingurl)) {
                $recordingurl = $livestream->recordingurl;
            } else if (!empty($config->playbackbaseurl)) {
                $recordingurl = \mod_livestream\local\mediamtx_client::latest_recording_url(
                    $config->playbackbaseurl, $livestream->streamkey);
            }
        }

        return ['live' => $live, 'recordingurl' => $recordingurl];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'live' => new external_value(PARAM_BOOL, 'Whether the stream is currently live'),
            'recordingurl' => new external_value(PARAM_URL,
                'URL of the most recent recording when offline, or empty', VALUE_DEFAULT, ''),
        ]);
    }
}
