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

use contenttype_mediabank\task\optimize_video;

/**
 * Makes the videos of the media bank ready for streaming, in the background.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_optimizer {
    /** @var string File area holding the optimized file for the moment before it replaces the item's file. */
    protected const WORKAREA = 'optimizing';

    /** Result: the file was changed. */
    public const OPTIMIZED = 'optimized';

    /** Result: the file was already fine, or is not a kind this changes. */
    public const NOTHING_TO_DO = 'nothing';

    /** Result: the item is gone or its file changed while this ran. */
    public const GONE = 'gone';

    /** Result: the file could not be optimized and was left as it was. */
    public const FAILED = 'failed';

    /**
     * Whether new videos are optimized. On unless an admin turned it off.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $setting = get_config('contenttype_mediabank', 'optimizevideo');
        return $setting === false || $setting === null || $setting === '' ? true : (bool) $setting;
    }

    /**
     * Whether a file is one this can change. Only the file name is looked at, so this is cheap.
     *
     * @param \stored_file|null $file The item's file.
     * @return bool
     */
    public static function is_candidate(?\stored_file $file): bool {
        return $file && upload_rules::get_extension($file->get_filename()) === 'mp4';
    }

    /**
     * Ask for an item's video to be optimized when cron next runs. Nothing happens for other kinds of file, or when the
     * setting is off.
     *
     * @param \core_contentbank\content $content The item.
     * @return bool Whether a task was queued.
     */
    public static function queue(\core_contentbank\content $content): bool {
        if (!self::is_enabled() || !self::is_candidate($content->get_file())) {
            return false;
        }
        $task = new optimize_video();
        $task->set_component('contenttype_mediabank');
        $task->set_custom_data(['contentid' => $content->get_id()]);
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /**
     * The items whose video is an MP4 with its index last, so it is not ready for streaming.
     *
     * @return \core_contentbank\content[] Item id => item.
     */
    public static function find_unprepared(): array {
        global $DB;
        $found = [];
        $records = $DB->get_recordset('contentbank_content', ['contenttype' => 'contenttype_mediabank'], 'id');
        foreach ($records as $record) {
            $content = new content($record);
            $file = $content->get_file();
            if (!self::is_candidate($file) || !($handle = $file->get_content_file_handle())) {
                continue;
            }
            if (faststart::needs_optimizing($handle, $file->get_filesize())) {
                $found[$record->id] = $content;
            }
            fclose($handle);
        }
        $records->close();
        return $found;
    }

    /**
     * Ask for every video in the bank that needs it to be optimized. For videos uploaded before the setting existed.
     *
     * @return int Number of items queued.
     */
    public static function queue_all(): int {
        $queued = 0;
        foreach (self::find_unprepared() as $content) {
            $queued += (int) self::queue($content);
        }
        return $queued;
    }

    /**
     * Optimize one item's video now. The old file is kept as a version, as for any replacement.
     *
     * @param int $contentid The item.
     * @return string One of the result constants.
     */
    public static function optimize(int $contentid): string {
        global $DB;
        $record = $DB->get_record('contentbank_content', ['id' => $contentid, 'contenttype' => 'contenttype_mediabank']);
        if (!$record) {
            return self::GONE;
        }
        $content = new content($record);
        $file = $content->get_file();
        if (!self::is_candidate($file)) {
            return self::NOTHING_TO_DO;
        }
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            return self::FAILED;
        }
        $hash = $file->get_contenthash();
        $size = $file->get_filesize();
        $path = make_request_directory() . '/optimized.mp4';
        try {
            if (!faststart::needs_optimizing($handle, $size)) {
                return self::NOTHING_TO_DO;
            }
            raise_memory_limit(MEMORY_EXTRA);
            faststart::rewrite($handle, $size, $path);
        } catch (\RuntimeException $e) {
            mtrace('Media bank item ' . $contentid . ' was left as it is: ' . $e->getMessage());
            return self::FAILED;
        } finally {
            fclose($handle);
        }

        $fs = get_file_storage();
        $work = $fs->create_file_from_pathname([
            'contextid' => $content->get_contextid(),
            'component' => 'contenttype_mediabank',
            'filearea' => self::WORKAREA,
            'itemid' => $contentid,
            'filepath' => '/',
            'filename' => $file->get_filename(),
        ], $path);
        @unlink($path);
        try {
            // The item may have been replaced or deleted while the copy was made. Only swap in the file it was made from.
            $current = $DB->record_exists('contentbank_content', ['id' => $contentid])
                ? (new content($DB->get_record('contentbank_content', ['id' => $contentid], '*', MUST_EXIST)))->get_file()
                : null;
            if (!$current || $current->get_contenthash() !== $hash) {
                return self::GONE;
            }
            $modifier = $record->usermodified;
            $content = $content->get_content_type_instance()->replace_content($work, $content);
            // The change is not an edit by anyone. Keep the name of the person who last changed the item.
            $DB->set_field('contentbank_content', 'usermodified', $modifier, ['id' => $contentid]);
            usage::sync_links($content);
        } finally {
            $fs->delete_area_files($content->get_contextid(), 'contenttype_mediabank', self::WORKAREA, $contentid);
        }
        return self::OPTIMIZED;
    }
}
