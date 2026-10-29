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

namespace contenttype_mediabank\privacy;

use contenttype_mediabank\captions;
use contenttype_mediabank\content;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the media bank privacy provider.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create an item as a user, with alt text, a transcript, a caption file and one earlier version.
     *
     * @param \stdClass $user The creator.
     * @param \context $context Where to put the item.
     * @return content
     */
    protected function create_full_item(\stdClass $user, \context $context): content {
        $this->setUser($user);
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'), $context);
        $item->merge_metadata(['alt' => 'Alt of ' . $user->username, 'transcript' => 'Words by ' . $user->username]);
        $adminctx = \context_user::instance(get_admin()->id)->id;
        $fs = get_file_storage();
        $draftid = file_get_unused_draft_itemid();
        $fs->create_file_from_string([
            'contextid' => $adminctx, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid,
            'filepath' => '/', 'filename' => 'c.vtt',
        ], "WEBVTT\n");
        captions::save($item, $fs->get_file($adminctx, 'user', 'draft', $draftid, '/', 'c.vtt'));
        $draft = $fs->create_file_from_string([
            'contextid' => $adminctx, 'component' => 'user', 'filearea' => 'draft', 'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/', 'filename' => 'doc.pdf',
        ], media_samples::get('pdf') . "\n% revision 2");
        $item->get_content_type_instance()->replace_content($draft, $item);
        return $item;
    }

    /**
     * Number of files this plugin holds for an item, directories excluded.
     *
     * @param content $item The item.
     * @return int
     */
    protected function count_files(content $item): int {
        global $DB;
        return $DB->count_records_select(
            'files',
            "component = 'contenttype_mediabank' AND itemid = :id AND filename <> '.'",
            ['id' => $item->get_id()]
        );
    }

    /**
     * Two users with items in the system context and one in a course.
     *
     * @return array [user1, user2, item1, item2, course item1]
     */
    protected function setup_items(): array {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $item1 = $this->create_full_item($user1, \context_system::instance());
        $item2 = $this->create_full_item($user2, \context_system::instance());
        $courseitem = $this->create_full_item($user1, \context_course::instance($course->id));
        return [$user1, $user2, $item1, $item2, $courseitem];
    }

    /**
     * The metadata declares the files and the item settings.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('contenttype_mediabank'))->get_collection();
        $this->assertCount(2, $items);
    }

    /**
     * Contexts and users are found by item creator.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        [$user1, $user2, $item1, , $courseitem] = $this->setup_items();

        $contextids = provider::get_contexts_for_userid($user1->id)->get_contextids();
        sort($contextids);
        $expected = [$item1->get_contextid(), $courseitem->get_contextid()];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $contextids));
        $this->assertCount(1, provider::get_contexts_for_userid($user2->id));

        $userlist = new userlist(\context_system::instance(), 'contenttype_mediabank');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());
        $this->assertEmpty(provider::get_contexts_for_userid(get_admin()->id)->get_contextids());
    }

    /**
     * Export contains settings, caption and version, and leaves core's own export in place.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        [$user1, , $item1] = $this->setup_items();
        $context = \context::instance_by_id($item1->get_contextid());

        $list = new approved_contextlist($user1, 'contenttype_mediabank', [$context->id]);
        provider::export_user_data($list);
        $corelist = new approved_contextlist($user1, 'core_contentbank', [$context->id]);
        \core_contentbank\privacy\provider::export_user_data($corelist);

        $writer = writer::with_context($context);
        $subcontext = [get_string('pluginname', 'contenttype_mediabank'), $item1->get_id() . '-' . $item1->get_name()];
        $data = $writer->get_data($subcontext);
        $this->assertSame('Alt of ' . $user1->username, $data->alt);
        $this->assertSame('Words by ' . $user1->username, $data->transcript);
        $files = $writer->get_files($subcontext);
        $this->assertEqualsCanonicalizing(['captions.vtt', '1/doc.pdf'], array_keys($files));
        // Core's export of the same item is a different path and still has its data.
        $this->assertNotEmpty($writer->get_data([get_string('name', 'core_contentbank')]));
    }

    /**
     * Deleting one user's data removes their files only, whichever provider runs first.
     *
     * @param bool $corefirst Whether core's provider runs before this one.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('order_provider')]
    public function test_delete_for_user(bool $corefirst): void {
        $this->resetAfterTest();
        [$user1, , $item1, $item2, $courseitem] = $this->setup_items();
        $this->assertSame(2, $this->count_files($item1));
        $contextids = [$item1->get_contextid(), $courseitem->get_contextid()];
        $mine = new approved_contextlist($user1, 'contenttype_mediabank', $contextids);
        $core = new approved_contextlist($user1, 'core_contentbank', $contextids);

        if ($corefirst) {
            \core_contentbank\privacy\provider::delete_data_for_user($core);
            provider::delete_data_for_user($mine);
        } else {
            provider::delete_data_for_user($mine);
            \core_contentbank\privacy\provider::delete_data_for_user($core);
        }

        $this->assertSame(0, $this->count_files($item1));
        $this->assertSame(0, $this->count_files($courseitem));
        $this->assertSame(2, $this->count_files($item2));
    }

    /**
     * Both provider orders.
     *
     * @return array
     */
    public static function order_provider(): array {
        return ['core first' => [true], 'plugin first' => [false]];
    }

    /**
     * Deleting a list of users in a context.
     */
    public function test_delete_for_users(): void {
        $this->resetAfterTest();
        [$user1, $user2, $item1, $item2, $courseitem] = $this->setup_items();
        $context = \context_system::instance();

        provider::delete_data_for_users(new approved_userlist($context, 'contenttype_mediabank', [$user1->id]));

        $this->assertSame(0, $this->count_files($item1));
        $this->assertSame(2, $this->count_files($item2));
        // A different context is untouched.
        $this->assertSame(2, $this->count_files($courseitem));
    }

    /**
     * Deleting everything in a context leaves other contexts alone.
     */
    public function test_delete_for_all_users_in_context(): void {
        $this->resetAfterTest();
        [, , $item1, $item2, $courseitem] = $this->setup_items();

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $this->count_files($item1));
        $this->assertSame(0, $this->count_files($item2));
        $this->assertSame(2, $this->count_files($courseitem));
    }

    /**
     * Core's deletion of the creator's data keeps the content of a link that has been opened.
     *
     * Core's provider removes the item without calling the content type, so the link-to-copy conversion does not run.
     * This records what actually happens.
     */
    public function test_core_delete_keeps_content_of_opened_link(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $item = $this->create_full_item($user, \context_system::instance());
        $this->setAdminUser();
        $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $link = link_helper::create_link($item, \context_module::instance($page->cmid));
        $hash = $item->get_file()->get_contenthash();
        // Syncing is what happens when anyone opens the link. A link nobody has opened has no content of its own yet
        // and is empty once core deletes the item. That case is an open policy question, not asserted here.
        $link->sync_external_file();

        $list = new approved_contextlist($user, 'core_contentbank', [$item->get_contextid()]);
        \core_contentbank\privacy\provider::delete_data_for_user($list);

        $after = get_file_storage()->get_file_by_id($link->get_id());
        $this->assertNotFalse($after);
        $this->assertSame($hash, $after->get_contenthash());
        $this->assertGreaterThan(0, $after->get_filesize());
    }
}
