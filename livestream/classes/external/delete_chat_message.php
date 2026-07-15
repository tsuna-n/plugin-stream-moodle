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
 * Deletes a live chat message (moderation). Teacher-only.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_chat_message extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the livestream activity'),
            'messageid' => new external_value(PARAM_INT, 'id of the message to delete'),
        ]);
    }

    /**
     * Deletes a message, scoped to this activity so a valid messageid from
     * another activity's chat can never be targeted.
     *
     * @param int $cmid course module id
     * @param int $messageid message id to delete
     * @return array success flag
     */
    public static function execute(int $cmid, int $messageid): array {
        global $DB;

        ['cmid' => $cmid, 'messageid' => $messageid] = self::validate_parameters(
            self::execute_parameters(), ['cmid' => $cmid, 'messageid' => $messageid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'livestream');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/livestream:managestream', $context);

        $DB->delete_records('livestream_chat', ['id' => $messageid, 'livestreamid' => $cm->instance]);

        return ['success' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'whether the delete ran'),
        ]);
    }
}
