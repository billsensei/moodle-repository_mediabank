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
 * Tests for who may be given an item's file.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(contenttype::class)]
final class serving_test extends \advanced_testcase {
    /**
     * Create a user with a role in a course.
     *
     * @param \stdClass $course The course.
     * @param string $role Role short name.
     * @return \stdClass
     */
    protected function enrol_user(\stdClass $course, string $role): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        return $user;
    }

    /**
     * Create an item in a course as a user.
     *
     * @param \stdClass $user The creator.
     * @param \stdClass $course The course.
     * @param bool $unlisted Whether to make it unlisted.
     * @return content
     */
    protected function create_item(\stdClass $user, \stdClass $course, bool $unlisted = false): content {
        $this->setUser($user);
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'), \context_course::instance($course->id));
        if ($unlisted) {
            $item->set_visibility(content::VISIBILITY_UNLISTED);
        }
        return $item;
    }

    /**
     * Whether a user may be given an item's file.
     *
     * @param \stdClass $user The user.
     * @param content $item The item.
     * @return bool
     */
    protected function can_serve(\stdClass $user, content $item): bool {
        $this->setUser($user);
        return contenttype::can_serve(\context::instance_by_id($item->get_contextid()), $item->get_id());
    }

    /**
     * Teachers get a public item, students and other courses' teachers do not.
     */
    public function test_access_follows_the_bank_capabilities(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->enrol_user($course, 'editingteacher');
        $item = $this->create_item($teacher, $course);

        $this->assertTrue($this->can_serve($teacher, $item));
        $this->assertTrue($this->can_serve($this->enrol_user($course, 'editingteacher'), $item));
        $this->assertFalse($this->can_serve($this->enrol_user($course, 'student'), $item));
        $this->assertFalse($this->can_serve($this->enrol_user($other, 'editingteacher'), $item));
        $this->assertFalse($this->can_serve($this->getDataGenerator()->create_user(), $item));
    }

    /**
     * An unlisted item is for its creator and people who may see unlisted content.
     */
    public function test_unlisted_item_is_hidden_from_other_teachers(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $creator = $this->enrol_user($course, 'editingteacher');
        $item = $this->create_item($creator, $course, true);

        $this->assertTrue($this->can_serve($creator, $item));
        $this->assertFalse($this->can_serve($this->enrol_user($course, 'editingteacher'), $item));
        $this->assertTrue($this->can_serve(get_admin(), $item));
    }

    /**
     * The item must exist, be a media bank item and be in the context the URL names.
     */
    public function test_url_must_match_a_media_bank_item(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->enrol_user($course, 'editingteacher');
        $item = $this->create_item($teacher, $course);
        $this->setUser($teacher);
        $context = \context_course::instance($course->id);

        $this->assertTrue(contenttype::can_serve($context, $item->get_id()));
        $this->assertFalse(contenttype::can_serve($context, $item->get_id() + 1000));
        $this->assertFalse(contenttype::can_serve(\context_course::instance($other->id), $item->get_id()));
        $DB->set_field('contentbank_content', 'contenttype', 'contenttype_h5p', ['id' => $item->get_id()]);
        $this->assertFalse(contenttype::can_serve($context, $item->get_id()));
    }
}
