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
 * Tests for link tracking and the delete and move guards.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(usage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(contenttype::class)]
final class usage_test extends \advanced_testcase {
    /**
     * Links are counted and grouped by course, and drafts are ignored.
     */
    public function test_links_are_counted_by_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $this->assertSame(0, usage::count_links($item));

        link_helper::create_link($item, \context_course::instance($course1->id));
        link_helper::create_link($item, \context_course::instance($course1->id), 'mod_label', 'intro');
        link_helper::create_link($item, \context_course::instance($course2->id));
        // A draft link, as in an open edit form, does not count.
        link_helper::create_link($item, \context_user::instance(get_admin()->id), 'user', 'draft');

        $this->assertSame(3, usage::count_links($item));
        $courses = usage::get_courses($item);
        $this->assertCount(2, $courses);
        $this->assertSame(2, $courses[$course1->id]->links);
        $this->assertSame(1, $courses[$course2->id]->links);
    }

    /**
     * An item with links cannot be deleted by hand, and can again once the links are converted.
     */
    public function test_linked_item_cannot_be_deleted_by_hand(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $type = $item->get_content_type_instance();
        $this->assertTrue($type->can_delete($item));

        link_helper::create_link($item, \context_course::instance($course->id));
        $this->assertFalse($type->can_delete($item));

        $this->assertSame(1, usage::detach_all($item));
        $this->assertTrue($type->can_delete($item));
    }

    /**
     * Deleting in bulk, as when a course is deleted, keeps the file in the courses that linked to it.
     */
    public function test_delete_keeps_other_courses_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $link = link_helper::create_link($item, \context_course::instance($course->id));
        $hash = $item->get_file()->get_contenthash();
        $bytes = $item->get_file()->get_content();

        $this->assertTrue($item->get_content_type_instance()->delete_content($item));

        $copy = get_file_storage()->get_file_by_id($link->get_id());
        $this->assertFalse($copy->is_external_file(), 'The link should now be an independent copy.');
        $this->assertSame($hash, $copy->get_contenthash());
        $this->assertGreaterThan(0, $copy->get_filesize());
        // The bytes must still be on disk after the source file was deleted, not just the database row.
        $this->assertSame($bytes, $copy->get_content());
    }

    /**
     * Moving an item converts its links to copies, so nothing breaks.
     */
    public function test_move_converts_links(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $link = link_helper::create_link($item, \context_course::instance($course->id));
        $hash = $item->get_file()->get_contenthash();
        $bytes = $item->get_file()->get_content();

        $moved = $item->get_content_type_instance()->move_content($item, \context_course::instance($target->id));
        $this->assertTrue($moved);
        $this->assertSame(0, usage::count_links($item));
        $copy = get_file_storage()->get_file_by_id($link->get_id());
        $this->assertFalse($copy->is_external_file());
        $this->assertSame($hash, $copy->get_contenthash());
        $this->assertSame($bytes, $copy->get_content());
        $this->assertSame(\context_course::instance($target->id)->id, (int) $item->get_file()->get_contextid());
    }

    /**
     * The item page lists the courses that link to it, but only names the ones the viewer can see.
     */
    public function test_view_lists_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Chemistry 101']);
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $this->assertStringContainsString(
            get_string('usagenone', 'contenttype_mediabank'),
            $item->get_content_type_instance()->get_view_content($item)
        );

        link_helper::create_link($item, \context_course::instance($course->id));
        $html = $item->get_content_type_instance()->get_view_content($item);
        $this->assertStringContainsString('Chemistry 101', $html);

        $this->setUser($this->getDataGenerator()->create_user());
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $html = $item->get_content_type_instance()->get_view_content($item);
        $this->assertStringNotContainsString('Chemistry 101', $html);
        $this->assertStringContainsString('Courses you cannot see', $html);
    }
}
