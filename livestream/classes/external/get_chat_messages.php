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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns new live chat messages for a livestream activity, since a given
 * message id -- a delta fetch so the client only appends what's new.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_chat_messages extends external_api {

    /** @var int cap on rows returned per poll, in case a client falls far behind. */
    const MAX_MESSAGES = 200;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the livestream activity'),
            'afterid' => new external_value(PARAM_INT, 'Only return messages with a higher id than this', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Fetches messages newer than afterid.
     *
     * @param int $cmid course module id
     * @param int $afterid last message id the client already has
     * @return array messages
     */
    public static function execute(int $cmid, int $afterid = 0): array {
        global $DB;

        ['cmid' => $cmid, 'afterid' => $afterid] = self::validate_parameters(
            self::execute_parameters(), ['cmid' => $cmid, 'afterid' => $afterid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'livestream');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/livestream:chat', $context);

        $sql = "SELECT c.id, c.userid, c.message, c.timecreated, u.firstname, u.lastname
                  FROM {livestream_chat} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE c.livestreamid = :livestreamid AND c.id > :afterid
              ORDER BY c.id ASC";
        $records = $DB->get_records_sql($sql,
            ['livestreamid' => $cm->instance, 'afterid' => $afterid], 0, self::MAX_MESSAGES);

        $messages = [];
        foreach ($records as $record) {
            $messages[] = [
                'id' => (int) $record->id,
                'userid' => (int) $record->userid,
                'username' => fullname($record),
                'message' => $record->message,
                'timecreated' => (int) $record->timecreated,
            ];
        }

        return ['messages' => $messages];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'messages' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'message id'),
                'userid' => new external_value(PARAM_INT, 'author user id'),
                'username' => new external_value(PARAM_TEXT, 'author display name'),
                'message' => new external_value(PARAM_TEXT, 'message text'),
                'timecreated' => new external_value(PARAM_INT, 'unix timestamp'),
            ])),
        ]);
    }
}
