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
 * The links to a media bank item were turned into independent copies.
 *
 * This happens when someone asks for it on the edit form, and automatically when an item is moved or deleted so that no
 * course loses its file. The event right after it, a move or a delete, says which.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class links_converted extends base {
    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'contentbank_content';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Create the event.
     *
     * @param \core_contentbank\content $item The item whose links were converted.
     * @param int $count How many links.
     * @return self
     */
    public static function create_from_item(\core_contentbank\content $item, int $count): self {
        return self::create([
            'objectid' => $item->get_id(),
            'context' => \context::instance_by_id($item->get_contextid()),
            'other' => ['count' => $count],
        ]);
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventlinksconverted', 'contenttype_mediabank');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' converted {$this->other['count']} link(s) to the media bank item with id " .
            "'$this->objectid' into copies.";
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['count'])) {
            throw new \coding_exception("The 'count' value must be set in other.");
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
