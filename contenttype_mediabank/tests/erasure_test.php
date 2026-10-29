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

use contenttype_mediabank\privacy\provider;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;

/**
 * Tests for what happens to courses that link to media a person uploaded, when that person's data is erased.
 *
 * The policy is that courses keep their file as an ordinary copy.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(usage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\sync_links::class)]
final class erasure_test extends \advanced_testcase {
    /**
     * A user, their item with a caption and an earlier version, and a File resource context to link from.
     *
     * @return array [user, item, resource context]
     */
    protected function setup_item(): array {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
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
        $item = new content($GLOBALS['DB']->get_record('contentbank_content', ['id' => $item->get_id()]));

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        return [$user, $item, $context];
    }

    /**
     * Erase a person's data in the system context, with core's provider and ours, in the given order.
     *
     * @param \stdClass $user The person.
     * @param content $item One of their items.
     * @param bool $corefirst Whether core's provider runs before ours.
     */
    protected function erase(\stdClass $user, content $item, bool $corefirst): void {
        $contextids = [$item->get_contextid()];
        $core = new approved_contextlist($user, 'core_contentbank', $contextids);
        $mine = new approved_contextlist($user, 'contenttype_mediabank', $contextids);
        if ($corefirst) {
            \core_contentbank\privacy\provider::delete_data_for_user($core);
            provider::delete_data_for_user($mine);
        } else {
            provider::delete_data_for_user($mine);
            \core_contentbank\privacy\provider::delete_data_for_user($core);
        }
    }

    /**
     * The content hash stored on a file's row. Reading it through the file object would sync a link, which is what
     * opening it does, and hide the thing being tested.
     *
     * @param \stored_file $file The file.
     * @return string
     */
    protected function stored_hash(\stored_file $file): string {
        global $DB;
        return (string) $DB->get_field('files', 'contenthash', ['id' => $file->get_id()]);
    }

    /**
     * The course's file is an ordinary file holding the item's content.
     *
     * @param \stored_file $link The link as it was made.
     * @param string $hash Content hash of the item's current file.
     * @param int $size Its size.
     */
    protected function assert_course_kept_copy(\stored_file $link, string $hash, int $size): void {
        global $DB;
        $row = $DB->get_record('files', ['id' => $link->get_id()], 'id, contenthash, filesize, referencefileid');
        $this->assertNotFalse($row, 'The course lost its file');
        $this->assertEmpty($row->referencefileid, 'The file is still a link');
        $this->assertSame($hash, $row->contenthash);
        $this->assertSame($size, (int) $row->filesize);
        $this->assertGreaterThan(0, (int) $row->filesize);
    }

    /**
     * Nothing of the person's item is left: row, current file, versions and captions.
     *
     * @param content $item The item.
     */
    protected function assert_item_erased(content $item): void {
        global $DB;
        $this->assertFalse($DB->record_exists('contentbank_content', ['id' => $item->get_id()]));
        $this->assertSame(0, $DB->count_records_select(
            'files',
            "itemid = :id AND filename <> '.' AND component IN ('contentbank', 'contenttype_mediabank')",
            ['id' => $item->get_id()]
        ));
    }

    /**
     * A link saved through the form holds the item's content as soon as it exists, and one made the way a restore makes
     * them holds none until something reads it.
     */
    public function test_form_saved_link_has_content_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        [, $item, $context] = $this->setup_item();
        $hash = $item->get_file()->get_contenthash();

        $saved = link_helper::create_link_by_saving_form($item, $context);
        $this->assertSame($hash, $this->stored_hash($saved));
        $this->assertGreaterThan(0, (int) $DB->get_field('files', 'filesize', ['id' => $saved->get_id()]));

        // The same thing made the restore way, for a different item, starts without it.
        [, $second, $secondcontext] = $this->setup_item();
        $restored = link_helper::create_link($second, $secondcontext);
        $this->assertSame(sha1(''), $this->stored_hash($restored));
    }

    /**
     * Courses keep their file whichever provider runs first, for a link the form saved.
     *
     * @param bool $corefirst Whether core's provider runs before ours.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('order_provider')]
    public function test_form_saved_link_survives_erasure(bool $corefirst): void {
        $this->resetAfterTest();
        [$user, $item, $context] = $this->setup_item();
        $link = link_helper::create_link_by_saving_form($item, $context);
        $hash = $item->get_file()->get_contenthash();
        $size = (int) $item->get_file()->get_filesize();

        $this->erase($user, $item, $corefirst);

        $this->assert_course_kept_copy($link, $hash, $size);
        $this->assert_item_erased($item);
    }

    /**
     * A link made the way a restore makes them has no content of its own. Our provider refreshes it before core erases the
     * item, which is the order Moodle uses today.
     */
    public function test_restore_style_link_survives_when_our_provider_runs_first(): void {
        $this->resetAfterTest();
        [$user, $item, $context] = $this->setup_item();
        $link = link_helper::create_link($item, $context);
        $hash = $item->get_file()->get_contenthash();
        $size = (int) $item->get_file()->get_filesize();
        $this->assertSame(sha1(''), $this->stored_hash($link), 'The link should start without the item\'s content');

        $this->erase($user, $item, false);

        $this->assert_course_kept_copy($link, $hash, $size);
        $this->assert_item_erased($item);
    }

    /**
     * If core's provider ran first, the daily task is what has already given the link its content.
     */
    public function test_restore_style_link_survives_when_core_runs_first_after_the_task(): void {
        $this->resetAfterTest();
        [$user, $item, $context] = $this->setup_item();
        $link = link_helper::create_link($item, $context);
        $hash = $item->get_file()->get_contenthash();
        $size = (int) $item->get_file()->get_filesize();

        $this->assertSame(1, usage::sync_all_links());
        $this->erase($user, $item, true);

        $this->assert_course_kept_copy($link, $hash, $size);
        $this->assert_item_erased($item);
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
     * Erasing a list of users, and erasing a whole context, keep the courses' files as well.
     */
    public function test_other_erasure_paths_keep_the_copy(): void {
        $this->resetAfterTest();
        [$user, $item, $context] = $this->setup_item();
        $link = link_helper::create_link($item, $context);
        $hash = $item->get_file()->get_contenthash();
        $size = (int) $item->get_file()->get_filesize();

        $systemcontext = \context_system::instance();
        provider::delete_data_for_users(new approved_userlist($systemcontext, 'contenttype_mediabank', [$user->id]));
        \core_contentbank\privacy\provider::delete_data_for_users(
            new approved_userlist($systemcontext, 'core_contentbank', [$user->id])
        );
        $this->assert_course_kept_copy($link, $hash, $size);
        $this->assert_item_erased($item);

        // A second item, erased with everything else in its context.
        [, $second, $secondcontext] = $this->setup_item();
        $secondlink = link_helper::create_link($second, $secondcontext);
        $secondhash = $second->get_file()->get_contenthash();
        $secondsize = (int) $second->get_file()->get_filesize();
        provider::delete_data_for_all_users_in_context($systemcontext);
        \core_contentbank\privacy\provider::delete_data_for_all_users_in_context($systemcontext);
        $this->assert_course_kept_copy($secondlink, $secondhash, $secondsize);
        $this->assert_item_erased($second);
    }

    /**
     * The daily task brings both an empty link and a stale one up to date, counts only what it changed, and does nothing
     * the second time.
     */
    public function test_sync_task_fixes_empty_and_stale_links(): void {
        global $DB;
        $this->resetAfterTest();
        [, $item, $context] = $this->setup_item();
        $link = link_helper::create_link($item, $context);
        $hash = $item->get_file()->get_contenthash();

        $this->assertSame(sha1(''), $this->stored_hash($link));
        $this->assertSame(1, usage::sync_all_links(), 'empty link');
        $this->assertSame($hash, $this->stored_hash($link));
        $this->assertSame(0, usage::sync_all_links(), 'nothing left to do');

        // A link that holds the content of an older version of the item.
        $DB->set_field('files', 'contenthash', sha1('an older version'), ['id' => $link->get_id()]);
        $this->assertSame(1, usage::sync_all_links(), 'stale link');
        $this->assertSame($hash, $this->stored_hash($link));
        $this->assertSame(0, usage::sync_all_links(), 'second run');
    }

    /**
     * Links to items of another content type are left alone.
     */
    public function test_sync_task_ignores_other_content_types(): void {
        global $DB;
        $this->resetAfterTest();
        [, $item, $context] = $this->setup_item();
        $link = link_helper::create_link($item, $context);
        $before = $this->stored_hash($link);
        $this->assertSame(sha1(''), $before);
        $DB->set_field('contentbank_content', 'contenttype', 'contenttype_h5p', ['id' => $item->get_id()]);

        $this->assertSame(0, usage::sync_all_links());
        $this->assertSame($before, $this->stored_hash($link));
    }

    /**
     * The task reports what it did in the task log.
     */
    public function test_sync_task_reports_its_count(): void {
        $this->resetAfterTest();
        [, $item, $context] = $this->setup_item();
        link_helper::create_link($item, $context);

        $this->expectOutputRegex('/Refreshed 1 media bank link\(s\) that were out of date\./');
        (new task\sync_links())->execute();
    }
}
