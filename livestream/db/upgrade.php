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

/**
 * Upgrade steps for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes upgrade steps between versions.
 *
 * @param int $oldversion previously installed version
 * @return bool
 */
function xmldb_livestream_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026071501) {
        // One row per live broadcast, used for roll-call grouping and to
        // scope the ephemeral chat table's lifetime.
        $table = new xmldb_table('livestream_session');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('livestreamid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('livestreamid', XMLDB_KEY_FOREIGN, ['livestreamid'], 'livestream', ['id']);
        $table->add_index('livestreamid_endtime', XMLDB_INDEX_NOTUNIQUE, ['livestreamid', 'endtime']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Per-user first/last-seen timestamps within a session, for roll call.
        $table = new xmldb_table('livestream_attendance');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('firstseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'livestream_session', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('sessionid_userid', XMLDB_INDEX_UNIQUE, ['sessionid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026071501, 'livestream');
    }

    if ($oldversion < 2026071600) {
        // Ephemeral live-only chat messages; purged when the broadcast
        // session ends (see classes/local/attendance.php and
        // classes/task/close_stale_sessions.php), never kept for replay.
        $table = new xmldb_table('livestream_chat');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('livestreamid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('livestreamid', XMLDB_KEY_FOREIGN, ['livestreamid'], 'livestream', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('livestreamid_id', XMLDB_INDEX_NOTUNIQUE, ['livestreamid', 'id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026071600, 'livestream');
    }

    if ($oldversion < 2026071700) {
        // Zoom moves from one site-wide admin-configured account to a
        // personal account per teacher (see classes/local/zoom_account.php),
        // since different teachers may hold entirely separate Zoom
        // organisations. zoomownerid records whose credentials own an
        // activity's zoommeetingid.
        $table = new xmldb_table('livestream');
        $field = new xmldb_field('zoomownerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'zoompasscode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('livestream_zoom_account');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accountid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '');
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '');
        $table->add_field('clientsecret', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The site-wide Zoom credentials are superseded by the per-teacher
        // account table above; drop them rather than leave stale secrets
        // sitting unused in config_plugins.
        unset_config('zoomaccountid', 'mod_livestream');
        unset_config('zoomclientid', 'mod_livestream');
        unset_config('zoomclientsecret', 'mod_livestream');

        upgrade_mod_savepoint(true, 2026071700, 'livestream');
    }

    if ($oldversion < 2026071701) {
        // The zoomownerid foreign key needs a supporting index.
        $table = new xmldb_table('livestream');
        $index = new xmldb_index('zoomownerid', XMLDB_INDEX_NOTUNIQUE, ['zoomownerid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026071701, 'livestream');
    }

    return true;
}
