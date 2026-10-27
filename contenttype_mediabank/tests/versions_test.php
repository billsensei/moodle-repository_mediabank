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

use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;

/**
 * Tests for keeping, restoring and expiring replaced files.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(versions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(contenttype::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\purge_versions::class)]
final class versions_test extends \advanced_testcase {
    /**
     * A different but valid pdf.
     *
     * @param int $number Makes the content unique.
     * @return string
     */
    protected function pdf(int $number): string {
        return media_samples::get('pdf') . "\n% revision $number";
    }

    /**
     * Replace an item's file.
     *
     * @param content $item The item.
     * @param string $data New content.
     */
    protected function replace(content $item, string $data): void {
        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'doc.pdf',
        ], $data);
        $item->get_content_type_instance()->replace_content($draft, $item);
    }

    /**
     * Each replace keeps the file it replaced, newest first, without a user id.
     */
    public function test_replace_keeps_versions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $hash1 = sha1($this->pdf(1));
        $hash2 = sha1($this->pdf(2));
        $this->assertSame([], versions::get_versions($item));

        $this->replace($item, $this->pdf(2));
        $this->replace($item, $this->pdf(3));

        $versions = versions::get_versions($item);
        $this->assertSame([2, 1], array_keys($versions));
        $this->assertSame($hash2, $versions[2]->get_contenthash());
        $this->assertSame($hash1, $versions[1]->get_contenthash());
        $this->assertEmpty($versions[1]->get_userid());
        // The old bytes are still readable after the replace, not just the database row.
        $this->assertSame($this->pdf(1), $versions[1]->get_content());
        $this->assertSame($this->pdf(2), $versions[2]->get_content());
        $this->assertSame(sha1($this->pdf(3)), $item->get_file()->get_contenthash());
    }

    /**
     * Replacing with the same bytes keeps nothing, and a retention of 0 keeps nothing.
     */
    public function test_nothing_kept_when_identical_or_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $this->replace($item, $this->pdf(1));
        $this->assertSame([], versions::get_versions($item));

        set_config('versionretentiondays', 0, 'contenttype_mediabank');
        $this->replace($item, $this->pdf(2));
        $this->assertSame([], versions::get_versions($item));
    }

    /**
     * Restoring puts the old file back, links follow, and the restore can itself be undone.
     */
    public function test_restore_and_undo(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $link = link_helper::create_link($item, \context_course::instance($course->id));
        $this->replace($item, $this->pdf(2));

        versions::restore($item, 1);
        $this->assertSame(sha1($this->pdf(1)), $item->get_file()->get_contenthash());
        $this->assertSame(sha1($this->pdf(1)), get_file_storage()->get_file_by_id($link->get_id())->get_contenthash());

        // Version 2 is the file that was live before the restore, so the restore can be undone.
        $versions = versions::get_versions($item);
        $this->assertSame(sha1($this->pdf(2)), $versions[array_key_first($versions)]->get_contenthash());
        versions::restore($item, array_key_first($versions));
        $this->assertSame(sha1($this->pdf(2)), $item->get_file()->get_contenthash());
    }

    /**
     * Restoring a version that does not exist is an error.
     */
    public function test_restore_unknown_version(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $this->expectException(\moodle_exception::class);
        versions::restore($item, 7);
    }

    /**
     * The scheduled task removes only versions older than the retention time.
     */
    public function test_expired_versions_are_purged(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('versionretentiondays', 10, 'contenttype_mediabank');
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $this->replace($item, $this->pdf(2));
        $this->replace($item, $this->pdf(3));
        $versions = versions::get_versions($item);
        $DB->set_field('files', 'timecreated', time() - 11 * DAYSECS, ['id' => $versions[1]->get_id()]);

        $task = new task\purge_versions();
        $this->assertNotEmpty($task->get_name());
        ob_start();
        $task->execute();
        ob_end_clean();

        $left = versions::get_versions($item);
        $this->assertSame([2], array_keys($left));
    }

    /**
     * Versions go away with the item, and move with it.
     */
    public function test_versions_follow_delete_and_move(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $item = link_helper::create_item('doc.pdf', $this->pdf(1));
        $this->replace($item, $this->pdf(2));

        $item->get_content_type_instance()->move_content($item, $coursecontext);
        $moved = versions::get_versions($item);
        $this->assertCount(1, $moved);
        $this->assertSame((int) $coursecontext->id, (int) reset($moved)->get_contextid());

        $item->get_content_type_instance()->delete_content($item);
        $this->assertCount(0, get_file_storage()->get_area_files($coursecontext->id, 'contenttype_mediabank', 'versions'));
    }
}
