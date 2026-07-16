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
 * Lets the current user manage their own Zoom Server-to-Server OAuth
 * credentials, used to create meetings for their livestream activities.
 *
 * Zoom credentials are personal rather than site-wide because different
 * teachers may hold entirely separate Zoom accounts/organisations.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/livestream/classes/form/zoom_account_form.php');

require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$url = new moodle_url('/mod/livestream/zoomaccount.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('zoomaccounttitle', 'mod_livestream'));
$PAGE->set_heading(get_string('zoomaccounttitle', 'mod_livestream'));

$delete = optional_param('delete', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if ($delete) {
    require_sesskey();
    if ($confirm) {
        \mod_livestream\local\zoom_account::delete($USER->id);
        redirect($url, get_string('zoomaccountremoved', 'mod_livestream'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('zoomaccountremoveconfirm', 'mod_livestream'),
        new moodle_url($url, ['delete' => 1, 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

$existing = \mod_livestream\local\zoom_account::get($USER->id);

$form = new \mod_livestream\form\zoom_account_form($url);
if ($existing) {
    $form->set_data($existing);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my/'));
} else if ($data = $form->get_data()) {
    \mod_livestream\local\zoom_account::save(
        $USER->id,
        trim($data->accountid),
        trim($data->clientid),
        trim($data->clientsecret)
    );
    redirect($url, get_string('zoomaccountsaved', 'mod_livestream'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

if ($existing) {
    $removeurl = new moodle_url($url, ['delete' => 1, 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::link($removeurl, get_string('zoomaccountremove', 'mod_livestream')),
        'mb-3'
    );
}

$form->display();
echo $OUTPUT->footer();
