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
 * Thin client for the MediaMTX playback server (recordings).
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediamtx_client {

    /**
     * Checks whether a stream key is currently live by probing its HLS
     * manifest. Used both for the per-activity status check and anywhere
     * else that needs a liveness answer (course nav badge, stale-session
     * cleanup task).
     *
     * @param string $hlsbaseurl base URL of the HLS server, e.g. https://media.example.com
     * @param string $streamkey the activity stream key (HLS path)
     * @return bool true if the manifest is currently reachable
     */
    public static function is_live(string $hlsbaseurl, string $streamkey): bool {
        if (empty($hlsbaseurl) || $streamkey === '') {
            return false;
        }

        $url = rtrim($hlsbaseurl, '/') . '/' . $streamkey . '/index.m3u8';
        // ignoresecurity: the HLS base URL is a trusted admin setting, not
        // user input, and media servers commonly run on ports (8888, etc.)
        // outside Moodle's default SSRF allowlist (80/443). Without this the
        // probe is blocked and every stream looks offline.
        $curl = new \curl(['ignoresecurity' => true]);
        // GET, not HEAD, with the cookie engine on (CURLOPT_COOKIEFILE => ''):
        // MediaMTX guards the HLS manifest with a cookie-check redirect that a
        // HEAD request never satisfies (it would always look offline). The
        // manifest is only a few hundred bytes, so fetching it is cheap.
        $curl->get($url, [], [
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_COOKIEFILE' => '',
        ]);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        return ($httpcode >= 200 && $httpcode < 300);
    }

    /**
     * Returns a ready-to-play MP4 URL for the most recent recording of a
     * stream key, or '' if the playback server has none (or isn't reachable).
     *
     * The MediaMTX playback server (port 9996) lists finished recordings per
     * path and serves any time range as a single file. We ask for the latest
     * recorded session and build a whole-session download URL.
     *
     * @param string $playbackbase base URL of the playback server, e.g. https://media.example.com
     * @param string $streamkey the activity stream key (recording path)
     * @return string playable MP4 URL, or '' when unavailable
     */
    public static function latest_recording_url(string $playbackbase, string $streamkey): string {
        $playbackbase = rtrim($playbackbase, '/');
        if ($playbackbase === '' || $streamkey === '') {
            return '';
        }

        $listurl = $playbackbase . '/list?path=' . rawurlencode($streamkey);
        // ignoresecurity: the playback base URL is a trusted admin setting, and
        // the media server usually listens on a port (9996) outside Moodle's
        // default SSRF allowlist (80/443), which would otherwise block this.
        $curl = new \curl(['ignoresecurity' => true]);
        $response = $curl->get($listurl, [], [
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_FOLLOWLOCATION' => 1,
        ]);

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode < 200 || $httpcode >= 300) {
            return '';
        }

        $segments = json_decode($response);
        if (!is_array($segments) || empty($segments)) {
            return '';
        }

        // Entries are ordered oldest-first; the last one is the most recent lesson.
        $latest = end($segments);
        if (empty($latest->start) || !isset($latest->duration)) {
            return '';
        }

        return $playbackbase . '/get?' . http_build_query([
            'path' => $streamkey,
            'start' => $latest->start,
            'duration' => (int) ceil((float) $latest->duration),
            'format' => 'mp4',
        ]);
    }
}
