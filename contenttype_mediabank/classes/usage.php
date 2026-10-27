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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace contenttype_mediabank;

/**
 * Where a media bank item is linked from, and converting those links to independent copies.
 *
 * A link is a core file alias (file reference) that points at the bank item. Aliases sitting in a user's draft area
 * are not counted, because they only exist while a form is being edited.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /**
     * The core reference string that aliases of this item carry.
     *
     * @param \core_contentbank\content $content The item.
     * @return string|null Null if the item has no file.
     */
    protected static function get_reference(\core_contentbank\content $content): ?string {
        $file = $content->get_file();
        if (!$file) {
            return null;
        }
        return \file_storage::pack_reference([
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
        ]);
    }

    /**
     * The files that link to the item.
     *
     * @param \core_contentbank\content $content The item.
     * @return \stored_file[]
     */
    public static function get_links(\core_contentbank\content $content): array {
        $reference = self::get_reference($content);
        return $reference === null ? [] : array_values(get_file_storage()->search_references($reference));
    }

    /**
     * How many links point at the item.
     *
     * @param \core_contentbank\content $content The item.
     * @return int
     */
    public static function count_links(\core_contentbank\content $content): int {
        $reference = self::get_reference($content);
        return $reference === null ? 0 : get_file_storage()->search_references_count($reference);
    }

    /**
     * The links grouped by course.
     *
     * @param \core_contentbank\content $content The item.
     * @return \stdClass[] Course id => object with id, links, and (for a course) the course record fields fullname and
     *      shortname. Links that are not inside a course are grouped under id 0.
     */
    public static function get_courses(\core_contentbank\content $content): array {
        global $DB;
        $courses = [];
        foreach (self::get_links($content) as $link) {
            $context = \context::instance_by_id($link->get_contextid(), IGNORE_MISSING);
            $coursecontext = $context ? $context->get_course_context(false) : false;
            $id = $coursecontext ? (int) $coursecontext->instanceid : 0;
            if (!isset($courses[$id])) {
                $record = $id ? $DB->get_record('course', ['id' => $id], 'id, fullname, shortname, category, visible') : false;
                $courses[$id] = $record ?: (object) ['id' => 0];
                $courses[$id]->links = 0;
            }
            $courses[$id]->links++;
        }
        return $courses;
    }

    /**
     * Turn every link to the item into an independent copy.
     *
     * Used before an item is deleted or moved, so that no course loses the file.
     *
     * @param \core_contentbank\content $content The item.
     * @return int Number of links converted.
     */
    public static function detach_all(\core_contentbank\content $content): int {
        $count = 0;
        $fs = get_file_storage();
        foreach (self::get_links($content) as $link) {
            // A link nobody has opened yet is stored without its content. Sync it first, or the copy would be empty.
            $link->sync_external_file();
            // Core copies the item's current content into the link, then drops the reference.
            $fs->import_external_file($link);
            $count++;
        }
        return $count;
    }
}
