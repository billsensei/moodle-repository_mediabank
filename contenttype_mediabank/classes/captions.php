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
 * The caption file (WebVTT) kept with a video or audio item.
 *
 * Stored in the plugin's own file area, one file per item. Captions belong to the bank item, so a course that links
 * the file plays it without them until a renderer shows them there.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class captions {
    /** @var string File area of the caption files. */
    public const FILEAREA = 'captions';

    /** @var string Name every caption file is stored under. */
    public const FILENAME = 'captions.vtt';

    /** @var int Largest caption file accepted, in bytes. */
    public const MAXBYTES = 1048576;

    /**
     * Whether a file is a WebVTT file.
     *
     * @param \stored_file $file The file to check.
     * @return bool
     */
    public static function is_valid(\stored_file $file): bool {
        if ($file->get_filesize() === 0 || $file->get_filesize() > self::MAXBYTES) {
            return false;
        }
        $start = (string) $file->get_content();
        // The signature may follow a byte order mark and must be followed by a space, tab or line break.
        return preg_match('/^(?:\xEF\xBB\xBF)?WEBVTT(?:[ \t\r\n]|$)/', $start) === 1;
    }

    /**
     * The caption file of an item.
     *
     * @param \core_contentbank\content $content The item.
     * @return \stored_file|null
     */
    public static function get(\core_contentbank\content $content): ?\stored_file {
        $file = get_file_storage()->get_file(
            $content->get_contextid(),
            'contenttype_mediabank',
            self::FILEAREA,
            $content->get_id(),
            '/',
            self::FILENAME
        );
        return $file ?: null;
    }

    /**
     * Replace the caption file of an item.
     *
     * @param \core_contentbank\content $content The item.
     * @param \stored_file $source A valid WebVTT file.
     * @return \stored_file The stored caption file.
     */
    public static function save(\core_contentbank\content $content, \stored_file $source): \stored_file {
        self::delete($content);
        return get_file_storage()->create_file_from_storedfile([
            'contextid' => $content->get_contextid(),
            'component' => 'contenttype_mediabank',
            'filearea' => self::FILEAREA,
            'itemid' => $content->get_id(),
            'filepath' => '/',
            'filename' => self::FILENAME,
            'userid' => null,
            'author' => null,
            'source' => null,
        ], $source);
    }

    /**
     * Remove the caption file of an item.
     *
     * @param \core_contentbank\content $content The item.
     */
    public static function delete(\core_contentbank\content $content): void {
        get_file_storage()->delete_area_files(
            $content->get_contextid(),
            'contenttype_mediabank',
            self::FILEAREA,
            $content->get_id()
        );
    }

    /**
     * Move the caption file of an item along with it.
     *
     * @param int $fromcontextid Context the item is leaving.
     * @param int $tocontextid Context it goes to.
     * @param int $contentid The item.
     */
    public static function move(int $fromcontextid, int $tocontextid, int $contentid): void {
        get_file_storage()->move_area_files_to_new_context(
            $fromcontextid,
            $tocontextid,
            'contenttype_mediabank',
            self::FILEAREA,
            $contentid
        );
    }

    /**
     * Delete caption files whose item no longer exists (core's privacy deletion does not call the content type).
     *
     * @return int Number of files deleted.
     */
    public static function purge_orphans(): int {
        global $DB;
        $fs = get_file_storage();
        $count = 0;
        $rows = $DB->get_records_select(
            'files',
            "component = :component AND filearea = :filearea AND filename <> '.'
                AND NOT EXISTS (SELECT 1 FROM {contentbank_content} cb WHERE cb.id = {files}.itemid)",
            ['component' => 'contenttype_mediabank', 'filearea' => self::FILEAREA]
        );
        foreach ($rows as $row) {
            $file = $fs->get_file_by_id($row->id);
            if ($file) {
                $file->delete();
                $count++;
            }
        }
        return $count;
    }
}
