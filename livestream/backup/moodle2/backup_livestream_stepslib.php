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
 * Backup structure step for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the complete livestream structure for backup.
 */
class backup_livestream_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure.
     *
     * Note: streamkey, Zoom URLs and meeting ids are secrets tied to the
     * original site/meeting, so they are intentionally NOT backed up.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $livestream = new backup_nested_element('livestream', ['id'], [
            'name', 'intro', 'introformat', 'streamtype',
            'starttime', 'duration', 'recordingurl',
            'timecreated', 'timemodified',
        ]);

        $livestream->set_source_table('livestream', ['id' => backup::VAR_ACTIVITYID]);

        $livestream->annotate_files('mod_livestream', 'intro', null);

        return $this->prepare_activity_structure($livestream);
    }
}
