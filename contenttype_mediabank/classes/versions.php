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
 * Earlier versions of a media bank item, kept for a while after the item is replaced so a bad replace can be undone.
 *
 * A version is a stored file in the plugin's own file area (component contenttype_mediabank, area versions, item id =
 * content id, file path /N/). It shares the old content with the file system, so keeping it costs no extra disk
 * space until the retention time passes. Versions carry no user id.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class versions {
    /** @var string File area holding the versions. */
    public const FILEAREA = 'versions';

    /** @var int Days to keep a replaced file when the admin has not chosen. */
    public const DEFAULT_RETENTION_DAYS = 30;

    /**
     * Days a replaced file is kept. 0 means replaced files are not kept.
     *
     * @return int
     */
    public static function get_retention_days(): int {
        $setting = get_config('contenttype_mediabank', 'versionretentiondays');
        if ($setting === false || $setting === null || $setting === '') {
            return self::DEFAULT_RETENTION_DAYS;
        }
        return max(0, (int) $setting);
    }

    /**
     * Keep the item's current file as a version, before it is replaced.
     *
     * @param \core_contentbank\content $content The item about to be replaced.
     * @param \stored_file $incoming The file that will replace it. If it is identical nothing is kept.
     * @return \stored_file|null The version, or null if nothing was kept.
     */
    public static function keep_current(\core_contentbank\content $content, \stored_file $incoming): ?\stored_file {
        $current = $content->get_file();
        if (!$current || self::get_retention_days() === 0 || $current->get_contenthash() === $incoming->get_contenthash()) {
            return null;
        }
        $next = 1;
        foreach (self::get_versions($content) as $number => $unused) {
            $next = max($next, $number + 1);
        }
        return get_file_storage()->create_file_from_storedfile([
            'contextid' => $current->get_contextid(),
            'component' => 'contenttype_mediabank',
            'filearea' => self::FILEAREA,
            'itemid' => $content->get_id(),
            'filepath' => "/$next/",
            'filename' => $current->get_filename(),
            'userid' => null,
            'timecreated' => time(),
        ], $current);
    }

    /**
     * The kept versions of an item, newest first.
     *
     * @param \core_contentbank\content $content The item.
     * @return \stored_file[] Version number => file.
     */
    public static function get_versions(\core_contentbank\content $content): array {
        $files = get_file_storage()->get_area_files(
            $content->get_contextid(),
            'contenttype_mediabank',
            self::FILEAREA,
            $content->get_id(),
            'filepath',
            false
        );
        $versions = [];
        foreach ($files as $file) {
            $versions[(int) trim($file->get_filepath(), '/')] = $file;
        }
        krsort($versions);
        return $versions;
    }

    /**
     * Put a kept version back as the item's current file. The file it replaces becomes a version itself, so this can
     * be undone.
     *
     * @param \core_contentbank\content $content The item.
     * @param int $number Version number from get_versions().
     * @return \core_contentbank\content
     * @throws \moodle_exception If there is no such version.
     */
    public static function restore(\core_contentbank\content $content, int $number): \core_contentbank\content {
        $versions = self::get_versions($content);
        if (!isset($versions[$number])) {
            throw new \moodle_exception('errornosuchversion', 'contenttype_mediabank', '', $number);
        }
        return $content->get_content_type_instance()->replace_content($versions[$number], $content);
    }

    /**
     * Delete versions older than the retention time.
     *
     * @return int Number of versions deleted.
     */
    public static function purge_expired(): int {
        global $DB;
        $cutoff = time() - self::get_retention_days() * DAYSECS;
        $fs = get_file_storage();
        $count = 0;
        $rows = $DB->get_records_select(
            'files',
            "component = ? AND filearea = ? AND filename <> '.' AND timecreated < ?",
            ['contenttype_mediabank', self::FILEAREA, $cutoff]
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

    /**
     * Delete every version of an item.
     *
     * @param \core_contentbank\content $content The item.
     */
    public static function delete_all(\core_contentbank\content $content): void {
        get_file_storage()->delete_area_files(
            $content->get_contextid(),
            'contenttype_mediabank',
            self::FILEAREA,
            $content->get_id()
        );
    }

    /**
     * Move every version of an item along with it.
     *
     * @param int $fromcontextid Context the item is leaving.
     * @param int $tocontextid Context it goes to.
     * @param int $contentid The item.
     */
    public static function move_all(int $fromcontextid, int $tocontextid, int $contentid): void {
        get_file_storage()->move_area_files_to_new_context(
            $fromcontextid,
            $tocontextid,
            'contenttype_mediabank',
            self::FILEAREA,
            $contentid
        );
    }
}
