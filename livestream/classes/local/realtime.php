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

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * Realtime gateway helper: mints the short-lived HMAC tokens that scope a
 * browser to one SSE room, and best-effort publishes server-side events
 * (chat, status/badge transitions) to the gateway for fan-out.
 *
 * The gateway is entirely optional (see enabled()) -- every AMD module keeps
 * its polling fallback and only opens an EventSource when this is switched
 * on. Nothing here stores any new personal data: tokens are ephemeral and
 * verified only at SSE connect time.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtime {

    /**
     * Whether the realtime gateway is configured. When false, every caller
     * of this class must fall back to the classic polling behaviour.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $config = get_config('mod_livestream');
        return !empty($config->realtimeurl);
    }

    /**
     * Mints a short-lived signed token scoping a user to one SSE room.
     *
     * Token format (implemented identically by the Node gateway):
     *   payload = {uid, room, sk, cid, cm, mod, exp}
     *   b64     = base64url(json_encode(payload))          // no padding
     *   sig     = hex(hmac_sha256(realtimesecret, b64))
     *   token   = b64 . '.' . sig
     *
     * @param int $uid the viewing user's id
     * @param string $room 'cm-<cmid>' or 'course-<courseid>'
     * @param string $streamkey the activity's stream key, for cm- rooms (gateway probe target); '' for course- rooms
     * @param int $courseid course id, or 0
     * @param int $cmid course module id, or 0
     * @param bool $canmoderate whether the user may delete chat messages
     * @return string the signed token
     */
    public static function token(int $uid, string $room, string $streamkey = '', int $courseid = 0,
            int $cmid = 0, bool $canmoderate = false): string {
        $config = get_config('mod_livestream');

        $payload = [
            'uid' => $uid,
            'room' => $room,
            'sk' => $streamkey,
            'cid' => $courseid,
            'cm' => $cmid,
            'mod' => $canmoderate ? 1 : 0,
            'exp' => time() + 60,
        ];

        return self::sign($payload, (string) ($config->realtimesecret ?? ''));
    }

    /**
     * Signs a payload with the given secret. Split out from token() so tests
     * can check a known vector without depending on get_config().
     *
     * @param array $payload
     * @param string $secret
     * @return string
     */
    public static function sign(array $payload, string $secret): string {
        $b64 = self::base64url_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $b64, $secret);
        return $b64 . '.' . $sig;
    }

    /**
     * URL-safe base64 encode with padding stripped, matching Node's
     * equivalent (base64url(json_encode(payload)) in the contract).
     *
     * @param string $data
     * @return string
     */
    public static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Best-effort publish of an event to a room via the gateway's /publish
     * endpoint. Never throws and never blocks the caller more than a couple
     * of seconds -- a gateway outage must not break chat storage or status
     * transitions, only delay their live delivery (the browser also picks
     * this up from its next reconnect snapshot).
     *
     * @param string $room 'cm-<cmid>' or 'course-<courseid>'
     * @param string $event 'status'|'chat'|'chatdelete'|'badge'
     * @param array $data event payload
     */
    public static function publish(string $room, string $event, array $data): void {
        if (!self::enabled()) {
            return;
        }
        $config = get_config('mod_livestream');

        try {
            // ignoresecurity: the realtime gateway URL is a trusted admin
            // setting (same posture as hlsbaseurl/playbackbaseurl), and it
            // commonly runs behind a subdomain on ports/paths outside
            // Moodle's default SSRF allowlist.
            $curl = new \curl(['ignoresecurity' => true]);
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('X-Livestream-Secret: ' . (string) ($config->realtimesecret ?? ''));
            $curl->post(rtrim($config->realtimeurl, '/') . '/publish', json_encode([
                'room' => $room,
                'event' => $event,
                'data' => $data,
            ]), [
                'CURLOPT_TIMEOUT' => 3,
                'CURLOPT_CONNECTTIMEOUT' => 2,
            ]);
        } catch (\Throwable $e) {
            debugging('mod_livestream: realtime publish to room ' . $room . ' failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }
}
