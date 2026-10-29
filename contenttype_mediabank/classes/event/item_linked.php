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

namespace contenttype_mediabank\event;

use core\event\base;

/**
 * A media bank item was linked from somewhere else, such as a File resource in a course.
 *
 * The event's context is where the link is. Aliases made by a restore are not reported: core creates them without telling
 * anyone.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_linked extends base {
    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'contentbank_content';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Create the event for a new link.
     *
     * @param \core_contentbank\content $item The item that is linked.
     * @param \stored_file $link The link.
     * @return self
     */
    public static function create_from_link(\core_contentbank\content $item, \stored_file $link): self {
        return self::create([
            'objectid' => $item->get_id(),
            'context' => \context::instance_by_id($link->get_contextid()),
            'other' => [
                'component' => $link->get_component(),
                'filearea' => $link->get_filearea(),
                'itemcontextid' => (int) $item->get_contextid(),
            ],
        ]);
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventitemlinked', 'contenttype_mediabank');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' linked the media bank item with id '$this->objectid' from the context " .
            "with id '$this->contextid' (component '{$this->other['component']}', file area '{$this->other['filearea']}').";
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        foreach (['component', 'filearea', 'itemcontextid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/contentbank/view.php', ['id' => $this->objectid]);
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'contentbank_content', 'restore' => base::NOT_MAPPED];
    }

    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}
