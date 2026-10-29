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

use contenttype_mediabank\event\item_linked;
use core_files\hook\after_file_created;

/**
 * Reacts to core hooks.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Report a new link to a media bank item.
     *
     * Core calls this for every file created, so it leaves at once for anything that is not a link. A new link is reported
     * and given the item's content. Core's file picker
     * first makes a link in the user's draft area; the real link is made when the form is saved, and only that one is
     * reported.
     *
     * @param after_file_created $hook The hook.
     */
    public static function after_file_created(after_file_created $hook): void {
        global $DB;
        $record = $hook->filerecord;
        if (empty($record->referencefileid)) {
            return;
        }
        if ($record->component === 'user' && $record->filearea === 'draft') {
            return;
        }
        $reference = $DB->get_field_sql(
            "SELECT fr.reference
               FROM {files_reference} fr
               JOIN {repository_instances} ri ON ri.id = fr.repositoryid
               JOIN {repository} r ON r.id = ri.typeid
              WHERE fr.id = :id AND r.type = 'contentbank'",
            ['id' => $record->referencefileid]
        );
        if (!$reference) {
            return;
        }
        try {
            $source = \file_storage::unpack_reference($reference);
        } catch (\Exception $e) {
            return;
        }
        if ($source['component'] !== 'contentbank' || $source['filearea'] !== 'public') {
            return;
        }
        $item = $DB->get_record('contentbank_content', ['id' => $source['itemid'], 'contenttype' => 'contenttype_mediabank']);
        if ($item) {
            // Core calls this while it saves a file. A failure to log the link must not stop the file being saved.
            try {
                $content = new content($item);
                item_linked::create_from_link($content, $hook->storedfile)->trigger();
                // Give the link the item's content now. A link nobody has opened would otherwise be empty if the item
                // were erased, and core keeps whatever the link holds.
                usage::sync_links($content);
            } catch (\Exception $e) {
                debugging('Could not finish a link to media bank item ' . $item->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
