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

namespace mod_livestream\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_livestream.
 *
 * Stores one kind of local personal data: attendance rows recording when a
 * user was seen watching a live broadcast (livestream_attendance, scoped
 * through livestream_session to a module context). Joining a Zoom meeting is
 * covered separately by Zoom's own policies and declared as an external
 * location.
 *
 * Also stores livestream_zoom_account: a teacher's personal Zoom
 * Server-to-Server OAuth credentials (teachers may hold entirely separate
 * Zoom accounts/organisations, so this is per-user rather than site
 * config). It is not tied to a course or activity, so it is scoped to the
 * user's own CONTEXT_USER context rather than a module context.
 *
 * livestream_chat is deliberately NOT covered here: it is ephemeral by
 * design (every row is deleted automatically within minutes of the
 * broadcast it belongs to ending -- see classes/local/attendance.php and
 * classes/task/close_stale_sessions.php) and never persists, the same
 * reasoning Moodle core's own (now-removed) mod_chat used to exclude its
 * equivalent short-lived mirror table (chat_messages_current) from privacy
 * export/delete scope.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Declares stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('livestream_attendance', [
            'userid' => 'privacy:metadata:livestream_attendance:userid',
            'firstseen' => 'privacy:metadata:livestream_attendance:firstseen',
            'lastseen' => 'privacy:metadata:livestream_attendance:lastseen',
        ], 'privacy:metadata:livestream_attendance');

        $collection->add_database_table('livestream_zoom_account', [
            'accountid' => 'privacy:metadata:livestream_zoom_account:accountid',
            'clientid' => 'privacy:metadata:livestream_zoom_account:clientid',
            'clientsecret' => 'privacy:metadata:livestream_zoom_account:clientsecret',
        ], 'privacy:metadata:livestream_zoom_account');

        $collection->add_external_location_link('zoom', [
            'fullname' => 'privacy:metadata:zoom:fullname',
            'email' => 'privacy:metadata:zoom:email',
        ], 'privacy:metadata:zoom');

        return $collection;
    }

    /**
     * Finds every module context where a user has an attendance row.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {livestream_attendance} a
                  JOIN {livestream_session} s ON s.id = a.sessionid
                  JOIN {livestream} l ON l.id = s.livestreamid
                  JOIN {course_modules} cm ON cm.instance = l.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'livestream',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        if ($DB->record_exists('livestream_zoom_account', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * Finds every user with an attendance row in a module context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            if ($DB->record_exists('livestream_zoom_account', ['userid' => $context->instanceid])) {
                $userlist->add_user($context->instanceid);
            }
            return;
        }

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('livestream', $context->instanceid);
        if (!$cm) {
            return;
        }

        $sql = "SELECT a.userid
                  FROM {livestream_attendance} a
                  JOIN {livestream_session} s ON s.id = a.sessionid
                 WHERE s.livestreamid = :livestreamid";
        $userlist->add_from_sql('userid', $sql, ['livestreamid' => $cm->instance]);
    }

    /**
     * Exports a user's attendance rows for every approved context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user) {
                if ((int) $context->instanceid !== $userid) {
                    continue;
                }
                $account = $DB->get_record('livestream_zoom_account', ['userid' => $userid]);
                if ($account) {
                    writer::with_context($context)->export_data(
                        [get_string('zoomaccounttitle', 'mod_livestream')],
                        (object) [
                            'accountid' => $account->accountid,
                            'clientid' => $account->clientid,
                            'clientsecret' => $account->clientsecret,
                        ]
                    );
                }
                continue;
            }

            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('livestream', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sql = "SELECT a.firstseen, a.lastseen, s.starttime AS sessionstart, s.endtime AS sessionend
                      FROM {livestream_attendance} a
                      JOIN {livestream_session} s ON s.id = a.sessionid
                     WHERE s.livestreamid = :livestreamid AND a.userid = :userid
                  ORDER BY a.firstseen ASC";
            $records = $DB->get_records_sql($sql, ['livestreamid' => $cm->instance, 'userid' => $userid]);

            if (empty($records)) {
                continue;
            }

            $sessions = [];
            foreach ($records as $record) {
                $sessions[] = [
                    'session_start' => transform::datetime((int) $record->sessionstart),
                    'session_end' => $record->sessionend ? transform::datetime((int) $record->sessionend) : null,
                    'first_seen' => transform::datetime((int) $record->firstseen),
                    'last_seen' => transform::datetime((int) $record->lastseen),
                ];
            }

            writer::with_context($context)->export_data([], (object) ['attendance' => $sessions]);
        }
    }

    /**
     * Deletes every attendance row for all users in a module context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_user) {
            $DB->delete_records('livestream_zoom_account', ['userid' => $context->instanceid]);
            return;
        }

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('livestream', $context->instanceid);
        if (!$cm) {
            return;
        }

        $sessionids = $DB->get_fieldset_select('livestream_session', 'id', 'livestreamid = ?', [$cm->instance]);
        if (empty($sessionids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($sessionids);
        $DB->delete_records_select('livestream_attendance', "sessionid $insql", $inparams);
    }

    /**
     * Deletes one user's attendance rows across their approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user) {
                if ((int) $context->instanceid === $userid) {
                    $DB->delete_records('livestream_zoom_account', ['userid' => $userid]);
                }
                continue;
            }

            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('livestream', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sessionids = $DB->get_fieldset_select('livestream_session', 'id', 'livestreamid = ?', [$cm->instance]);
            if (empty($sessionids)) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($sessionids);
            $inparams[] = $userid;
            $DB->delete_records_select('livestream_attendance', "sessionid $insql AND userid = ?", $inparams);
        }
    }

    /**
     * Deletes specific users' attendance rows in a module context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            if (in_array($context->instanceid, $userlist->get_userids())) {
                $DB->delete_records('livestream_zoom_account', ['userid' => $context->instanceid]);
            }
            return;
        }

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('livestream', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $sessionids = $DB->get_fieldset_select('livestream_session', 'id', 'livestreamid = ?', [$cm->instance]);
        if (empty($sessionids)) {
            return;
        }
        [$sessionsql, $sessionparams] = $DB->get_in_or_equal($sessionids);
        [$usersql, $userparams] = $DB->get_in_or_equal($userids);
        $DB->delete_records_select('livestream_attendance', "sessionid $sessionsql AND userid $usersql",
            array_merge($sessionparams, $userparams));
    }
}
