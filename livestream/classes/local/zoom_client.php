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

namespace mod_livestream\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Minimal Zoom REST client using Server-to-Server OAuth.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_client {

    /** @var string OAuth token endpoint. */
    const TOKEN_URL = 'https://zoom.us/oauth/token';

    /** @var string REST API base. */
    const API_BASE = 'https://api.zoom.us/v2';

    /** @var string cached access token for this request. */
    private $token = null;

    /** @var \stdClass plugin config (zoomaccountid, zoomclientid, zoomclientsecret). */
    private $config;

    /**
     * Constructor. Throws if Zoom credentials are not configured.
     */
    public function __construct() {
        $this->config = get_config('mod_livestream');
        if (empty($this->config->zoomaccountid) || empty($this->config->zoomclientid)
                || empty($this->config->zoomclientsecret)) {
            throw new \moodle_exception('errorzoomnotconfigured', 'mod_livestream');
        }
    }

    /**
     * Creates a scheduled Zoom meeting.
     *
     * @param string $topic meeting topic
     * @param int $starttime unix timestamp, 0 for "now"
     * @param int $durationminutes duration in minutes
     * @param string $passcode optional passcode; Zoom generates one if empty and required
     * @return \stdClass decoded meeting object (id, join_url, start_url, password)
     */
    public function create_meeting(string $topic, int $starttime, int $durationminutes, string $passcode = ''): \stdClass {
        $body = [
            'topic' => $topic,
            'type' => $starttime > 0 ? 2 : 1, // 2 = scheduled, 1 = instant-style (no fixed time).
            'duration' => max(1, $durationminutes),
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => true,
                'mute_upon_entry' => true,
            ],
        ];
        if ($starttime > 0) {
            $body['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $starttime);
            $body['timezone'] = 'UTC';
        }
        if ($passcode !== '') {
            $body['password'] = $passcode;
        }

        return $this->request('POST', '/users/me/meetings', $body);
    }

    /**
     * Updates topic/time/duration of an existing meeting.
     *
     * @param string $meetingid Zoom meeting id
     * @param string $topic meeting topic
     * @param int $starttime unix timestamp, 0 to leave unscheduled
     * @param int $durationminutes duration in minutes
     */
    public function update_meeting(string $meetingid, string $topic, int $starttime, int $durationminutes): void {
        $body = [
            'topic' => $topic,
            'duration' => max(1, $durationminutes),
        ];
        if ($starttime > 0) {
            $body['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $starttime);
            $body['timezone'] = 'UTC';
        }
        $this->request('PATCH', '/meetings/' . urlencode($meetingid), $body);
    }

    /**
     * Deletes a meeting.
     *
     * @param string $meetingid Zoom meeting id
     */
    public function delete_meeting(string $meetingid): void {
        $this->request('DELETE', '/meetings/' . urlencode($meetingid));
    }

    /**
     * Configures a meeting's custom live stream so Zoom pushes it to our
     * media server. Zoom publishes to "<streamurl>/<streamkey>", which is the
     * same RTMP path OBS would use, so the lesson appears in the embedded
     * Moodle player exactly like OBS mode.
     *
     * The Zoom account must have "Allow live streaming of meetings → Custom Live
     * Streaming Service" enabled, and the OAuth app must hold a live-streaming
     * write scope, or Zoom returns an error.
     *
     * @param string $meetingid Zoom meeting id
     * @param string $streamurl RTMP base, e.g. rtmp://media.example.com:1935
     * @param string $streamkey the activity stream key (RTMP path)
     * @param string $pageurl URL of the page where the stream is displayed
     */
    public function set_livestream(string $meetingid, string $streamurl, string $streamkey, string $pageurl): void {
        $this->request('PATCH', '/meetings/' . urlencode($meetingid) . '/livestream', [
            'stream_url' => $streamurl,
            'stream_key' => $streamkey,
            'page_url' => $pageurl,
        ]);
    }

    /**
     * Starts or stops the custom live stream of an in-progress meeting.
     *
     * Only works while the meeting is actually running (a host has joined);
     * otherwise Zoom rejects it. Teachers can also toggle it from the Zoom
     * client ("More → Live on Custom Live Streaming Service").
     *
     * @param string $meetingid Zoom meeting id
     * @param bool $start true to start, false to stop
     */
    public function set_livestream_status(string $meetingid, bool $start): void {
        $this->request('PATCH', '/meetings/' . urlencode($meetingid) . '/livestream/status', [
            'action' => $start ? 'start' : 'stop',
        ]);
    }

    /**
     * Obtains (and memoizes) an access token via account_credentials grant.
     *
     * @return string bearer token
     */
    private function get_token(): string {
        if ($this->token !== null) {
            return $this->token;
        }

        $curl = new \curl();
        $curl->setHeader('Authorization: Basic ' . base64_encode(
            $this->config->zoomclientid . ':' . $this->config->zoomclientsecret));

        $response = $curl->post(self::TOKEN_URL, [
            'grant_type' => 'account_credentials',
            'account_id' => $this->config->zoomaccountid,
        ]);

        $decoded = json_decode($response);
        if (empty($decoded->access_token)) {
            $reason = $decoded->reason ?? $decoded->error ?? s($response);
            throw new \moodle_exception('errorzoomapi', 'mod_livestream', '', 'OAuth: ' . $reason);
        }

        $this->token = $decoded->access_token;
        return $this->token;
    }

    /**
     * Performs an authenticated JSON request against the Zoom API.
     *
     * @param string $method HTTP method
     * @param string $path path under the API base, starting with /
     * @param array|null $body JSON body for POST/PATCH
     * @return \stdClass decoded response (empty object for 204 responses)
     */
    private function request(string $method, string $path, ?array $body = null): \stdClass {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->get_token(),
            'Content-Type: application/json',
        ]);

        $url = self::API_BASE . $path;
        $options = ['CURLOPT_CUSTOMREQUEST' => $method];
        $payload = $body !== null ? json_encode($body) : null;

        switch ($method) {
            case 'POST':
                $response = $curl->post($url, $payload);
                break;
            case 'DELETE':
                $response = $curl->delete($url, null, $options);
                break;
            default:
                $response = $curl->post($url, $payload, $options);
                break;
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode >= 400) {
            $decoded = json_decode($response);
            $message = $decoded->message ?? s($response);
            throw new \moodle_exception('errorzoomapi', 'mod_livestream', '', $httpcode . ': ' . $message);
        }

        $decoded = json_decode($response ?: '{}');
        return is_object($decoded) ? $decoded : new \stdClass();
    }
}
