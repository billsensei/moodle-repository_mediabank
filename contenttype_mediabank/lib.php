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

/**
 * Library functions for the media bank content type.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve the files this plugin keeps next to an item: captions and earlier versions.
 *
 * Captions are sent inline to anyone who may see the item. Earlier versions are always downloaded and only to people
 * who may manage the item. The current file itself is served by core and {@see \contenttype_mediabank\contenttype}.
 *
 * @param stdClass|null $course Course record.
 * @param stdClass|null $cm Course module record.
 * @param context $context File context.
 * @param string $filearea File area.
 * @param array $args Item id, subdirectories and file name.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Options for send_stored_file().
 * @return bool False if the file cannot be found. Otherwise the request ends here.
 */
function contenttype_mediabank_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if (!in_array($filearea, [\contenttype_mediabank\captions::FILEAREA, \contenttype_mediabank\versions::FILEAREA], true)) {
        return false;
    }
    if ($context->contextlevel == CONTEXT_COURSE) {
        require_login($course);
    } else if ($context->contextlevel == CONTEXT_SYSTEM || $context->contextlevel == CONTEXT_COURSECAT) {
        require_login();
    } else {
        return false;
    }
    if (isguestuser()) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // The item must exist, be a media bank item and live in the context the URL names.
    $record = $DB->get_record('contentbank_content', ['id' => $itemid, 'contenttype' => 'contenttype_mediabank']);
    if (!$record || (int) $record->contextid !== (int) $context->id) {
        return false;
    }
    $contenttype = new \contenttype_mediabank\contenttype($context);
    $content = new \contenttype_mediabank\content($record);
    // Unlisted items are hidden from people who may not see unlisted content, as on the item page.
    if (!$contenttype->can_access() || !$content->is_view_allowed()) {
        return false;
    }

    if ($filearea === \contenttype_mediabank\versions::FILEAREA) {
        if (!$contenttype->can_manage($content)) {
            return false;
        }
        $forcedownload = true;
    } else if ($filepath !== '/' || $filename !== \contenttype_mediabank\captions::FILENAME) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'contenttype_mediabank', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    \core\session\manager::write_close();
    header('X-Content-Type-Options: nosniff');
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
