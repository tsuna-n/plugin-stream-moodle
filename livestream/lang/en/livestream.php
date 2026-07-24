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

$string['attendance'] = 'Attendance';
$string['attendeecount'] = 'Students seen';
$string['attendeeduration'] = 'Duration';
$string['attendeefirstseen'] = 'First seen';
$string['attendeelastseen'] = 'Last seen';
$string['attendeeusername'] = 'Username';
$string['calendarstart'] = '{$a} starts';
$string['chatheading'] = 'Live chat';
$string['chatplaceholder'] = 'Say something…';
$string['chatsend'] = 'Send';
$string['coursenavlive'] = 'Live now';
$string['coursenavstreams'] = 'Live streams';
$string['downloadcsv'] = 'Download CSV';
$string['errorobsnotconfigured'] = 'OBS streaming is not configured yet. A site administrator must set the RTMP server and HLS base URL in the plugin settings.';
$string['errorzoomapi'] = 'Zoom API error: {$a}';
$string['errorzoomnotconfigured'] = 'You have not connected a Zoom account yet. Use "Manage my Zoom account" (in the course navigation) to enter your Zoom Server-to-Server OAuth credentials first.';
$string['joinmeeting'] = 'Join Zoom meeting';
$string['managezoomaccount'] = 'Manage my Zoom account';
$string['livestream:addinstance'] = 'Add a new live stream activity';
$string['livestream:chat'] = 'Participate in the live chat';
$string['livestream:managestream'] = 'Manage the stream (see stream key and host controls)';
$string['livestream:view'] = 'View a live stream activity';
$string['livestreamname'] = 'Stream name';
$string['meetingid'] = 'Meeting ID';
$string['modulename'] = 'Live stream';
$string['modulename_help'] = 'The live stream activity lets a teacher broadcast a lesson to students, either by streaming from OBS Studio to the institution\'s media server (students watch an embedded player) or through an automatically created Zoom meeting.';
$string['modulenameplural'] = 'Live streams';
$string['noattendees'] = 'No students were seen watching this session.';
$string['nolivestreams'] = 'There are no live streams in this course.';
$string['nosessions'] = 'No sessions recorded yet.';
$string['obssetup'] = 'OBS setup (teacher only)';
$string['obssetup_help'] = 'In OBS Studio open Settings → Stream, choose "Custom...", then copy the server URL and stream key below. Press "Start Streaming" in OBS when you are ready — the player on this page goes live automatically within a few seconds.';
$string['pluginadministration'] = 'Live stream administration';
$string['pluginname'] = 'Live stream';
$string['privacy:metadata:livestream_attendance'] = 'Records of when a student was seen watching a live broadcast, for attendance/roll call.';
$string['privacy:metadata:livestream_attendance:firstseen'] = 'The time the user was first seen watching this broadcast.';
$string['privacy:metadata:livestream_attendance:lastseen'] = 'The time the user was last seen watching this broadcast.';
$string['privacy:metadata:livestream_attendance:userid'] = 'The id of the user who was watching.';
$string['privacy:metadata:livestream_zoom_account'] = 'Your personal Zoom Server-to-Server OAuth app credentials, used to create Zoom meetings under your own Zoom account.';
$string['privacy:metadata:livestream_zoom_account:accountid'] = 'The Account ID of your Zoom Server-to-Server OAuth app.';
$string['privacy:metadata:livestream_zoom_account:clientid'] = 'The Client ID of your Zoom Server-to-Server OAuth app.';
$string['privacy:metadata:livestream_zoom_account:clientsecret'] = 'The Client secret of your Zoom Server-to-Server OAuth app.';
$string['privacy:metadata:zoom'] = 'When a Zoom meeting is used, participants join through Zoom, which receives their data under its own privacy policy.';
$string['privacy:metadata:zoom:email'] = 'The email address the participant uses in Zoom.';
$string['privacy:metadata:zoom:fullname'] = 'The display name the participant uses in Zoom.';
$string['recordingurl'] = 'Recording URL';
$string['recordingurl_help'] = 'Optional. After the class, paste a link to the recording (e.g. a video in your media library or YouTube) and students will see a "Watch recording" button.';
$string['rtmpserver'] = 'Server (RTMP)';
$string['settingrealtimesecret'] = 'Realtime shared secret';
$string['settingrealtimesecret_desc'] = 'Shared HMAC secret used to sign realtime tokens and to authenticate server-to-server calls between Moodle and the realtime gateway (also set as REALTIME_SECRET in the gateway\'s environment). Leave empty while realtimeurl is empty.';
$string['settingrealtimeurl'] = 'Realtime gateway URL';
$string['settingrealtimeurl_desc'] = 'Public base URL of the realtime gateway (e.g. https://rt.media.example.com), reached through Caddy in the media-server docker-compose. When set, the player, live chat, and course "Live now" badge switch from polling to an instant push (SSE) connection, automatically falling back to polling if the gateway is unreachable. Leave empty to keep the classic polling behaviour with no realtime gateway required.';
$string['settingsrealtimeheading'] = 'Realtime (full streaming)';
$string['settingsrealtimeheading_desc'] = 'Optional real-time push gateway that replaces the player/chat/badge polling loops with instant updates over Server-Sent Events. See the realtime directory of the plugin distribution for the Node gateway. Leave realtimeurl empty to keep today\'s polling behaviour unchanged.';
$string['scheduledfor'] = 'Scheduled for {$a}';
$string['sessionend'] = 'Session end';
$string['sessioninprogress'] = 'In progress';
$string['sessionrange'] = '{$a->start} – {$a->end}';
$string['sessionstart'] = 'Session start';
$string['settinghlsbaseurl'] = 'HLS base URL';
$string['settinghlsbaseurl_desc'] = 'Public base URL where the media server serves HLS, e.g. https://media.example.com:8888 — the player requests {baseurl}/{streamkey}/index.m3u8. Must be reachable by students\' browsers (HTTPS strongly recommended).';
$string['settinghlsjsurl'] = 'hls.js URL';
$string['settinghlsjsurl_desc'] = 'URL of the hls.js library used by the player. The default loads it from the jsDelivr CDN; you can point this to a self-hosted copy instead.';
$string['settingplaybackbaseurl'] = 'Recording playback URL';
$string['settingplaybackbaseurl_desc'] = 'Base URL of the media server\'s recording playback service, e.g. https://media.example.com:9996. When set, a "Watch recording" button appears automatically after a stream ends. Leave empty to keep recordings on disk only. Must be reachable by students\' browsers (HTTPS strongly recommended).';
$string['settingrtmpserver'] = 'RTMP server URL';
$string['settingrtmpserver_desc'] = 'The ingest URL teachers enter in OBS, e.g. rtmp://media.example.com:1935 (without a trailing app path or the stream key — the stream key alone is the media server path).';
$string['settingsobsheading'] = 'OBS / media server';
$string['settingsobsheading_desc'] = 'Settings for the self-hosted media server that receives RTMP from OBS and serves HLS to students. See the media-server directory of the plugin distribution for a ready-made MediaMTX docker-compose setup.';
$string['settingszoomheading'] = 'Zoom';
$string['settingszoomheading_desc'] = 'Zoom has no site-wide setting: each teacher may hold a separate Zoom account/organisation, so credentials are entered per-teacher via "Manage my Zoom account" in the course navigation (mod/livestream/zoomaccount.php), not here.';
$string['settingzoomaccountid'] = 'Zoom account ID';
$string['settingzoomclientid'] = 'Zoom client ID';
$string['settingzoomclientsecret'] = 'Zoom client secret';
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
* **Zoom meeting** — a Zoom meeting is created automatically. When the media server is also configured, Zoom relays the meeting into the same embedded player (students stay on this page); otherwise students join through Zoom.';
$string['task_close_stale_sessions'] = 'Close stale live-stream sessions';
$string['typeobs'] = 'OBS / media server';
$string['typezoom'] = 'Zoom meeting';
$string['watchrecording'] = 'Watch recording';
$string['zoomaccountintro'] = 'Meetings for your Zoom-type live stream activities are created under your own Zoom account. Create a Server-to-Server OAuth app at marketplace.zoom.us (Develop → Build App → Server-to-Server OAuth) with the meeting:write scope, then enter its credentials below.';
$string['zoomaccountremove'] = 'Remove my Zoom account';
$string['zoomaccountremoveconfirm'] = 'Remove your Zoom account credentials? Existing Zoom meetings created with them keep working, but you will not be able to create or manage new ones until you reconnect an account.';
$string['zoomaccountremoved'] = 'Your Zoom account was removed.';
$string['zoomaccountsave'] = 'Save Zoom account';
$string['zoomaccountsaved'] = 'Your Zoom account was saved.';
$string['zoomaccounttitle'] = 'My Zoom account';
$string['zoomcreatefailed'] = 'The activity was saved, but the Zoom meeting could not be created: {$a} — edit and save the activity again to retry.';
$string['zoomembedhelp'] = 'Students watch this lesson in the embedded player below — they do not need to open Zoom. Start the meeting, then in the Zoom client choose <strong>More → Live on Custom Live Streaming Service</strong> to go live. The player starts automatically within a few seconds.';
$string['zoomlivestreamfailed'] = 'The activity was saved, but the Zoom custom live stream could not be configured: {$a} — check that "Custom Live Streaming Service" is enabled on your Zoom account and that the OAuth app has the live-streaming scope. Students can still join the meeting directly.';
$string['zoommeetingmissing'] = 'No Zoom meeting is linked to this activity yet. A teacher must edit and save the activity to create one.';
$string['zoompasscode'] = 'Passcode';
$string['zoompasscode_help'] = 'Optional meeting passcode. If left empty, Zoom may generate one according to your account security settings.';
$string['zoomteacherheading'] = 'Zoom host controls (teacher only)';
$string['zoomupdatefailed'] = 'The activity was saved, but the Zoom meeting could not be updated: {$a}';
