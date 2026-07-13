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
 * English language strings for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['calendarstart'] = '{$a} starts';
$string['coursenavstreams'] = 'Live streams';
$string['errorobsnotconfigured'] = 'OBS streaming is not configured yet. A site administrator must set the RTMP server and HLS base URL in the plugin settings.';
$string['errorzoomapi'] = 'Zoom API error: {$a}';
$string['errorzoomnotconfigured'] = 'Zoom is not configured yet. A site administrator must enter the Zoom Server-to-Server OAuth credentials in the plugin settings.';
$string['joinmeeting'] = 'Join Zoom meeting';
$string['livestream:addinstance'] = 'Add a new live stream activity';
$string['livestream:managestream'] = 'Manage the stream (see stream key and host controls)';
$string['livestream:view'] = 'View a live stream activity';
$string['livestreamname'] = 'Stream name';
$string['meetingid'] = 'Meeting ID';
$string['modulename'] = 'Live stream';
$string['modulename_help'] = 'The live stream activity lets a teacher broadcast a lesson to students, either by streaming from OBS Studio to the institution\'s media server (students watch an embedded player) or through an automatically created Zoom meeting.';
$string['modulenameplural'] = 'Live streams';
$string['nolivestreams'] = 'There are no live streams in this course.';
$string['obssetup'] = 'OBS setup (teacher only)';
$string['obssetup_help'] = 'In OBS Studio open Settings → Stream, choose "Custom...", then copy the server URL and stream key below. Press "Start Streaming" in OBS when you are ready — the player on this page goes live automatically within a few seconds.';
$string['pluginadministration'] = 'Live stream administration';
$string['pluginname'] = 'Live stream';
$string['privacy:metadata:zoom'] = 'When a Zoom meeting is used, participants join through Zoom, which receives their data under its own privacy policy.';
$string['privacy:metadata:zoom:email'] = 'The email address the participant uses in Zoom.';
$string['privacy:metadata:zoom:fullname'] = 'The display name the participant uses in Zoom.';
$string['recordingurl'] = 'Recording URL';
$string['recordingurl_help'] = 'Optional. After the class, paste a link to the recording (e.g. a video in your media library or YouTube) and students will see a "Watch recording" button.';
$string['rtmpserver'] = 'Server (RTMP)';
$string['scheduledfor'] = 'Scheduled for {$a}';
$string['settinghlsbaseurl'] = 'HLS base URL';
$string['settinghlsbaseurl_desc'] = 'Public base URL where the media server serves HLS, e.g. https://media.example.com:8888 — the player requests {baseurl}/{streamkey}/index.m3u8. Must be reachable by students\' browsers (HTTPS strongly recommended).';
$string['settinghlsjsurl'] = 'hls.js URL';
$string['settinghlsjsurl_desc'] = 'URL of the hls.js library used by the player. The default loads it from the jsDelivr CDN; you can point this to a self-hosted copy instead.';
$string['settingrtmpserver'] = 'RTMP server URL';
$string['settingrtmpserver_desc'] = 'The ingest URL teachers enter in OBS, e.g. rtmp://media.example.com:1935 (without a trailing app path or the stream key — the stream key alone is the media server path).';
$string['settingsobsheading'] = 'OBS / media server';
$string['settingsobsheading_desc'] = 'Settings for the self-hosted media server that receives RTMP from OBS and serves HLS to students. See the media-server directory of the plugin distribution for a ready-made MediaMTX docker-compose setup.';
$string['settingszoomheading'] = 'Zoom';
$string['settingszoomheading_desc'] = 'Server-to-Server OAuth app credentials. Create the app at marketplace.zoom.us (Develop → Build App → Server-to-Server OAuth) with the meeting:write scope.';
$string['settingzoomaccountid'] = 'Zoom account ID';
$string['settingzoomaccountid_desc'] = 'Account ID of your Zoom Server-to-Server OAuth app.';
$string['settingzoomclientid'] = 'Zoom client ID';
$string['settingzoomclientid_desc'] = 'Client ID of your Zoom Server-to-Server OAuth app.';
$string['settingzoomclientsecret'] = 'Zoom client secret';
$string['settingzoomclientsecret_desc'] = 'Client secret of your Zoom Server-to-Server OAuth app.';
$string['startmeeting'] = 'Start meeting (host)';
$string['starttime'] = 'Scheduled start';
$string['starttime_help'] = 'Optional. If set, the stream appears in the course calendar and students see the scheduled time on the activity page.';
$string['statuschecking'] = 'Checking stream…';
$string['statuslive'] = 'LIVE';
$string['statusoffline'] = 'Offline';
$string['streamduration'] = 'Expected duration';
$string['streamkey'] = 'Stream key';
$string['streamkeywarning'] = 'Keep the stream key secret — anyone who has it can broadcast to this activity.';
$string['streamoffline'] = 'The stream has not started yet. This page will start playing automatically as soon as the teacher goes live.';
$string['streamsettings'] = 'Stream settings';
$string['streamtype'] = 'Stream type';
$string['streamtype_help'] = '* **OBS / media server** — stream from OBS Studio to the institution\'s media server; students watch an embedded player on this page.
* **Zoom meeting** — a Zoom meeting is created automatically; students join through Zoom.';
$string['typeobs'] = 'OBS / media server';
$string['typezoom'] = 'Zoom meeting';
$string['watchrecording'] = 'Watch recording';
$string['zoomcreatefailed'] = 'The activity was saved, but the Zoom meeting could not be created: {$a} — edit and save the activity again to retry.';
$string['zoommeetingmissing'] = 'No Zoom meeting is linked to this activity yet. A teacher must edit and save the activity to create one.';
$string['zoompasscode'] = 'Passcode';
$string['zoompasscode_help'] = 'Optional meeting passcode. If left empty, Zoom may generate one according to your account security settings.';
$string['zoomupdatefailed'] = 'The activity was saved, but the Zoom meeting could not be updated: {$a}';
