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

namespace contenttype_mediabank\privacy;

use contenttype_mediabank\captions;
use contenttype_mediabank\content;
use contenttype_mediabank\usage;
use contenttype_mediabank\versions;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for contenttype_mediabank.
 *
 * The plugin has no tables of its own. It keeps alt text and transcripts in the item's configdata (a column of core's
 * contentbank_content table) and captions and earlier versions as files. None of those files carry a user id, so the
 * only link to a person is the user who created the item. An earlier version may have been uploaded by someone who
 * later replaced it; as in core, the item belongs to its creator.
 *
 * Core's provider handles the item rows and the current file. It does not call content types, so everything above is
 * exported and deleted here. Providers run in no fixed order, so deletion also removes files whose item row is
 * already gone.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Value of contentbank_content.contenttype for this plugin. */
    private const CONTENTTYPE = 'contenttype_mediabank';

    /** @var string Component that owns the files. */
    private const COMPONENT = 'contenttype_mediabank';

    /**
     * Describe the personal data this plugin keeps.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_subsystem_link('core_contentbank', [], 'privacy:metadata:core_contentbank');
        return $collection;
    }

    /**
     * Contexts holding media bank items created by the user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {contentbank_content} cb ON cb.contextid = ctx.id
                 WHERE cb.usercreated = :userid
                       AND cb.contenttype = :contenttype
                       AND ctx.contextlevel IN (:system, :category, :course)";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['userid' => $userid] + self::params());
        return $contextlist;
    }

    /**
     * Users who created media bank items in a context.
     *
     * @param userlist $userlist The list to add to.
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!self::is_supported_context($userlist->get_context())) {
            return;
        }
        $sql = "SELECT cb.usercreated AS userid
                  FROM {contentbank_content} cb
                 WHERE cb.contextid = :contextid AND cb.contenttype = :contenttype";
        $userlist->add_from_sql('userid', $sql, [
            'contextid' => $userlist->get_context()->id,
            'contenttype' => self::CONTENTTYPE,
        ]);
    }

    /**
     * Export alt text, transcript, captions and earlier versions of the user's items.
     *
     * Written under this plugin's own folder, so core's export of the same items is not overwritten.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $contexts = array_filter($contextlist->get_contexts(), [self::class, 'is_supported_context']);
        if (!$contexts) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_map(fn($context) => $context->id, $contexts), SQL_PARAMS_NAMED);
        $items = $DB->get_recordset_select(
            'contentbank_content',
            "usercreated = :userid AND contenttype = :contenttype AND contextid $insql",
            ['userid' => $contextlist->get_user()->id, 'contenttype' => self::CONTENTTYPE] + $inparams,
            'contextid, id',
            'id, name, contextid, configdata'
        );
        foreach ($items as $item) {
            $context = \context::instance_by_id($item->contextid);
            $subcontext = [get_string('pluginname', 'contenttype_mediabank'), $item->id . '-' . $item->name];
            $metadata = json_decode((string) $item->configdata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $writer = writer::with_context($context);
            $writer->export_data($subcontext, (object) [
                'name' => $item->name,
                'kind' => $metadata['kind'] ?? null,
                'alt' => $metadata['alt'] ?? null,
                'transcript' => $metadata['transcript'] ?? null,
            ]);
            $writer->export_area_files($subcontext, self::COMPONENT, captions::FILEAREA, $item->id);
            $writer->export_area_files($subcontext, self::COMPONENT, versions::FILEAREA, $item->id);
        }
        $items->close();
    }

    /**
     * Delete the files of every media bank item in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (self::is_supported_context($context)) {
            self::keep_links($context, []);
            get_file_storage()->delete_area_files($context->id, self::COMPONENT);
        }
    }

    /**
     * Delete the files of the user's items.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if (self::is_supported_context($context)) {
                self::delete_files($context, [$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete the files of several users' items in a context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (self::is_supported_context($userlist->get_context())) {
            self::delete_files($userlist->get_context(), $userlist->get_userids());
        }
    }

    /**
     * Whether media bank items can live in this context.
     *
     * @param \context $context The context.
     * @return bool
     */
    private static function is_supported_context(\context $context): bool {
        return in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE]);
    }

    /**
     * Query parameters for the three supported context levels.
     *
     * @return array
     */
    private static function params(): array {
        return [
            'contenttype' => self::CONTENTTYPE,
            'system' => CONTEXT_SYSTEM,
            'category' => CONTEXT_COURSECAT,
            'course' => CONTEXT_COURSE,
        ];
    }

    /**
     * Make sure courses that link to the items about to be erased keep their file.
     *
     * Erasing an item removes its file. Core turns each link into a copy of what the link holds at that moment, so every
     * link must hold the item's content first. Links normally do, since they are synced when made and daily; this covers
     * the ones made since. If core's provider has already erased the items, there is nothing left to do here.
     *
     * @param \context $context The context.
     * @param int[] $userids Creators whose items are erased, or none for every item in the context.
     */
    private static function keep_links(\context $context, array $userids): void {
        global $DB;
        $select = 'contextid = :contextid AND contenttype = :contenttype';
        $params = ['contextid' => $context->id, 'contenttype' => self::CONTENTTYPE];
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select .= " AND usercreated $insql";
            $params += $inparams;
        }
        $items = $DB->get_recordset_select('contentbank_content', $select, $params);
        foreach ($items as $record) {
            // Erasing the person's data must go ahead even if a link cannot be refreshed.
            try {
                usage::sync_links(new content($record));
            } catch (\Exception $e) {
                $message = 'Could not refresh the links to media bank item ' . $record->id . ': ' . $e->getMessage();
                debugging($message, DEBUG_DEVELOPER);
            }
        }
        $items->close();
    }

    /**
     * Delete the files of the given users' items, and files whose item is already gone.
     *
     * The second part matters because core's provider may have deleted the item rows first, which leaves nothing to
     * tell whose files these are.
     *
     * @param \context $context The context.
     * @param int[] $userids The users.
     */
    private static function delete_files(\context $context, array $userids): void {
        global $DB;
        self::keep_links($context, $userids);
        $fs = get_file_storage();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $itemids = $DB->get_fieldset_select(
            'contentbank_content',
            'id',
            "contextid = :contextid AND contenttype = :contenttype AND usercreated $insql",
            ['contextid' => $context->id, 'contenttype' => self::CONTENTTYPE] + $inparams
        );
        $orphans = $DB->get_fieldset_sql(
            "SELECT DISTINCT f.itemid
               FROM {files} f
              WHERE f.contextid = :contextid AND f.component = :component
                    AND NOT EXISTS (SELECT 1 FROM {contentbank_content} cb WHERE cb.id = f.itemid)",
            ['contextid' => $context->id, 'component' => self::COMPONENT]
        );
        foreach (array_unique(array_merge($itemids, $orphans)) as $itemid) {
            $fs->delete_area_files($context->id, self::COMPONENT, false, $itemid);
        }
    }
}
