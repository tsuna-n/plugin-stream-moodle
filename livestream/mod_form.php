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
 * Activity creation/editing form for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/livestream/lib.php');

/**
 * Settings form for the livestream activity.
 */
class mod_livestream_mod_form extends moodleform_mod {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('livestreamname', 'mod_livestream'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'streamsettings', get_string('streamsettings', 'mod_livestream'));
        $mform->setExpanded('streamsettings');

        $types = [
            LIVESTREAM_TYPE_OBS => get_string('typeobs', 'mod_livestream'),
            LIVESTREAM_TYPE_ZOOM => get_string('typezoom', 'mod_livestream'),
        ];
        $mform->addElement('select', 'streamtype', get_string('streamtype', 'mod_livestream'), $types);
        $mform->addHelpButton('streamtype', 'streamtype', 'mod_livestream');
        $mform->setDefault('streamtype', LIVESTREAM_TYPE_OBS);

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'mod_livestream'),
            ['optional' => true]);
        $mform->addHelpButton('starttime', 'starttime', 'mod_livestream');

        $mform->addElement('duration', 'duration', get_string('streamduration', 'mod_livestream'),
            ['defaultunit' => MINSECS, 'optional' => false]);
        $mform->setDefault('duration', 3600);

        $mform->addElement('text', 'zoompasscode', get_string('zoompasscode', 'mod_livestream'), ['size' => 16]);
        $mform->setType('zoompasscode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('zoompasscode', 'zoompasscode', 'mod_livestream');
        $mform->hideIf('zoompasscode', 'streamtype', 'neq', LIVESTREAM_TYPE_ZOOM);

        $mform->addElement('text', 'recordingurl', get_string('recordingurl', 'mod_livestream'), ['size' => 64]);
        $mform->setType('recordingurl', PARAM_URL);
        $mform->addHelpButton('recordingurl', 'recordingurl', 'mod_livestream');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validates the form.
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['streamtype'] === LIVESTREAM_TYPE_ZOOM) {
            $config = get_config('mod_livestream');
            if (empty($config->zoomaccountid) || empty($config->zoomclientid) || empty($config->zoomclientsecret)) {
                $errors['streamtype'] = get_string('errorzoomnotconfigured', 'mod_livestream');
            }
        } else {
            $config = get_config('mod_livestream');
            if (empty($config->rtmpserver) || empty($config->hlsbaseurl)) {
                $errors['streamtype'] = get_string('errorobsnotconfigured', 'mod_livestream');
            }
        }

        return $errors;
    }
}
