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

namespace contenttype_mediabank\external;

use contenttype_mediabank\captions;
use contenttype_mediabank\content;
use contenttype_mediabank\contenttype;
use contenttype_mediabank\upload_rules;
use core_contentbank\contentbank;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\util;

/**
 * What the media bank web service functions share: the access rules and the shape of an item.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item {
    /**
     * Check the caller may use the media bank in a context, and return the context and its content type.
     *
     * Core's search does not check this: the Content Bank screens do it before they search.
     *
     * @param int $contextid The context.
     * @return array [context, contenttype]
     * @throws \core\exception\moodle_exception If the media bank is not available there to this user.
     */
    public static function require_access(int $contextid): array {
        $context = \context::instance_by_id($contextid, MUST_EXIST);
        \core_external\external_api::validate_context($context);
        $enabled = array_key_exists('mediabank', \core_plugin_manager::instance()->get_enabled_plugins('contenttype') ?? []);
        $type = new contenttype($context);
        if (!$enabled || !(new contentbank())->is_context_allowed($context) || !$type->can_access()) {
            throw new \core\exception\moodle_exception('errornosuchitem', 'contenttype_mediabank');
        }
        return [$context, $type];
    }

    /**
     * Find an item the caller may see.
     *
     * A missing item, one of another content type and one the caller may not see all give the same error, so the
     * answer does not reveal what exists.
     *
     * @param int $id The item id.
     * @return array [content, contenttype]
     * @throws \core\exception\moodle_exception If there is no such item for this user.
     */
    public static function require_item(int $id): array {
        global $DB;
        $record = $DB->get_record('contentbank_content', ['id' => $id, 'contenttype' => 'contenttype_mediabank']);
        if (!$record) {
            throw new \core\exception\moodle_exception('errornosuchitem', 'contenttype_mediabank');
        }
        [, $type] = self::require_access($record->contextid);
        $content = new content($record);
        if (!$content->is_view_allowed()) {
            throw new \core\exception\moodle_exception('errornosuchitem', 'contenttype_mediabank');
        }
        return [$content, $type];
    }

    /**
     * The kind of an item: video, audio, pdf or image.
     *
     * @param content $content The item.
     * @return string
     */
    public static function get_kind(content $content): string {
        $kind = $content->get_metadata()['kind'] ?? null;
        if (!$kind) {
            $file = $content->get_file();
            $kind = $file ? upload_rules::get_kind(upload_rules::get_extension($file->get_filename())) : null;
        }
        return (string) $kind;
    }

    /**
     * The structure of one item in a result.
     *
     * @return external_single_structure
     */
    public static function structure(): external_single_structure {
        $nullable = fn($type, $desc) => new external_value($type, $desc, VALUE_DEFAULT, null, NULL_ALLOWED);
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Item id'),
            'name' => new external_value(PARAM_RAW, 'Name, formatted for display'),
            'contextid' => new external_value(PARAM_INT, 'Context the item lives in'),
            'kind' => new external_value(PARAM_ALPHA, 'video, audio, pdf or image'),
            'filename' => new external_value(PARAM_FILE, 'Name of the file'),
            'mimetype' => new external_value(PARAM_RAW, 'MIME type, from the file name'),
            'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
            'fileurl' => new external_value(PARAM_URL, 'Web service URL of the file. Add the token to fetch it'),
            'visibility' => new external_value(PARAM_INT, '1 public, 2 unlisted'),
            'alt' => new external_value(PARAM_RAW, 'Text alternative, empty if none'),
            'transcript' => new external_value(PARAM_RAW, 'Transcript, empty if none'),
            'captionsurl' => $nullable(PARAM_URL, 'Web service URL of the WebVTT captions, null if none'),
            'width' => $nullable(PARAM_INT, 'Image width in pixels, null if not an image'),
            'height' => $nullable(PARAM_INT, 'Image height in pixels, null if not an image'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time last modified'),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the caller can manage the item'),
        ]);
    }

    /**
     * Describe an item for a result.
     *
     * The creator is not included.
     *
     * @param content $content The item.
     * @param contenttype $type Content type for the item's context.
     * @return array
     */
    public static function export(content $content, contenttype $type): array {
        $context = \context::instance_by_id($content->get_contextid());
        $file = $content->get_file();
        $metadata = $content->get_metadata();
        $captions = captions::get($content);
        $url = fn(string $component, string $area, string $name) => \moodle_url::make_webservice_pluginfile_url(
            $context->id,
            $component,
            $area,
            $content->get_id(),
            '/',
            $name
        )->out(false);
        return [
            'id' => $content->get_id(),
            'name' => util::format_string($content->get_name(), $context),
            'contextid' => $context->id,
            'kind' => self::get_kind($content),
            'filename' => $file ? $file->get_filename() : '',
            'mimetype' => $file ? (string) $file->get_mimetype() : '',
            'filesize' => $file ? (int) $file->get_filesize() : 0,
            'fileurl' => $file ? $url('contentbank', 'public', $file->get_filename()) : '',
            'visibility' => (int) $content->get_visibility(),
            'alt' => $content->get_alt(),
            'transcript' => $content->get_transcript(),
            'captionsurl' => $captions ? $url('contenttype_mediabank', captions::FILEAREA, captions::FILENAME) : null,
            'width' => isset($metadata['width']) ? (int) $metadata['width'] : null,
            'height' => isset($metadata['height']) ? (int) $metadata['height'] : null,
            'timecreated' => (int) $content->get_content()->timecreated,
            'timemodified' => (int) $content->get_timemodified(),
            'canmanage' => $type->can_manage($content),
        ];
    }
}
