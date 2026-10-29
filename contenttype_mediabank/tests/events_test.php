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

use contenttype_mediabank\event\item_linked;
use contenttype_mediabank\event\links_converted;
use contenttype_mediabank\event\version_restored;
use contenttype_mediabank\form\editor;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;

/**
 * Tests for the events the media bank fires, and for the core events it relies on.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(item_linked::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(links_converted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(version_restored::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class events_test extends \advanced_testcase {
    /**
     * Short class names of the events caught so far, then forget them.
     *
     * @param \phpunit_event_sink $sink The sink.
     * @return string[]
     */
    protected function names(\phpunit_event_sink $sink): array {
        $names = array_map(fn($event) => (new \ReflectionClass($event))->getShortName(), $sink->get_events());
        $sink->clear();
        return $names;
    }

    /**
     * Events of one class caught so far.
     *
     * @param \phpunit_event_sink $sink The sink.
     * @param string $class Event class.
     * @return \core\event\base[]
     */
    protected function events_of(\phpunit_event_sink $sink, string $class): array {
        return array_values(array_filter($sink->get_events(), fn($event) => $event instanceof $class));
    }

    /**
     * A File resource in a new course.
     *
     * @return \context_module
     */
    protected function resource_context(): \context_module {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        return $context;
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
     * Core reports upload, replace, view and delete of our items. The report depends on our overrides still calling
     * core, so this fails if one stops.
     */
    public function test_core_events_still_fire_for_our_items(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $this->assertContains('contentbank_content_created', $this->names($sink));

        $this->replace($item, media_samples::get('pdf') . "\n% two");
        $this->assertContains('contentbank_content_updated', $this->names($sink));

        $item->get_content_type_instance()->get_view_content($item);
        $this->assertContains('contentbank_content_viewed', $this->names($sink));

        $item->get_content_type_instance()->delete_content($item);
        $this->assertContains('contentbank_content_deleted', $this->names($sink));
    }

    /**
     * A change made only to captions is still reported as an update, so no event of ours is needed for it.
     */
    public function test_captions_only_change_is_reported_as_update(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'));
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'c.vtt',
        ], "WEBVTT\n");
        $form = new editor(null, [
            'contextid' => $item->get_contextid(),
            'plugin' => 'mediabank',
            'id' => $item->get_id(),
            'heading' => $item->get_name(),
            'library' => null,
        ]);
        $sink = $this->redirectEvents();

        $form->save_content((object) ['transcript' => '', 'captions' => $draftid]);

        $this->assertContains('contentbank_content_updated', $this->names($sink));
    }

    /**
     * Saving a form that links to an item reports the link, once, in the place the link is.
     */
    public function test_saving_a_link_fires_item_linked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $context = $this->resource_context();
        $sink = $this->redirectEvents();

        link_helper::create_link_by_saving_form($item, $context);

        $events = $this->events_of($sink, item_linked::class);
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame($item->get_id(), (int) $event->objectid);
        $this->assertSame($context->id, $event->contextid);
        $this->assertSame('mod_resource', $event->other['component']);
        $this->assertSame('content', $event->other['filearea']);
        $this->assertEquals($item->get_contextid(), $event->other['itemcontextid']);
        $this->assertSame('/contentbank/view.php?id=' . $item->get_id(), $event->get_url()->out_as_local_url(false));
        $this->assertNotEmpty($event->get_description());
        $this->assertNotEmpty($event::get_name());
    }

    /**
     * The link made in the draft area while the teacher is still choosing is not reported, and neither is anything else.
     */
    public function test_nothing_else_fires_item_linked(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $context = $this->resource_context();
        $fs = get_file_storage();
        $sink = $this->redirectEvents();

        // A draft link.
        $repos = \repository::get_instances(['type' => 'contentbank']);
        $repo = reset($repos);
        $source = $item->get_file();
        $fs->create_file_from_reference([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/', 'filename' => 'doc.pdf',
        ], $repo->id, \file_storage::pack_reference([
            'contextid' => $source->get_contextid(), 'component' => $source->get_component(), 'filearea' => $source->get_filearea(),
            'itemid' => $source->get_itemid(), 'filepath' => $source->get_filepath(), 'filename' => $source->get_filename(),
        ]));
        // An ordinary file, and a link made the way a restore makes them.
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content', 'itemid' => 0,
            'filepath' => '/', 'filename' => 'plain.txt',
        ], 'text');
        link_helper::create_link($item, $context, 'mod_resource', 'intro');

        $this->assertSame([], $this->events_of($sink, item_linked::class));
    }

    /**
     * Converting links fires one event with the number converted, on all three paths, and nothing when there is nothing
     * to convert. A course keeps its file.
     */
    public function test_links_converted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $sink = $this->redirectEvents();

        // Nothing linked, nothing reported.
        usage::detach_all($item);
        $this->assertSame([], $this->events_of($sink, links_converted::class));

        // The edit form's "convert links" button.
        $context = $this->resource_context();
        link_helper::create_link($item, $context);
        $form = new editor(null, [
            'contextid' => $item->get_contextid(), 'plugin' => 'mediabank', 'id' => $item->get_id(),
            'heading' => $item->get_name(), 'library' => null,
        ]);
        $form->save_content((object) ['detachlinks' => 1]);
        $events = $this->events_of($sink, links_converted::class);
        $this->assertCount(1, $events);
        $this->assertSame(1, (int) $events[0]->other['count']);
        $this->assertSame($item->get_id(), (int) $events[0]->objectid);
        $this->assertSame($item->get_contextid(), (string) $events[0]->contextid);
        $this->assertNotEmpty($events[0]->get_description());
        $sink->clear();

        // Moving the item.
        $second = $this->resource_context();
        link_helper::create_link($item, $second);
        $target = $this->getDataGenerator()->create_course();
        $item->get_content_type_instance()->move_content($item, \context_course::instance($target->id));
        $this->assertCount(1, $this->events_of($sink, links_converted::class));
        $sink->clear();
        $copy = get_file_storage()->get_file($second->id, 'mod_resource', 'content', 0, '/', 'doc.pdf');
        $this->assertFalse($copy->is_external_file());

        // Deleting the item: the delete still goes through and the course keeps its file.
        $item = new content($GLOBALS['DB']->get_record('contentbank_content', ['id' => $item->get_id()]));
        $third = $this->resource_context();
        $link = link_helper::create_link($item, $third);
        $this->assertTrue($item->get_content_type_instance()->delete_content($item));
        $this->assertCount(1, $this->events_of($sink, links_converted::class));
        $kept = get_file_storage()->get_file_by_id($link->get_id());
        $this->assertFalse($kept->is_external_file());
        $this->assertGreaterThan(0, $kept->get_filesize());
    }

    /**
     * A restored version is reported with its number.
     */
    public function test_version_restored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $this->replace($item, media_samples::get('pdf') . "\n% two");
        $sink = $this->redirectEvents();

        versions::restore($item, 1);

        $events = $this->events_of($sink, version_restored::class);
        $this->assertCount(1, $events);
        $this->assertSame(1, (int) $events[0]->other['version']);
        $this->assertSame($item->get_id(), (int) $events[0]->objectid);
        $this->assertNotEmpty($events[0]->get_description());
        $this->assertNotEmpty($events[0]::get_name());
    }

    /**
     * The events refuse to be made without what they need to describe themselves.
     */
    public function test_events_validate_their_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \context_system::instance();
        foreach ([item_linked::class, links_converted::class, version_restored::class] as $class) {
            try {
                $class::create(['objectid' => 1, 'context' => $context, 'other' => []]);
                $this->fail("$class accepted missing data");
            } catch (\coding_exception $e) {
                $this->assertInstanceOf(\coding_exception::class, $e);
            }
        }
    }
}
