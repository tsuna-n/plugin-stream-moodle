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
 * Lists all livestream instances in a course.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/livestream/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_livestream'));

$instances = get_all_instances_in_course('livestream', $course);
if (empty($instances)) {
    notice(get_string('nolivestreams', 'mod_livestream'),
        new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('streamtype', 'mod_livestream'),
    get_string('starttime', 'mod_livestream'),
];

foreach ($instances as $instance) {
    $url = new moodle_url('/mod/livestream/view.php', ['id' => $instance->coursemodule]);
    $typename = (int) $instance->streamtype === LIVESTREAM_TYPE_ZOOM
        ? get_string('typezoom', 'mod_livestream')
        : get_string('typeobs', 'mod_livestream');
    $table->data[] = [
        html_writer::link($url, format_string($instance->name)),
        $typename,
        $instance->starttime ? userdate($instance->starttime) : '-',
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
