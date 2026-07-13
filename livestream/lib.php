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
 * Library of interface functions and constants for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var int Stream type: OBS / RTMP via the self-hosted media server. */
define('LIVESTREAM_TYPE_OBS', 0);
/** @var int Stream type: Zoom meeting. */
define('LIVESTREAM_TYPE_ZOOM', 1);

/**
 * Returns whether the module supports a given feature.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed True if supported, null if unknown
 */
function livestream_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Adds a new livestream instance.
 *
 * @param stdClass $data form data
 * @param mod_livestream_mod_form|null $mform the form
 * @return int new instance id
 */
function livestream_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->streamkey = bin2hex(random_bytes(16));

    if ((int) $data->streamtype === LIVESTREAM_TYPE_ZOOM) {
        livestream_create_zoom_meeting($data);
    }

    $data->id = $DB->insert_record('livestream', $data);

    livestream_update_calendar($data);

    return $data->id;
}

/**
 * Updates an existing livestream instance.
 *
 * @param stdClass $data form data
 * @param mod_livestream_mod_form|null $mform the form
 * @return bool success
 */
function livestream_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $old = $DB->get_record('livestream', ['id' => $data->id], '*', MUST_EXIST);
    $data->streamkey = $old->streamkey;

    if ((int) $data->streamtype === LIVESTREAM_TYPE_ZOOM) {
        if (empty($old->zoommeetingid) || (int) $old->streamtype !== LIVESTREAM_TYPE_ZOOM) {
            livestream_create_zoom_meeting($data);
        } else {
            $data->zoommeetingid = $old->zoommeetingid;
            $data->zoomjoinurl = $old->zoomjoinurl;
            $data->zoomstarturl = $old->zoomstarturl;
            livestream_sync_zoom_meeting($data);
        }
    } else if (!empty($old->zoommeetingid)) {
        // Switched away from Zoom: remove the remote meeting, keep local data clean.
        livestream_delete_zoom_meeting($old->zoommeetingid);
        $data->zoommeetingid = '';
        $data->zoomjoinurl = '';
        $data->zoomstarturl = '';
    }

    $DB->update_record('livestream', $data);

    livestream_update_calendar($data);

    return true;
}

/**
 * Deletes a livestream instance.
 *
 * @param int $id instance id
 * @return bool success
 */
function livestream_delete_instance($id) {
    global $DB;

    $livestream = $DB->get_record('livestream', ['id' => $id]);
    if (!$livestream) {
        return false;
    }

    if (!empty($livestream->zoommeetingid)) {
        livestream_delete_zoom_meeting($livestream->zoommeetingid);
    }

    $DB->delete_records('event', ['modulename' => 'livestream', 'instance' => $id]);
    $DB->delete_records('livestream', ['id' => $id]);

    return true;
}

/**
 * Creates a Zoom meeting for the given instance data and stores the result on it.
 *
 * Failures are surfaced to the teacher but do not abort saving the activity,
 * so a misconfigured Zoom connection never loses form data.
 *
 * @param stdClass $data instance data, modified in place
 */
function livestream_create_zoom_meeting($data) {
    try {
        $client = new \mod_livestream\local\zoom_client();
        $meeting = $client->create_meeting(
            $data->name,
            (int) ($data->starttime ?? 0),
            (int) (($data->duration ?? 3600) / 60),
            $data->zoompasscode ?? ''
        );
        $data->zoommeetingid = (string) $meeting->id;
        $data->zoomjoinurl = $meeting->join_url;
        $data->zoomstarturl = $meeting->start_url;
        if (!empty($meeting->password)) {
            $data->zoompasscode = $meeting->password;
        }
    } catch (\moodle_exception $e) {
        $data->zoommeetingid = '';
        $data->zoomjoinurl = '';
        $data->zoomstarturl = '';
        \core\notification::error(get_string('zoomcreatefailed', 'mod_livestream', $e->getMessage()));
    }
}

/**
 * Pushes topic/time changes for an existing meeting to Zoom (best effort).
 *
 * @param stdClass $data instance data
 */
function livestream_sync_zoom_meeting($data) {
    try {
        $client = new \mod_livestream\local\zoom_client();
        $client->update_meeting(
            $data->zoommeetingid,
            $data->name,
            (int) ($data->starttime ?? 0),
            (int) (($data->duration ?? 3600) / 60)
        );
    } catch (\moodle_exception $e) {
        \core\notification::warning(get_string('zoomupdatefailed', 'mod_livestream', $e->getMessage()));
    }
}

/**
 * Deletes a Zoom meeting (best effort).
 *
 * @param string $meetingid Zoom meeting id
 */
function livestream_delete_zoom_meeting($meetingid) {
    try {
        $client = new \mod_livestream\local\zoom_client();
        $client->delete_meeting($meetingid);
    } catch (\moodle_exception $e) {
        // The activity is being removed either way; a stale Zoom meeting is harmless.
        debugging('mod_livestream: could not delete Zoom meeting ' . $meetingid . ': ' . $e->getMessage());
    }
}

/**
 * Creates or updates the calendar event for the scheduled stream.
 *
 * @param stdClass $data instance data (must have id, course, name, starttime, duration)
 */
function livestream_update_calendar($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');

    $eventid = $DB->get_field('event', 'id', ['modulename' => 'livestream', 'instance' => $data->id]);

    if (empty($data->starttime)) {
        if ($eventid) {
            calendar_event::load($eventid)->delete();
        }
        return;
    }

    $event = new stdClass();
    $event->eventtype = 'start';
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->name = get_string('calendarstart', 'mod_livestream', $data->name);
    $event->description = '';
    $event->format = FORMAT_HTML;
    $event->courseid = $data->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'livestream';
    $event->instance = $data->id;
    $event->timestart = $data->starttime;
    $event->timeduration = (int) ($data->duration ?? 0);
    $event->visible = 1;

    if ($eventid) {
        $event->id = $eventid;
        calendar_event::load($eventid)->update($event, false);
    } else {
        calendar_event::create($event, false);
    }
}

/**
 * Adds a "Live streams" item to the course navigation (shown in the course
 * secondary navigation tabs, under "More" in Boost-based themes).
 *
 * @param navigation_node $navigation the course navigation node
 * @param stdClass $course the course record
 * @param context_course $context the course context
 */
function livestream_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    global $DB;

    if (!has_capability('mod/livestream:view', $context)) {
        return;
    }
    if (!$DB->record_exists('livestream', ['course' => $course->id])) {
        return;
    }

    $url = new moodle_url('/mod/livestream/index.php', ['id' => $course->id]);
    $navigation->add(
        get_string('coursenavstreams', 'mod_livestream'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'livestreams',
        new pix_icon('monologo', '', 'mod_livestream')
    );
}

/**
 * Adds course module info for course page rendering (show description support).
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|false
 */
function livestream_get_coursemodule_info($coursemodule) {
    global $DB;

    $livestream = $DB->get_record('livestream', ['id' => $coursemodule->instance],
        'id, name, intro, introformat, streamtype, starttime');
    if (!$livestream) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $livestream->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('livestream', $livestream, $coursemodule->id, false);
    }
    return $info;
}
