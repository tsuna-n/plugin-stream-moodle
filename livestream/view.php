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
 * Displays a livestream activity to teachers and students.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'livestream');
$livestream = $DB->get_record('livestream', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livestream:view', $context);

// Log the view and mark completion.
$event = \mod_livestream\event\course_module_viewed::create([
    'objectid' => $livestream->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('livestream', $livestream);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/livestream/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($livestream->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($livestream);

$canmanage = has_capability('mod/livestream:managestream', $context);
$config = get_config('mod_livestream');

$isobs = (int) $livestream->streamtype === LIVESTREAM_TYPE_OBS;
$iszoom = (int) $livestream->streamtype === LIVESTREAM_TYPE_ZOOM;

// The media server is what turns a Zoom meeting into an embedded stream: with
// it configured, Zoom relays to our RTMP and both modes share one HLS player.
$hasmedia = !empty($config->rtmpserver) && !empty($config->hlsbaseurl);
$zoomembed = $iszoom && $hasmedia && !empty($livestream->zoommeetingid);
$showplayer = $isobs || $zoomembed;

$data = [
    'name' => format_string($livestream->name),
    'intro' => format_module_intro('livestream', $livestream, $cm->id),
    'isobs' => $isobs,
    'iszoom' => $iszoom,
    'zoomembed' => $zoomembed,
    'showplayer' => $showplayer,
    'canmanage' => $canmanage,
    'starttime' => $livestream->starttime ? userdate($livestream->starttime) : '',
    'recordingurl' => $livestream->recordingurl ?: '',
];

if ($showplayer) {
    $hlsbase = rtrim($config->hlsbaseurl ?? '', '/');
    $data['hlsurl'] = $hlsbase . '/' . $livestream->streamkey . '/index.m3u8';
    $data['cmid'] = $cm->id;
    $PAGE->requires->js_call_amd('mod_livestream/player', 'init', [[
        'cmid' => $cm->id,
        'hlsurl' => $data['hlsurl'],
        'hlsjsurl' => $config->hlsjsurl ?? 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js',
    ]]);
    if ($canmanage) {
        $data['attendanceurl'] = (new moodle_url('/mod/livestream/attendance.php', ['id' => $cm->id]))->out(false);
    }

    // Plain Zoom mode already has Zoom's own chat; the custom panel is only
    // needed where students would otherwise have no way to interact (OBS,
    // or Zoom relayed into this embedded player).
    $data['canchat'] = has_capability('mod/livestream:chat', $context);
    if ($data['canchat']) {
        $PAGE->requires->js_call_amd('mod_livestream/chat', 'init', [[
            'cmid' => $cm->id,
            'canmoderate' => $canmanage,
        ]]);
    }
}

if ($isobs && $canmanage) {
    $data['rtmpserver'] = $config->rtmpserver ?? '';
    $data['streamkey'] = $livestream->streamkey;
}

if ($iszoom) {
    // Students only get the join button when there is no embedded player.
    $data['zoomjoinonly'] = !$zoomembed;
    $data['zoomconfigured'] = !empty($livestream->zoommeetingid);
    $data['zoomjoinurl'] = $livestream->zoomjoinurl ?: '';
    $data['zoommeetingid'] = $livestream->zoommeetingid;
    $data['zoompasscode'] = $livestream->zoompasscode;
    if ($canmanage) {
        $data['zoomstarturl'] = $livestream->zoomstarturl ?: '';
        $data['managezoomaccounturl'] = (new moodle_url('/mod/livestream/zoomaccount.php'))->out(false);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_livestream/view', $data);
echo $OUTPUT->footer();
