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

    $settings->add(new admin_setting_configtext('mod_livestream/rtmpserver',
        get_string('settingrtmpserver', 'mod_livestream'),
        get_string('settingrtmpserver_desc', 'mod_livestream'),
        'rtmp://your-media-server:1935/live', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_livestream/hlsbaseurl',
        get_string('settinghlsbaseurl', 'mod_livestream'),
        get_string('settinghlsbaseurl_desc', 'mod_livestream'),
        'https://your-media-server:8888', PARAM_URL));

    $settings->add(new admin_setting_configtext('mod_livestream/hlsjsurl',
        get_string('settinghlsjsurl', 'mod_livestream'),
        get_string('settinghlsjsurl_desc', 'mod_livestream'),
        'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js', PARAM_URL));

    // --- Zoom ----------------------------------------------------------------.
    $settings->add(new admin_setting_heading('mod_livestream/zoomheading',
        get_string('settingszoomheading', 'mod_livestream'),
        get_string('settingszoomheading_desc', 'mod_livestream')));

    $settings->add(new admin_setting_configtext('mod_livestream/zoomaccountid',
        get_string('settingzoomaccountid', 'mod_livestream'),
        get_string('settingzoomaccountid_desc', 'mod_livestream'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_livestream/zoomclientid',
        get_string('settingzoomclientid', 'mod_livestream'),
        get_string('settingzoomclientid_desc', 'mod_livestream'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('mod_livestream/zoomclientsecret',
        get_string('settingzoomclientsecret', 'mod_livestream'),
        get_string('settingzoomclientsecret_desc', 'mod_livestream'),
        ''));
}
