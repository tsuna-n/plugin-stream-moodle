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
 * Attendance (roll call) report for a livestream activity: lists past
 * broadcast sessions, and per session the students seen watching live.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module id.
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'livestream');
$livestream = $DB->get_record('livestream', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livestream:managestream', $context);

$baseurl = new moodle_url('/mod/livestream/attendance.php', ['id' => $id]);

if ($sessionid) {
    $session = $DB->get_record('livestream_session',
        ['id' => $sessionid, 'livestreamid' => $livestream->id], '*', MUST_EXIST);

    $sql = "SELECT a.id, a.userid, a.firstseen, a.lastseen, u.username, u.firstname, u.lastname
              FROM {livestream_attendance} a
              JOIN {user} u ON u.id = a.userid
             WHERE a.sessionid = :sessionid
          ORDER BY a.firstseen ASC";
    $attendees = $DB->get_records_sql($sql, ['sessionid' => $sessionid]);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $livestream->streamkey . '-session-' . $sessionid . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['username', 'fullname', 'first_seen', 'last_seen', 'duration_seconds']);
        foreach ($attendees as $a) {
            fputcsv($out, [
                $a->username,
                fullname($a),
                userdate($a->firstseen),
                userdate($a->lastseen),
                $a->lastseen - $a->firstseen,
            ]);
        }
        fclose($out);
        exit;
    }

    $PAGE->set_url('/mod/livestream/attendance.php', ['id' => $id, 'sessionid' => $sessionid]);
    $PAGE->set_title(format_string($livestream->name));
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('attendance', 'mod_livestream'));
    echo html_writer::link($baseurl, get_string('back'), ['class' => 'mb-3 d-inline-block']);

    echo html_writer::tag('p', get_string('sessionrange', 'mod_livestream', (object) [
        'start' => userdate($session->starttime),
        'end' => $session->endtime ? userdate($session->endtime) : get_string('sessioninprogress', 'mod_livestream'),
    ]));

    $table = new html_table();
    $table->head = [
        get_string('attendeeusername', 'mod_livestream'),
        get_string('fullname'),
        get_string('attendeefirstseen', 'mod_livestream'),
        get_string('attendeelastseen', 'mod_livestream'),
        get_string('attendeeduration', 'mod_livestream'),
    ];
    foreach ($attendees as $a) {
        $table->data[] = [
            s($a->username),
            fullname($a),
            userdate($a->firstseen),
            userdate($a->lastseen),
            format_time($a->lastseen - $a->firstseen),
        ];
    }
    if (empty($attendees)) {
        echo $OUTPUT->notification(get_string('noattendees', 'mod_livestream'), 'info');
    } else {
        echo html_writer::table($table);
        $csvurl = new moodle_url('/mod/livestream/attendance.php', ['id' => $id, 'sessionid' => $sessionid, 'format' => 'csv']);
        echo html_writer::link($csvurl, get_string('downloadcsv', 'mod_livestream'), ['class' => 'btn btn-outline-secondary']);
    }

    echo $OUTPUT->footer();
    exit;
}

$sql = "SELECT s.id, s.starttime, s.endtime,
               (SELECT COUNT(*) FROM {livestream_attendance} a WHERE a.sessionid = s.id) AS attendeecount
          FROM {livestream_session} s
         WHERE s.livestreamid = :livestreamid
      ORDER BY s.starttime DESC";
$sessions = $DB->get_records_sql($sql, ['livestreamid' => $livestream->id]);

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($livestream->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attendance', 'mod_livestream'));

if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_livestream'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('sessionstart', 'mod_livestream'),
        get_string('sessionend', 'mod_livestream'),
        get_string('attendeecount', 'mod_livestream'),
        '',
    ];
    foreach ($sessions as $s) {
        $viewurl = new moodle_url('/mod/livestream/attendance.php', ['id' => $id, 'sessionid' => $s->id]);
        $table->data[] = [
            userdate($s->starttime),
            $s->endtime ? userdate($s->endtime) : get_string('sessioninprogress', 'mod_livestream'),
            $s->attendeecount,
            html_writer::link($viewurl, get_string('view')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
