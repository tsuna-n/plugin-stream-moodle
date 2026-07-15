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
 * Decorates the course navigation "Live streams" link with a live badge.
 *
 * Runs on every course page (not just the activity page), so it never
 * blocks page render on a media-server round trip: the nav link itself is
 * rendered statically server-side, and this only polls afterwards to show
 * or hide a small badge.
 *
 * @module     mod_livestream/navbadge
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var POLL_INTERVAL = 20000;

    /**
     * Asks Moodle whether any embeddable stream in the course is live.
     *
     * @param {Number} courseid course id
     * @return {Promise<Object>} {live: Boolean, cmid: Number}
     */
    var fetchStatus = function(courseid) {
        return Ajax.call([{
            methodname: 'mod_livestream_get_course_live_status',
            args: {courseid: courseid}
        }])[0].then(function(response) {
            return response;
        }).catch(function() {
            return {live: false, cmid: 0};
        });
    };

    /**
     * Initialises the nav badge poller.
     *
     * @param {Object} config {courseid}
     */
    var init = function(config) {
        var link = document.querySelector('li[data-key="livestreams"] a.nav-link');
        if (!link) {
            return;
        }

        var badge = document.createElement('span');
        badge.className = 'badge badge-danger bg-danger ms-1 mod_livestream-navlive';
        badge.style.display = 'none';
        link.appendChild(badge);

        Str.get_string('coursenavlive', 'mod_livestream').then(function(s) {
            badge.textContent = s;
            return null;
        }).catch(function() {
            return null;
        });

        var poll = function() {
            fetchStatus(config.courseid).then(function(status) {
                badge.style.display = status.live ? '' : 'none';
                setTimeout(poll, POLL_INTERVAL);
                return null;
            }).catch(function() {
                setTimeout(poll, POLL_INTERVAL);
            });
        };

        poll();
    };

    return {init: init};
});
