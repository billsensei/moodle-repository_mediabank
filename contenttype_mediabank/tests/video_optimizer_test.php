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
use contenttype_mediabank\tests\mp4_builder;

/**
 * Tests for preparing uploaded videos for streaming in the background.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(video_optimizer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\optimize_video::class)]
final class video_optimizer_test extends \advanced_testcase {
    /**
     * Queued optimize tasks for an item.
     *
     * @param int $contentid The item.
     * @return int
     */
    protected function queued(int $contentid): int {
        $count = 0;
        foreach (\core\task\manager::get_adhoc_tasks(task\optimize_video::class) as $task) {
            $count += (int) ($task->get_custom_data()->contentid == $contentid);
        }
        return $count;
    }

    /**
     * An upload queues one task, the task changes the file, and every link gets the new file.
     */
    public function test_upload_queues_and_task_optimizes_and_links_follow(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = mp4_builder::build();
        $item = link_helper::create_item('lecture.mp4', $source);
        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($resource->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        $link = link_helper::create_link($item, $context);
        $link->sync_external_file();
        $before = $DB->get_field('files', 'contenthash', ['id' => $link->get_id()]);
        $this->assertSame(1, $this->queued($item->get_id()));
        $modifier = $DB->get_field('contentbank_content', 'usermodified', ['id' => $item->get_id()]);
        $this->setUser(0);

        $this->expectOutputRegex('/Media bank item \d+: optimized/');
        $this->runAdhocTasks(task\optimize_video::class);

        $item = new content($DB->get_record('contentbank_content', ['id' => $item->get_id()], '*', MUST_EXIST));
        $result = $item->get_file()->get_content();
        $this->assertNotSame($source, $result);
        $this->assertSame(strlen($source), strlen($result));
        $this->assertLessThan(strpos($result, 'mdat'), strpos($result, 'moov'));
        $this->assertTrue(mp4_builder::offsets_hit_data($result));
        $this->assertSame((int) $modifier, (int) $item->get_content()->usermodified);
        // The file as uploaded is kept as an earlier version.
        $versions = versions::get_versions($item);
        $this->assertCount(1, $versions);
        $this->assertSame($source, reset($versions)->get_content());
        // The link was refreshed to the new file, which is read from the database, not from the link.
        $after = $DB->get_field('files', 'contenthash', ['id' => $link->get_id()]);
        $this->assertNotSame($before, $after);
        $this->assertSame($item->get_file()->get_contenthash(), $after);
        // No working copy is left, and a second run has nothing to do and queues nothing more.
        $this->assertCount(0, get_file_storage()->get_area_files(
            $item->get_contextid(),
            'contenttype_mediabank',
            'optimizing',
            $item->get_id()
        ));
        $this->assertSame(video_optimizer::NOTHING_TO_DO, video_optimizer::optimize($item->get_id()));
        $this->assertSame(0, $this->queued($item->get_id()));
    }

    /**
     * Nothing is queued when the setting is off, or for other kinds of file.
     */
    public function test_only_mp4_is_queued_and_the_setting_turns_it_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $audio = link_helper::create_item('song.mp3', media_samples::get('mp3'));
        $webm = link_helper::create_item('clip.webm', media_samples::get('webm'));
        $this->assertSame(0, $this->queued($audio->get_id()));
        $this->assertSame(0, $this->queued($webm->get_id()));

        set_config('optimizevideo', 0, 'contenttype_mediabank');
        $off = link_helper::create_item('off.mp4', mp4_builder::build());
        $this->assertSame(0, $this->queued($off->get_id()));

        set_config('optimizevideo', 1, 'contenttype_mediabank');
        $on = link_helper::create_item('on.mp4', mp4_builder::build());
        $this->assertSame(1, $this->queued($on->get_id()));
    }

    /**
     * Without the setting stored, the default is on.
     */
    public function test_default_is_on(): void {
        $this->resetAfterTest();
        unset_config('optimizevideo', 'contenttype_mediabank');
        $this->assertTrue(video_optimizer::is_enabled());
    }

    /**
     * A video that is already streamable, or is not an index-last MP4, is not touched and keeps its version history.
     */
    public function test_files_that_need_nothing_are_left_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ready = link_helper::create_item('ready.mp4', mp4_builder::build(['moovfirst' => true]));
        $odd = link_helper::create_item('odd.mp4', media_samples::get('mp4'));

        $this->assertSame(video_optimizer::NOTHING_TO_DO, video_optimizer::optimize($ready->get_id()));
        $this->assertSame(video_optimizer::NOTHING_TO_DO, video_optimizer::optimize($odd->get_id()));
        $this->assertSame(mp4_builder::build(['moovfirst' => true]), $ready->get_file()->get_content());
        $this->assertCount(0, versions::get_versions($ready));
    }

    /**
     * An item that is gone is not an error.
     */
    public function test_missing_item_is_not_an_error(): void {
        $this->resetAfterTest();
        $this->assertSame(video_optimizer::GONE, video_optimizer::optimize(987654));
    }

    /**
     * If the item is replaced before the task runs, the task works on the new file, not the old one.
     */
    public function test_replaced_before_the_task_runs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('v.mp4', mp4_builder::build());
        $newer = mp4_builder::build(['before' => mp4_builder::box('free', str_repeat("\0", 12))]);
        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'v.mp4',
        ], $newer);
        $item = $item->get_content_type_instance()->replace_content($draft, $item);

        $this->assertSame(video_optimizer::OPTIMIZED, video_optimizer::optimize($item->get_id()));

        $result = $item->get_file()->get_content();
        $this->assertSame(strlen($newer), strlen($result));
        $this->assertTrue(mp4_builder::offsets_hit_data($result));
    }

    /**
     * A file that cannot be optimized is left as it is, is not tried again, and is tried once more if it is replaced.
     */
    public function test_failed_file_is_not_retried(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $damaged = mp4_builder::build();
        $damaged = substr_replace($damaged, pack('N', 1000), strpos($damaged, 'stco') + 8, 4);
        $item = link_helper::create_item('bad.mp4', $damaged);
        $modified = $DB->get_field('contentbank_content', 'timemodified', ['id' => $item->get_id()]);
        $this->assertSame(1, $this->queued($item->get_id()));
        $this->expectOutputRegex('/was left as it is/');

        $this->assertSame(video_optimizer::FAILED, video_optimizer::optimize($item->get_id()));

        $item = new content($DB->get_record('contentbank_content', ['id' => $item->get_id()], '*', MUST_EXIST));
        $this->assertSame($damaged, $item->get_file()->get_content());
        $this->assertCount(0, versions::get_versions($item));
        $this->assertSame((int) $modified, (int) $item->get_content()->timemodified, 'not an edit');
        $this->assertFalse(video_optimizer::queue($item), 'the same file is not queued again');
        $this->assertSame(0, video_optimizer::queue_all());

        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'bad.mp4',
        ], mp4_builder::build());
        $item = $item->get_content_type_instance()->replace_content($draft, $item);
        $this->assertTrue(video_optimizer::queue($item), 'a replacement gets its own chance');
    }

    /**
     * Marking a failure adds one key to what is stored now. Edits made while the file was being worked on are not lost, and a
     * file that has been replaced meanwhile is not marked.
     */
    public function test_marking_a_failure_keeps_edits_made_meanwhile(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('m.mp4', mp4_builder::build());
        $hash = $item->get_file()->get_contenthash();
        $mark = new \ReflectionMethod(video_optimizer::class, 'mark_failed');
        // A teacher saves a transcript after the task began and before it gave up.
        $fresh = new content($DB->get_record('contentbank_content', ['id' => $item->get_id()], '*', MUST_EXIST));
        $fresh->merge_metadata(['transcript' => 'Written while the task ran.']);

        $mark->invoke(null, $item->get_id(), $hash);

        $stored = $DB->get_record('contentbank_content', ['id' => $item->get_id()], '*', MUST_EXIST);
        $metadata = (new content($stored))->get_metadata();
        $this->assertSame('Written while the task ran.', $metadata['transcript']);
        $this->assertSame($hash, $metadata['streamfailed']);

        // A hash that is no longer the item's file changes nothing.
        $before = $DB->get_field('contentbank_content', 'configdata', ['id' => $item->get_id()]);
        $mark->invoke(null, $item->get_id(), sha1('some other file'));
        $this->assertSame($before, $DB->get_field('contentbank_content', 'configdata', ['id' => $item->get_id()]));
    }

    /**
     * A bank full of older videos can be queued in one go, once, and only the ones that need it.
     */
    public function test_queue_all_finds_only_videos_that_need_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('optimizevideo', 0, 'contenttype_mediabank');
        $needs = link_helper::create_item('needs.mp4', mp4_builder::build());
        $ready = link_helper::create_item('ready.mp4', mp4_builder::build(['moovfirst' => true]));
        link_helper::create_item('song.mp3', media_samples::get('mp3'));
        set_config('optimizevideo', 1, 'contenttype_mediabank');

        $this->assertSame(1, video_optimizer::queue_all());
        $this->assertSame(1, video_optimizer::queue_all(), 'asking twice does not queue it twice');
        $this->assertSame(1, $this->queued($needs->get_id()));
        $this->assertSame(0, $this->queued($ready->get_id()));
    }
}
