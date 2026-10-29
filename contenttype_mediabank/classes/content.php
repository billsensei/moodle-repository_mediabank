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

namespace contenttype_mediabank;

/**
 * Media bank content manager class.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \core_contentbank\content {
    /** @var int Most characters a transcript may have. */
    public const MAX_TRANSCRIPT_LENGTH = 100000;

    /**
     * Facts kept with the item: kind, image size, and later alt text, transcript and so on.
     *
     * @return array
     */
    public function get_metadata(): array {
        $decoded = json_decode((string) $this->get_configdata(), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Add or change facts kept with the item. Other keys are left alone.
     *
     * @param array $values Keys to set. A null value removes the key.
     * @return bool
     */
    public function merge_metadata(array $values): bool {
        $metadata = array_filter(array_merge($this->get_metadata(), $values), fn($value) => $value !== null);
        return $this->set_configdata(json_encode($metadata));
    }

    /**
     * Text alternative of an image item.
     *
     * @return string
     */
    public function get_alt(): string {
        return (string) ($this->get_metadata()['alt'] ?? '');
    }

    /**
     * Written transcript of a video or audio item.
     *
     * @return string
     */
    public function get_transcript(): string {
        return (string) ($this->get_metadata()['transcript'] ?? '');
    }
}
