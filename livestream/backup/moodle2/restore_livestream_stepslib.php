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
 * Restore structure step for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the restore structure and processing for livestream.
 */
class restore_livestream_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines the restore structure.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        return $this->prepare_activity_structure([
            new restore_path_element('livestream', '/activity/livestream'),
        ]);
    }

    /**
     * Processes a livestream record.
     *
     * @param array $data the record data
     */
    protected function process_livestream($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // Secrets are never restored: issue a fresh stream key, and leave the
        // Zoom fields empty so a teacher re-creates the meeting by saving.
        $data->streamkey = bin2hex(random_bytes(16));
        $data->zoommeetingid = '';
        $data->zoomjoinurl = '';
        $data->zoomstarturl = '';
        $data->zoompasscode = '';

        $newid = $DB->insert_record('livestream', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_livestream', 'intro', null);
    }
}
