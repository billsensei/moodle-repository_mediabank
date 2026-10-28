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

namespace local_mediabank;

use backup;
use backup_controller_dbops;
use backup_file_manager;
use file_storage;

/**
 * Carries the files behind media bank links inside a course backup, and puts them back on restore.
 *
 * Core's backup stores a link (file alias) as a reference only, so restoring on another site leaves the file out. When the
 * admin setting is on, the backup also holds the linked file's bytes. On restore, a link that can be restored is left to
 * core; one that cannot is created as an ordinary copy.
 *
 * @package    local_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embedded_links {
    /** @var string Name of the backup_ids_temp item that remembers, per link, the file that was embedded. */
    public const ITEMNAME = 'local_mediabank_embedded';

    /** @var string Name of the backup_ids_temp item where core queues links to restore. */
    private const QUEUE = 'file_aliases_queue';

    /**
     * Whether the admin chose to include linked files in backups.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('contenttype_mediabank', 'embedinbackups');
    }

    /**
     * Whether this backup should carry linked files: setting on, manual course backup, and files are included at all.
     *
     * Import, course copy and automated backups stay as they are, as does a backup that leaves files out.
     *
     * @param string $backupid The backup.
     * @return bool
     */
    public static function applies_to_backup(string $backupid): bool {
        if (!self::is_enabled()) {
            return false;
        }
        $controller = backup_controller_dbops::load_controller($backupid);
        $applies = $controller->get_mode() === backup::MODE_GENERAL && $controller->get_include_files();
        $controller->destroy();
        return $applies;
    }

    /**
     * Links in one context that point at a media bank item, with the file each points at.
     *
     * @param int $contextid The context (a course or an activity).
     * @return \stored_file[] Alias file id => the bank file it links to.
     */
    public static function find_linked_files(int $contextid): array {
        global $DB;
        $fs = get_file_storage();
        $aliases = $DB->get_records_sql(
            "SELECT f.id, fr.reference
               FROM {files} f
               JOIN {files_reference} fr ON fr.id = f.referencefileid
               JOIN {repository_instances} ri ON ri.id = fr.repositoryid
               JOIN {repository} r ON r.id = ri.typeid
              WHERE f.contextid = :contextid AND f.filename <> '.' AND r.type = 'contentbank'",
            ['contextid' => $contextid]
        );
        $found = [];
        foreach ($aliases as $alias) {
            try {
                $reference = file_storage::unpack_reference($alias->reference);
            } catch (\Exception $e) {
                continue;
            }
            if ($reference['component'] !== 'contentbank' || $reference['filearea'] !== 'public') {
                continue;
            }
            $ismediabank = $DB->record_exists(
                'contentbank_content',
                ['id' => $reference['itemid'], 'contenttype' => 'contenttype_mediabank']
            );
            $source = $ismediabank ? $fs->get_file(
                $reference['contextid'],
                $reference['component'],
                $reference['filearea'],
                $reference['itemid'],
                $reference['filepath'],
                $reference['filename']
            ) : false;
            if ($source) {
                $found[$alias->id] = $source;
            }
        }
        return $found;
    }

    /**
     * Copy the files behind the links of a context into the backup, and list them.
     *
     * The list is keyed by the link's file id, which is how core's restore queue identifies it, because the link's own
     * content hash is the empty-file hash until someone has opened the link.
     *
     * @param string $backupid The backup.
     * @param int $contextid The context.
     * @return array[] One record per file, for the backup XML.
     */
    public static function embed_in_backup(string $backupid, int $contextid): array {
        if (!self::applies_to_backup($backupid)) {
            return [];
        }
        $records = [];
        foreach (self::find_linked_files($contextid) as $aliasid => $source) {
            $target = backup_file_manager::get_backup_storage_base_dir($backupid) . '/' .
                backup_file_manager::get_backup_content_file_location($source->get_contenthash());
            if (!file_exists($target)) {
                check_dir_exists(dirname($target), true, true);
                $source->copy_content_to($target);
            }
            $records[] = [
                'aliasid' => $aliasid,
                'contenthash' => $source->get_contenthash(),
                'filename' => $source->get_filename(),
                'filesize' => $source->get_filesize(),
            ];
        }
        return $records;
    }

    /**
     * Remember, during restore, which links the backup carries a file for.
     *
     * @param string $restoreid The restore.
     * @param array $data The embedded file element from the backup.
     */
    public static function remember(string $restoreid, array $data): void {
        \restore_dbops::set_backup_ids_record($restoreid, self::ITEMNAME, (int) $data['aliasid'], 0, null, (object) $data);
    }

    /**
     * Turn the links that core cannot restore into ordinary copies, using the files the backup carries.
     *
     * Runs before core processes its queue of links. A link is left to core when core can restore it: same site, the
     * repository still exists and the bank file is still there. Anything else gets a copy, and the link is taken off
     * core's queue so it is not tried twice.
     *
     * @param string $restoreid The restore.
     * @param string $basepath Folder the backup was unpacked into.
     * @param bool $samesite Whether the backup was made on this site.
     * @return string[] Names of the files restored as copies.
     */
    public static function restore_copies(string $restoreid, string $basepath, bool $samesite): array {
        global $DB;
        $fs = get_file_storage();
        $restored = [];
        $embedded = $DB->get_records('backup_ids_temp', ['backupid' => $restoreid, 'itemname' => self::ITEMNAME]);
        foreach ($embedded as $row) {
            $queued = $DB->get_record(
                'backup_ids_temp',
                ['backupid' => $restoreid, 'itemname' => self::QUEUE, 'itemid' => $row->itemid]
            );
            if (!$queued) {
                continue;
            }
            $info = backup_controller_dbops::decode_backup_temp_info($queued->info);
            if ($samesite && self::core_can_restore($info)) {
                continue;
            }
            $data = backup_controller_dbops::decode_backup_temp_info($row->info);
            $pool = $basepath . '/files/' . backup_file_manager::get_backup_content_file_location($data->contenthash);
            if (!file_exists($pool)) {
                continue;
            }
            $record = (array) $info->newfile;
            if (
                !$fs->file_exists(
                    $record['contextid'],
                    $record['component'],
                    $record['filearea'],
                    $record['itemid'],
                    $record['filepath'],
                    $record['filename']
                )
            ) {
                $fs->create_file_from_pathname($record, $pool);
            }
            $DB->delete_records('backup_ids_temp', ['id' => $queued->id]);
            $restored[] = $data->filename;
        }
        return $restored;
    }

    /**
     * Whether core will be able to restore a queued link as a link, on the same site.
     *
     * @param \stdClass $info The queued link.
     * @return bool
     */
    private static function core_can_restore(\stdClass $info): bool {
        global $DB;
        if (!$DB->record_exists('repository_instances', ['id' => $info->oldfile->repositoryid])) {
            return false;
        }
        try {
            $reference = file_storage::unpack_reference($info->oldfile->reference);
        } catch (\Exception $e) {
            return false;
        }
        return get_file_storage()->file_exists(
            $reference['contextid'],
            $reference['component'],
            $reference['filearea'],
            $reference['itemid'],
            $reference['filepath'],
            $reference['filename']
        );
    }
}
