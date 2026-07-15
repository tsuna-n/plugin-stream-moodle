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
 * Reports whether any embeddable live stream in a course is currently live,
 * so the course navigation can show a "Live now" badge without blocking page
 * render on a media-server round trip.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_live_status extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Checks every embeddable-player stream in the course (OBS, or Zoom
     * relayed into the embedded player) and reports the first one found live.
     *
     * @param int $courseid course id
     * @return array live status
     */
    public static function execute(int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/livestream/lib.php');

        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('mod/livestream:view', $context);

        $config = get_config('mod_livestream');
        $hasmedia = !empty($config->rtmpserver) && !empty($config->hlsbaseurl);

        $live = false;
        $cmid = 0;

        if ($hasmedia) {
            $streams = $DB->get_records('livestream', ['course' => $courseid]);
            foreach ($streams as $stream) {
                $isobs = (int) $stream->streamtype === LIVESTREAM_TYPE_OBS;
                $zoomembed = (int) $stream->streamtype === LIVESTREAM_TYPE_ZOOM && !empty($stream->zoommeetingid);
                if (!$isobs && !$zoomembed) {
                    continue;
                }
                if (\mod_livestream\local\mediamtx_client::is_live($config->hlsbaseurl, $stream->streamkey)) {
                    $cm = get_coursemodule_from_instance('livestream', $stream->id, $courseid);
                    if ($cm) {
                        $live = true;
                        $cmid = (int) $cm->id;
                        break;
                    }
                }
            }
        }

        return ['live' => $live, 'cmid' => $cmid];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'live' => new external_value(PARAM_BOOL, 'Whether any embeddable stream in the course is currently live'),
            'cmid' => new external_value(PARAM_INT, 'Course module id of the live activity, or 0 when none', VALUE_DEFAULT, 0),
        ]);
    }
}
