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

use contenttype_mediabank\external\get_item;
use contenttype_mediabank\external\get_items;
use contenttype_mediabank\external\get_usage;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;
use core_external\external_api;

/**
 * Tests for the media bank web service functions.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_items::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_item::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_usage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(external\item::class)]
final class external_test extends \core_external\tests\externallib_testcase {
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
     * Create an item in a context as a user.
     *
     * @param \stdClass $user The creator.
     * @param \context $context Where to put it.
     * @param string $filename File name.
     * @param string|null $data Content. Defaults to a small PDF.
     * @return content
     */
    protected function create_item(
        \stdClass $user,
        \context $context,
        string $filename = 'doc.pdf',
        ?string $data = null
    ): content {
        $this->setUser($user);
        return link_helper::create_item($filename, $data ?? media_samples::get('pdf'), $context);
    }

    /**
     * Run a call and check its result against the declared structure, the way the web service layer does.
     *
     * @param string $class Function class.
     * @param array $args Arguments by name.
     * @return array
     */
    protected function call(string $class, array $args): array {
        $result = $class::execute(...$args);
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    /**
     * A teacher lists the items of the course, with the filters and paging, and the result has the declared shape.
     */
    public function test_get_items_filters_and_pages(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->enrol_user($course, 'editingteacher');
        $this->create_item($teacher, $context, 'b.pdf');
        $this->create_item($teacher, $context, 'a.pdf');
        $this->create_item($teacher, $context, 'pic.png', media_samples::get('png'));
        $this->setUser($teacher);

        $all = $this->call(get_items::class, ['contextid' => $context->id]);
        $this->assertSame(3, $all['total']);
        $this->assertSame(['a.pdf', 'b.pdf', 'pic.png'], array_column($all['items'], 'name'));

        $pdfs = $this->call(get_items::class, ['contextid' => $context->id, 'kind' => 'pdf']);
        $this->assertSame(2, $pdfs['total']);

        $page = $this->call(get_items::class, ['contextid' => $context->id, 'perpage' => 2, 'page' => 1]);
        $this->assertSame(3, $page['total']);
        $this->assertSame(['pic.png'], array_column($page['items'], 'name'));

        $found = $this->call(get_items::class, ['contextid' => $context->id, 'query' => 'PIC']);
        $this->assertSame(['pic.png'], array_column($found['items'], 'name'));
        $this->assertSame('image', $found['items'][0]['kind']);
        $this->assertGreaterThan(0, $found['items'][0]['width']);
        $this->assertNull($all['items'][0]['width']);
    }

    /**
     * Bad filters and paging are refused.
     */
    public function test_get_items_validates_arguments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $contextid = \context_system::instance()->id;

        foreach ([['kind' => 'zip'], ['page' => -1], ['perpage' => 0], ['perpage' => 101]] as $bad) {
            try {
                $this->call(get_items::class, ['contextid' => $contextid] + $bad);
                $this->fail('Expected a refusal for ' . json_encode($bad));
            } catch (\invalid_parameter_exception $e) {
                $this->assertInstanceOf(\invalid_parameter_exception::class, $e);
            }
        }
    }

    /**
     * People without the media bank in a context cannot list it, whatever they ask for.
     */
    public function test_get_items_needs_access_in_the_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->enrol_user($course, 'editingteacher');
        $this->create_item($teacher, \context_course::instance($course->id));

        foreach ([$this->enrol_user($course, 'student'), $this->enrol_user($other, 'editingteacher')] as $user) {
            $this->setUser($user);
            try {
                $this->call(get_items::class, ['contextid' => \context_course::instance($course->id)->id]);
                $this->fail('Expected a refusal');
            } catch (\core\exception\moodle_exception $e) {
                $this->assertInstanceOf(\core\exception\moodle_exception::class, $e);
            }
        }
        // Not a context the Content Bank uses.
        $this->setAdminUser();
        $this->expectException(\core\exception\moodle_exception::class);
        $this->call(get_items::class, ['contextid' => \context_user::instance($teacher->id)->id]);
    }

    /**
     * An unlisted item is listed for its creator, not for other teachers.
     */
    public function test_unlisted_items_are_hidden(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $creator = $this->enrol_user($course, 'editingteacher');
        $item = $this->create_item($creator, $context);
        $item->set_visibility(content::VISIBILITY_UNLISTED);

        $this->setUser($creator);
        $this->assertSame(1, $this->call(get_items::class, ['contextid' => $context->id])['total']);
        $this->setUser($this->enrol_user($course, 'editingteacher'));
        $this->assertSame(0, $this->call(get_items::class, ['contextid' => $context->id])['total']);
        try {
            $this->call(get_item::class, ['id' => $item->get_id()]);
            $this->fail('Expected a refusal');
        } catch (\core\exception\moodle_exception $e) {
            $this->assertSame('errornosuchitem', $e->errorcode);
        }
    }

    /**
     * One item has its file and caption addresses and its accessibility text, and no creator.
     */
    public function test_get_item(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->enrol_user($course, 'editingteacher');
        $item = $this->create_item($teacher, $context, 'talk.mp3', media_samples::get('mp3'));
        $item->merge_metadata(['transcript' => 'Hello there']);
        $adminctx = \context_user::instance(get_admin()->id)->id;
        $fs = get_file_storage();
        $draftid = file_get_unused_draft_itemid();
        $fs->create_file_from_string([
            'contextid' => $adminctx, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid,
            'filepath' => '/', 'filename' => 'c.vtt',
        ], "WEBVTT\n");
        $this->setUser($teacher);
        $before = $this->call(get_item::class, ['id' => $item->get_id()])['item'];
        $this->assertNull($before['captionsurl']);
        captions::save($item, $fs->get_file($adminctx, 'user', 'draft', $draftid, '/', 'c.vtt'));

        $result = $this->call(get_item::class, ['id' => $item->get_id()])['item'];

        $this->assertSame('audio', $result['kind']);
        $this->assertSame('Hello there', $result['transcript']);
        $this->assertSame('talk.mp3', $result['filename']);
        $this->assertTrue($result['canmanage']);
        $this->assertStringContainsString(
            "/webservice/pluginfile.php/{$context->id}/contentbank/public/{$item->get_id()}/talk.mp3",
            $result['fileurl']
        );
        $this->assertStringContainsString(
            "/webservice/pluginfile.php/{$context->id}/contenttype_mediabank/captions/{$item->get_id()}/captions.vtt",
            $result['captionsurl']
        );
        $this->assertArrayNotHasKey('usercreated', $result);
        $this->assertArrayNotHasKey('userid', $result);
    }

    /**
     * Anything that is not a media bank item the caller can use gives the same answer.
     */
    public function test_get_item_refuses_other_things_alike(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $other = clone $DB->get_record('contentbank_content', ['id' => $item->get_id()]);
        unset($other->id);
        $other->contenttype = 'contenttype_h5p';
        $otherid = $DB->insert_record('contentbank_content', $other);

        foreach ([$otherid, $item->get_id() + 1000] as $id) {
            try {
                $this->call(get_item::class, ['id' => $id]);
                $this->fail('Expected a refusal');
            } catch (\core\exception\moodle_exception $e) {
                $this->assertSame('errornosuchitem', $e->errorcode);
            }
        }
    }

    /**
     * A user with the given capabilities in the system context only.
     *
     * @param string[] $capabilities Capabilities to allow.
     * @return \stdClass
     */
    protected function user_with_capabilities(array $capabilities): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        $this->getDataGenerator()->role_assign($roleid, $user->id, \context_system::instance()->id);
        return $user;
    }

    /**
     * Usage is for people who can manage the item, and does not name courses the caller cannot see.
     */
    public function test_get_usage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $visible = $this->getDataGenerator()->create_course();
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        foreach ([$visible, $hidden] as $course) {
            $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
            $modulecontext = \context_module::instance($page->cmid);
            get_file_storage()->delete_area_files($modulecontext->id, 'mod_resource', 'content');
            link_helper::create_link($item, $modulecontext);
        }

        $admin = $this->call(get_usage::class, ['id' => $item->get_id()]);
        $this->assertSame(2, $admin['links']);
        $this->assertEqualsCanonicalizing([(int) $visible->id, (int) $hidden->id], array_column($admin['courses'], 'id'));
        $this->assertSame(0, $admin['hiddenlinks']);

        // Can manage the bank, but cannot see hidden courses: the hidden course is counted, not named.
        $bank = ['moodle/contentbank:access', 'contenttype/mediabank:access', 'moodle/contentbank:manageanycontent'];
        $this->setUser($this->user_with_capabilities($bank));
        $limited = $this->call(get_usage::class, ['id' => $item->get_id()]);
        $this->assertSame(2, $limited['links']);
        $this->assertSame([(int) $visible->id], array_column($limited['courses'], 'id'));
        $this->assertSame(1, $limited['hiddenlinks']);

        // Can see the item but not manage it.
        $this->setUser($this->user_with_capabilities(['moodle/contentbank:access', 'contenttype/mediabank:access']));
        $this->assertSame($item->get_id(), $this->call(get_item::class, ['id' => $item->get_id()])['item']['id']);
        try {
            $this->call(get_usage::class, ['id' => $item->get_id()]);
            $this->fail('Expected a refusal');
        } catch (\core\exception\moodle_exception $e) {
            $this->assertSame('errornosuchitem', $e->errorcode);
        }
    }

    /**
     * Core's own delete function refuses a linked item, so the protection holds over web services too.
     */
    public function test_core_delete_function_refuses_linked_item(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $modulecontext = \context_module::instance($page->cmid);
        get_file_storage()->delete_area_files($modulecontext->id, 'mod_resource', 'content');
        link_helper::create_link($item, $modulecontext);

        $result = \core_contentbank\external\delete_content::execute([$item->get_id()]);

        $this->assertFalse($result['result']);
        $this->assertSame('nopermissiontodelete', $result['warnings'][0]['warningcode']);
        $this->assertTrue($DB->record_exists('contentbank_content', ['id' => $item->get_id()]));
    }
}
