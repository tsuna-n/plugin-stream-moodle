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
 * Admin settings for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // --- OBS / self-hosted media server -------------------------------------.
    $settings->add(new admin_setting_heading('mod_livestream/obsheading',
        get_string('settingsobsheading', 'mod_livestream'),
        get_string('settingsobsheading_desc', 'mod_livestream')));

    // Defaults are intentionally empty: mod_form validation treats an empty
    // value as "not configured" and blocks teachers from creating an OBS
    // activity until an admin sets a real, reachable URL. A non-empty
    // placeholder would pass that check and let teachers save activities that
    // silently point at a non-existent server. The expected format is shown in
    // each setting's description string instead.
    $settings->add(new admin_setting_configtext('mod_livestream/rtmpserver',
        get_string('settingrtmpserver', 'mod_livestream'),
        get_string('settingrtmpserver_desc', 'mod_livestream'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_livestream/hlsbaseurl',
        get_string('settinghlsbaseurl', 'mod_livestream'),
        get_string('settinghlsbaseurl_desc', 'mod_livestream'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtext('mod_livestream/hlsjsurl',
        get_string('settinghlsjsurl', 'mod_livestream'),
        get_string('settinghlsjsurl_desc', 'mod_livestream'),
        'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js', PARAM_URL));

    // Base URL of the MediaMTX playback server (port 9996). When set, a
    // "watch recording" link appears automatically once a stream ends. Leave
    // empty to keep recordings on disk only. Must be reachable from students'
    // browsers (and, like HLS, served over HTTPS on an HTTPS Moodle).
    $settings->add(new admin_setting_configtext('mod_livestream/playbackbaseurl',
        get_string('settingplaybackbaseurl', 'mod_livestream'),
        get_string('settingplaybackbaseurl_desc', 'mod_livestream'),
        '', PARAM_URL));

    // Zoom has no site-wide setting: each teacher may hold a separate Zoom
    // account/organisation, so credentials are entered per-teacher at
    // My courses -> (course) -> "My Zoom account" (mod/livestream/zoomaccount.php),
    // not here.
    $settings->add(new admin_setting_heading('mod_livestream/zoomheading',
        get_string('settingszoomheading', 'mod_livestream'),
        get_string('settingszoomheading_desc', 'mod_livestream')));
}
