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
 * Posts a plain-text message to a livestream activity's ephemeral live chat.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_chat_message extends external_api {

    /** @var int maximum stored message length, matching the DB column. */
    const MAX_LENGTH = 500;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the livestream activity'),
            'message' => new external_value(PARAM_TEXT, 'Chat message text'),
        ]);
    }

    /**
     * Stores a chat message.
     *
     * @param int $cmid course module id
     * @param string $message message text
     * @return array the created message id
     */
    public static function execute(int $cmid, string $message): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'message' => $message] = self::validate_parameters(
            self::execute_parameters(), ['cmid' => $cmid, 'message' => $message]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'livestream');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/livestream:chat', $context);

        $message = trim($message);
        if ($message === '') {
            throw new \invalid_parameter_exception('message must not be empty');
        }
        if (\core_text::strlen($message) > self::MAX_LENGTH) {
            $message = \core_text::substr($message, 0, self::MAX_LENGTH);
        }

        $record = new \stdClass();
        $record->livestreamid = $cm->instance;
        $record->userid = $USER->id;
        $record->message = $message;
        $record->timecreated = time();
        $id = $DB->insert_record('livestream_chat', $record);

        return ['id' => $id];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'id of the created message'),
        ]);
    }
}
