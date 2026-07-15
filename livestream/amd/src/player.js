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
 * HLS live player with live-status polling for mod_livestream.
 *
 * Polls the server every POLL_INTERVAL ms for as long as the page is open --
 * before going live, and continuously while live too (not just before).
 * Attaches hls.js (or native HLS on Safari) to the <video> element once live;
 * if the server-side probe reports offline again, or playback fatally errors
 * client-side, it goes back to showing the offline state (the poll loop
 * itself never stops, so either signal is picked up within one interval).
 * Continuing to poll while live also doubles as this activity's "still
 * watching" signal for attendance tracking, server-side.
 *
 * @module     mod_livestream/player
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var POLL_INTERVAL = 10000;

    /**
     * Loads hls.js from the configured URL exactly once.
     *
     * @param {String} url script URL
     * @return {Promise} resolves when window.Hls is available
     */
    var loadHlsJs = function(url) {
        if (window.Hls) {
            return Promise.resolve();
        }
        return new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.onload = resolve;
            script.onerror = function() {
                reject(new Error('Could not load hls.js from ' + url));
            };
            document.head.appendChild(script);
        });
    };

    /**
     * Asks Moodle whether the stream is live, and for the latest recording URL
     * when it is not (both resolved server-side).
     *
     * @param {Number} cmid course module id
     * @return {Promise<Object>} {live: Boolean, recordingurl: String}
     */
    var fetchStatus = function(cmid) {
        return Ajax.call([{
            methodname: 'mod_livestream_get_stream_status',
            args: {cmid: cmid}
        }])[0].then(function(response) {
            return response;
        }).catch(function() {
            return {live: false, recordingurl: ''};
        });
    };

    /**
     * Attaches the HLS source to the video element.
     *
     * @param {HTMLVideoElement} video the video element
     * @param {String} hlsurl manifest URL
     * @param {String} hlsjsurl hls.js script URL
     * @param {Function} onFatal called when playback dies (stream ended)
     * @return {Promise}
     */
    var attachPlayer = function(video, hlsurl, hlsjsurl, onFatal) {
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS (Safari, iOS).
            video.src = hlsurl;
            video.addEventListener('error', onFatal);
            video.play().catch(function() {
                // Autoplay blocked; user will press play themselves.
                return;
            });
            return Promise.resolve();
        }

        return loadHlsJs(hlsjsurl).then(function() {
            if (!window.Hls.isSupported()) {
                throw new Error('HLS is not supported in this browser');
            }
            var hls = new window.Hls({liveSyncDurationCount: 3});
            hls.loadSource(hlsurl);
            hls.attachMedia(video);
            hls.on(window.Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(function() {
                    return;
                });
            });
            hls.on(window.Hls.Events.ERROR, function(event, data) {
                if (data.fatal) {
                    hls.destroy();
                    onFatal();
                }
            });
            return null;
        });
    };

    /**
     * Initialises the player + polling loop.
     *
     * @param {Object} config {cmid, hlsurl, hlsjsurl}
     */
    var init = function(config) {
        var root = document.getElementById('livestream-status-' + config.cmid);
        if (!root) {
            return;
        }
        var container = root.closest('.mod_livestream-view');
        var badge = root.querySelector('[data-region="status-badge"]');
        var playerWrap = container.querySelector('[data-region="player-wrap"]');
        var offlineMsg = container.querySelector('[data-region="offline-message"]');
        var recordingRegion = container.querySelector('[data-region="recording"]');
        var recordingLink = container.querySelector('[data-region="recording-link"]');
        var video = document.getElementById('livestream-video-' + config.cmid);
        var playing = false;

        var strings = Str.get_strings([
            {key: 'statuslive', component: 'mod_livestream'},
            {key: 'statusoffline', component: 'mod_livestream'}
        ]);

        var setLive = function(live) {
            strings.then(function(s) {
                badge.textContent = live ? s[0] : s[1];
                badge.className = live
                    ? 'badge badge-danger bg-danger mod_livestream-live'
                    : 'badge badge-secondary bg-secondary';
                return null;
            }).catch(function() {
                return null;
            });
            playerWrap.style.display = live ? '' : 'none';
            offlineMsg.style.display = live ? 'none' : '';
        };

        // Shows the "watch recording" link when a replay URL is available and
        // the stream is not live; hides it otherwise.
        var setRecording = function(url) {
            if (!recordingRegion) {
                return;
            }
            if (url) {
                if (recordingLink) {
                    recordingLink.href = url;
                }
                recordingRegion.style.display = '';
            } else {
                recordingRegion.style.display = 'none';
            }
        };

        // Fires on a client-side fatal error (hls.js, or the native <video>
        // element). Only updates state/UI -- the perpetual poll() loop below
        // is always running and will pick up the offline state on its own
        // next tick, so this does not need to (and must not) start a second,
        // overlapping timer chain.
        var onStreamDied = function() {
            playing = false;
            setLive(false);
        };

        var poll = function() {
            fetchStatus(config.cmid).then(function(status) {
                if (status.live) {
                    if (!playing) {
                        playing = true;
                        setLive(true);
                        setRecording('');
                        attachPlayer(video, config.hlsurl, config.hlsjsurl, onStreamDied);
                    }
                    // Already playing: this tick is just confirming still-live
                    // (and, server-side, an attendance heartbeat) -- no UI change.
                } else {
                    // Server-side probe is authoritative: notice the stream
                    // ended even if the player itself never raised an error.
                    playing = false;
                    setLive(false);
                    setRecording(status.recordingurl);
                }
                setTimeout(poll, POLL_INTERVAL);
                return null;
            }).catch(function() {
                playing = false;
                setLive(false);
                setTimeout(poll, POLL_INTERVAL);
            });
        };

        poll();
    };

    return {init: init};
});
