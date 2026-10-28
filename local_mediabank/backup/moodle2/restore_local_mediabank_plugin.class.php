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
 * Restore of the files behind media bank links.
 *
 * @package    local_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_mediabank_plugin extends restore_local_plugin {
    /**
     * Files embedded for links in the course's own files.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [new restore_path_element('mediabank_embedded', $this->get_pathfor('/embeddedfile'))];
    }

    /**
     * Files embedded for links in an activity's files.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('mediabank_embedded', $this->get_pathfor('/embeddedfile'))];
    }

    /**
     * Remember an embedded file until core's queue of links is known.
     *
     * @param array $data The embedded file element.
     */
    public function process_mediabank_embedded($data) {
        embedded_links::remember($this->get_restoreid(), $data);
    }

    /**
     * Restore the course's links that core cannot restore as copies.
     */
    public function after_restore_course() {
        $this->restore_copies();
    }

    /**
     * Restore the activity's links that core cannot restore as copies.
     */
    public function after_restore_module() {
        $this->restore_copies();
    }

    /**
     * Restore copies for every queued link that needs one.
     *
     * Core runs after_restore methods once every activity has queued its links and before it processes the queue, so this
     * sees all links. It is safe to run once per connection point: a handled link is taken off the queue.
     */
    private function restore_copies(): void {
        $restored = embedded_links::restore_copies(
            $this->get_restoreid(),
            $this->task->get_basepath(),
            $this->task->is_samesite()
        );
        foreach ($restored as $name) {
            $this->step->log('restored media bank link as a copy: ' . $name, backup::LOG_INFO);
        }
    }
}
