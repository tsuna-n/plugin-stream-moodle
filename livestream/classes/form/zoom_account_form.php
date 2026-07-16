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

namespace mod_livestream\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Lets a teacher enter their own Zoom Server-to-Server OAuth app credentials.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_account_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('static', 'zoomaccountintro', '', get_string('zoomaccountintro', 'mod_livestream'));

        $mform->addElement('text', 'accountid', get_string('settingzoomaccountid', 'mod_livestream'), ['size' => 40]);
        $mform->setType('accountid', PARAM_TEXT);
        $mform->addRule('accountid', null, 'required', null, 'client');

        $mform->addElement('text', 'clientid', get_string('settingzoomclientid', 'mod_livestream'), ['size' => 40]);
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addRule('clientid', null, 'required', null, 'client');

        $mform->addElement('passwordunmask', 'clientsecret', get_string('settingzoomclientsecret', 'mod_livestream'),
            ['size' => 40]);
        $mform->setType('clientsecret', PARAM_TEXT);
        $mform->addRule('clientsecret', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('zoomaccountsave', 'mod_livestream'));
    }
}
