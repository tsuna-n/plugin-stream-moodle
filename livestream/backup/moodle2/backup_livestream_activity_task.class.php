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
 * Backup task for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/livestream/backup/moodle2/backup_livestream_stepslib.php');

/**
 * Provides the steps to perform one complete backup of a livestream instance.
 */
class backup_livestream_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the backup step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_livestream_activity_structure_step('livestream_structure', 'livestream.xml'));
    }

    /**
     * Encodes URLs to the module's view/index pages.
     *
     * @param string $content content to encode
     * @return string encoded content
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/livestream\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@LIVESTREAMINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/livestream\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@LIVESTREAMVIEWBYID*$2@$', $content);

        return $content;
    }
}
