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
 * Restore task for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/livestream/backup/moodle2/restore_livestream_stepslib.php');

/**
 * Provides the steps to perform one complete restore of a livestream instance.
 */
class restore_livestream_activity_task extends restore_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the restore step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_livestream_activity_structure_step('livestream_structure', 'livestream.xml'));
    }

    /**
     * Defines the decoding contents.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('livestream', ['intro'], 'livestream'),
        ];
    }

    /**
     * Defines the decoding rules for links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('LIVESTREAMINDEX', '/mod/livestream/index.php?id=$1', 'course'),
            new restore_decode_rule('LIVESTREAMVIEWBYID', '/mod/livestream/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * Defines the restore log rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('livestream', 'view', 'view.php?id={course_module}', '{livestream}'),
        ];
    }
}
