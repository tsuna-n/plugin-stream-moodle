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
 * Checks whether the HLS stream of an OBS-mode activity is live.
 *
 * The check runs server-side so the browser never hits CORS issues
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
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'livestream');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/livestream:view', $context);

        $livestream = $DB->get_record('livestream', ['id' => $cm->instance], '*', MUST_EXIST);
        $config = get_config('mod_livestream');

        $live = false;
        if ((int) $livestream->streamtype === 0 && !empty($config->hlsbaseurl)) {
            $url = rtrim($config->hlsbaseurl, '/') . '/' . $livestream->streamkey . '/index.m3u8';
            $curl = new \curl();
            $curl->head($url, [
                'CURLOPT_TIMEOUT' => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
                'CURLOPT_FOLLOWLOCATION' => 1,
            ]);
            $httpcode = $curl->get_info()['http_code'] ?? 0;
            $live = ($httpcode >= 200 && $httpcode < 300);
        }

        return ['live' => $live];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'live' => new external_value(PARAM_BOOL, 'Whether the stream is currently live'),
        ]);
    }
}
