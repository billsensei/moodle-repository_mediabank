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

namespace contenttype_mediabank\tests;

/**
 * Test helper: create a media bank item and links to it.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_helper {
    /**
     * Upload a file as a media bank item.
     *
     * @param string $filename File name.
     * @param string $data Content.
     * @param \context|null $context Where to put the item. Defaults to the system context.
     * @return \contenttype_mediabank\content
     */
    public static function create_item(string $filename, string $data, ?\context $context = null): \contenttype_mediabank\content {
        $context = $context ?? \context_system::instance();
        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ], $data);
        return (new \contenttype_mediabank\contenttype($context))->upload_content($draft);
    }

    /**
     * Create a link (file alias) to an item, the way the file picker does.
     *
     * @param \core_contentbank\content $item The item to link to.
     * @param \context $context Where the link lives, for example a course module context.
     * @param string $component Component owning the link.
     * @param string $filearea File area of the link.
     * @return \stored_file
     */
    public static function create_link(
        \core_contentbank\content $item,
        \context $context,
        string $component = 'mod_resource',
        string $filearea = 'content'
    ): \stored_file {
        $source = $item->get_file();
        $repos = \repository::get_instances(['type' => 'contentbank']);
        $repo = reset($repos);
        $reference = \file_storage::pack_reference([
            'contextid' => $source->get_contextid(),
            'component' => $source->get_component(),
            'filearea' => $source->get_filearea(),
            'itemid' => $source->get_itemid(),
            'filepath' => $source->get_filepath(),
            'filename' => $source->get_filename(),
        ]);
        return get_file_storage()->create_file_from_reference([
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $source->get_filename(),
        ], $repo->id, $reference);
    }
}
