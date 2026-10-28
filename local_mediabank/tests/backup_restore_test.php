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
use backup_controller;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;
use restore_controller;
use restore_dbops;

/**
 * Tests for carrying media bank links through course backup and restore.
 *
 * @package    local_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(embedded_links::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Load the backup and restore libraries.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * A course with a File resource whose only file is a link to a new bank item.
     *
     * @param bool $sync Whether the link has been opened, which stores the item's content hash on the link.
     * @return array [course, bank item]
     */
    protected function course_with_link(bool $sync): array {
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($resource->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        $link = link_helper::create_link($item, $context);
        if ($sync) {
            $link->sync_external_file();
        }
        return [$course, $item];
    }

    /**
     * Back a course up.
     *
     * @param int $courseid The course.
     * @param int $mode Backup mode.
     * @return \stored_file The backup file.
     */
    protected function backup_course(int $courseid, int $mode = backup::MODE_GENERAL): \stored_file {
        global $USER;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $courseid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            $mode,
            $USER->id
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        return $file;
    }

    /**
     * Restore a backup into a new course.
     *
     * @param \stored_file $backup The backup file.
     * @param bool $othersite Whether to restore as if on a different site.
     * @return array [new course id, restore results]
     */
    protected function restore_backup(\stored_file $backup, bool $othersite): array {
        global $CFG, $USER;
        $dir = 'mbrestore' . random_string(8);
        $backup->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($dir));
        $categoryid = $this->getDataGenerator()->create_category()->id;
        $newid = restore_dbops::create_new_course('Restored', 'R' . random_string(6), $categoryid);
        $saved = $CFG->siteidentifier ?? null;
        if ($othersite) {
            $CFG->siteidentifier = 'someothersite';
        }
        try {
            $rc = new restore_controller(
                $dir,
                $newid,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id,
                backup::TARGET_NEW_COURSE
            );
            $rc->execute_precheck();
            $rc->execute_plan();
            $results = $rc->get_results();
            $rc->destroy();
        } finally {
            $CFG->siteidentifier = $saved;
        }
        return [$newid, $results];
    }

    /**
     * The files of the restored course's File resource.
     *
     * @param int $courseid The course.
     * @return \stored_file[]
     */
    protected function resource_files(int $courseid): array {
        global $DB;
        $files = [];
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'resource']);
        $fs = get_file_storage();
        foreach ($DB->get_records('course_modules', ['course' => $courseid, 'module' => $moduleid]) as $cm) {
            $context = \context_module::instance($cm->id);
            $files = array_merge($files, $fs->get_area_files($context->id, 'mod_resource', 'content', false, 'id', false));
        }
        return array_values($files);
    }

    /**
     * Without the setting, a backup restored on another site has lost the file. This is core's behaviour and the reason
     * for the setting.
     */
    public function test_setting_off_loses_file_on_other_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course_with_link(true);

        [$newid] = $this->restore_backup($this->backup_course($course->id), true);

        $this->assertCount(0, $this->resource_files($newid));
    }

    /**
     * With the setting, an opened link comes back on another site as an ordinary copy with the right content.
     */
    public function test_embedded_opened_link_becomes_copy_on_other_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('embedinbackups', 1, 'contenttype_mediabank');
        [$course, $item] = $this->course_with_link(true);

        [$newid, $results] = $this->restore_backup($this->backup_course($course->id), true);

        $files = $this->resource_files($newid);
        $this->assertCount(1, $files);
        $this->assertFalse($files[0]->is_external_file());
        $this->assertSame($item->get_file()->get_contenthash(), $files[0]->get_contenthash());
        $this->assertSame($item->get_file()->get_filesize(), $files[0]->get_filesize());
        $this->assertSame('doc.pdf', $files[0]->get_filename());
        $this->assertArrayNotHasKey('file_aliases_restore_failures', $results);
    }

    /**
     * A link nobody opened has no content of its own, but its file is still carried.
     */
    public function test_embedded_unopened_link_becomes_copy_on_other_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('embedinbackups', 1, 'contenttype_mediabank');
        [$course, $item] = $this->course_with_link(false);

        [$newid] = $this->restore_backup($this->backup_course($course->id), true);

        $files = $this->resource_files($newid);
        $this->assertCount(1, $files);
        $this->assertFalse($files[0]->is_external_file());
        $this->assertSame($item->get_file()->get_contenthash(), $files[0]->get_contenthash());
        $this->assertGreaterThan(0, $files[0]->get_filesize());
    }

    /**
     * On the same site the link stays a link, and no copy is made next to it.
     */
    public function test_same_site_keeps_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('embedinbackups', 1, 'contenttype_mediabank');
        [$course] = $this->course_with_link(true);

        [$newid, $results] = $this->restore_backup($this->backup_course($course->id), false);

        $files = $this->resource_files($newid);
        $this->assertCount(1, $files);
        $this->assertTrue($files[0]->is_external_file());
        $this->assertArrayNotHasKey('file_aliases_restore_failures', $results);
    }

    /**
     * On the same site, if the bank file is gone by restore time the link cannot come back, so a copy is made.
     */
    public function test_same_site_missing_source_becomes_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('embedinbackups', 1, 'contenttype_mediabank');
        [$course, $item] = $this->course_with_link(true);
        $backup = $this->backup_course($course->id);
        $hash = $item->get_file()->get_contenthash();
        get_file_storage()->delete_area_files($item->get_contextid(), 'contentbank', 'public', $item->get_id());
        $DB->delete_records('contentbank_content', ['id' => $item->get_id()]);

        [$newid] = $this->restore_backup($backup, false);

        $files = $this->resource_files($newid);
        $this->assertCount(1, $files);
        $this->assertFalse($files[0]->is_external_file());
        $this->assertSame($hash, $files[0]->get_contenthash());
    }

    /**
     * Only manual course backups carry the files. Import leaves files out by core's own default, so it is covered too.
     */
    public function test_which_backups_carry_files(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $applies = function (int $mode) use ($course, &$USER): bool {
            $bc = new backup_controller(
                backup::TYPE_1COURSE,
                $course->id,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                $mode,
                $USER->id
            );
            $result = embedded_links::applies_to_backup($bc->get_backupid());
            $bc->destroy();
            return $result;
        };

        $this->assertFalse($applies(backup::MODE_GENERAL));
        set_config('embedinbackups', 1, 'contenttype_mediabank');
        $this->assertTrue($applies(backup::MODE_GENERAL));
        $this->assertFalse($applies(backup::MODE_IMPORT));
        $this->assertFalse($applies(backup::MODE_AUTOMATED));
    }
}
