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
 * Ephemeral live chat panel for mod_livestream.
 *
 * Polls for new messages every POLL_INTERVAL ms using a "since this message
 * id" cursor so it only ever fetches what's new, and appends them as plain
 * text nodes (never innerHTML) so injecting markup through a message is not
 * possible regardless of what reaches the DOM. Messages are not persisted
 * server-side past the end of the broadcast -- this panel does not attempt
 * to keep any history across a page reload either.
 *
 * @module     mod_livestream/chat
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    var POLL_INTERVAL = 3000;

    /**
     * Fetches messages newer than afterid.
     *
     * @param {Number} cmid course module id
     * @param {Number} afterid last message id already rendered
     * @return {Promise<Array>}
     */
    var fetchMessages = function(cmid, afterid) {
        return Ajax.call([{
            methodname: 'mod_livestream_get_chat_messages',
            args: {cmid: cmid, afterid: afterid}
        }])[0].then(function(response) {
            return response.messages;
        }).catch(function() {
            return [];
        });
    };

    /**
     * Initialises the chat panel.
     *
     * @param {Object} config {cmid, canmoderate}
     */
    var init = function(config) {
        var root = document.querySelector('[data-region="chat"][data-cmid="' + config.cmid + '"]');
        if (!root) {
            return;
        }
        var list = root.querySelector('[data-region="chat-messages"]');
        var form = root.querySelector('[data-region="chat-form"]');
        var input = root.querySelector('[data-region="chat-input"]');
        var lastId = 0;

        /**
         * Appends one message as plain text nodes.
         *
         * @param {Object} msg {id, userid, username, message, timecreated}
         */
        var appendMessage = function(msg) {
            var row = document.createElement('div');
            row.className = 'mod_livestream-chat-message mb-1';
            row.setAttribute('data-messageid', msg.id);

            var author = document.createElement('strong');
            author.textContent = msg.username + ': ';
            row.appendChild(author);

            var text = document.createElement('span');
            text.textContent = msg.message;
            row.appendChild(text);

            if (config.canmoderate) {
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'btn btn-sm btn-link text-danger p-0 ms-2';
                del.setAttribute('aria-label', 'delete');
                del.textContent = '×';
                del.addEventListener('click', function() {
                    Ajax.call([{
                        methodname: 'mod_livestream_delete_chat_message',
                        args: {cmid: config.cmid, messageid: msg.id}
                    }])[0].then(function() {
                        row.remove();
                        return null;
                    }).catch(function() {
                        return null;
                    });
                });
                row.appendChild(del);
            }

            list.appendChild(row);
            list.scrollTop = list.scrollHeight;
        };

        var poll = function() {
            fetchMessages(config.cmid, lastId).then(function(messages) {
                messages.forEach(function(msg) {
                    appendMessage(msg);
                    lastId = msg.id;
                });
                setTimeout(poll, POLL_INTERVAL);
                return null;
            }).catch(function() {
                setTimeout(poll, POLL_INTERVAL);
            });
        };

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var text = input.value.trim();
            if (!text) {
                return;
            }
            input.value = '';
            input.disabled = true;
            Ajax.call([{
                methodname: 'mod_livestream_send_chat_message',
                args: {cmid: config.cmid, message: text}
            }])[0].then(function() {
                input.disabled = false;
                input.focus();
                // Fetch immediately rather than waiting for the next tick,
                // so the sender sees their own message right away.
                return fetchMessages(config.cmid, lastId).then(function(messages) {
                    messages.forEach(function(msg) {
                        appendMessage(msg);
                        lastId = msg.id;
                    });
                    return null;
                });
            }).catch(function() {
                input.disabled = false;
                input.value = text;
            });
        });

        poll();
    };

    return {init: init};
});
