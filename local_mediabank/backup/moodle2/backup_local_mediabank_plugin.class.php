<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use local_mediabank\embedded_links;

/**
 * Backup of the files behind media bank links, when the admin has asked for it.
 *
 * @package    local_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_mediabank_plugin extends backup_local_plugin {
    /**
     * Links in the course's own files, such as the summary.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        return $this->define_embedded();
    }

    /**
     * Links in an activity's files, such as a File resource.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        return $this->define_embedded();
    }

    /**
     * Describe the embedded files for the backup XML.
     *
     * @return backup_plugin_element
     */
    private function define_embedded() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $file = new backup_nested_element('embeddedfile', ['aliasid'], ['contenthash', 'filename', 'filesize']);
        $plugin->add_child($wrapper);
        $wrapper->add_child($file);
        $file->set_source_array(embedded_links::embed_in_backup($this->task->get_backupid(), $this->task->get_contextid()));
        return $plugin;
    }
}
