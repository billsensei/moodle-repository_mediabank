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

namespace contenttype_mediabank;

use contenttype_mediabank\form\editor;
use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;

/**
 * Tests for the editor form, captions and who may fetch captions and earlier versions.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(captions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(editor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(content::class)]
final class editor_test extends \advanced_testcase {
    /**
     * Put a file in the current user's draft area.
     *
     * @param string $filename File name.
     * @param string $data File content.
     * @return int Draft item id.
     */
    protected function draft(string $filename, string $data): int {
        global $USER;
        $itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $data);
        return $itemid;
    }

    /**
     * Load an item again from the database.
     *
     * @param int $id Item id.
     * @return content
     */
    protected function reload(int $id): content {
        global $DB;
        return new content($DB->get_record('contentbank_content', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Build the editor form for an item.
     *
     * @param content $item The item.
     * @return editor
     */
    protected function form(content $item): editor {
        return new editor(null, [
            'contextid' => $item->get_contextid(),
            'plugin' => 'mediabank',
            'id' => $item->get_id(),
            'heading' => $item->get_name(),
            'library' => null,
        ]);
    }

    /**
     * Only a real WebVTT file passes.
     */
    public function test_caption_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cases = [
            "WEBVTT\n\n00:00.000 --> 00:01.000\nHi" => true,
            "\xEF\xBB\xBFWEBVTT - title\n" => true,
            "WEBVTT" => true,
            "WEBVTTx\n" => false,
            "<html>WEBVTT</html>" => false,
            "1\n00:00:00,000 --> 00:00:01,000\nHi" => false,
            '' => false,
        ];
        foreach ($cases as $data => $expected) {
            $itemid = $this->draft('c.vtt', (string) $data);
            $file = get_file_storage()->get_file(
                \context_user::instance(get_admin()->id)->id,
                'user',
                'draft',
                $itemid,
                '/',
                'c.vtt'
            );
            $this->assertSame($expected, captions::is_valid($file), json_encode($data));
        }
    }

    /**
     * An image needs a text alternative, and the form refuses to save without it.
     */
    public function test_image_requires_alt_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('pic.png', media_samples::get('png'));
        $form = $this->form($item);

        $errors = $form->validation(['alt' => '  '], []);
        $this->assertArrayHasKey('alt', $errors);
        $this->assertArrayNotHasKey('alt', $form->validation(['alt' => 'A red dot'], []));

        $form->save_content((object) ['alt' => ' A red dot ']);
        $reloaded = $this->reload($item->get_id());
        $this->assertSame('A red dot', $reloaded->get_alt());
        // The facts saved at upload are still there.
        $this->assertSame('image', $reloaded->get_metadata()['kind']);
    }

    /**
     * Transcript and captions are saved, replaced and removed with the form.
     */
    public function test_transcript_and_captions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'));
        $vtt = "WEBVTT\n\n00:00.000 --> 00:01.000\nHello";

        $form = $this->form($item);
        $form->save_content((object) ['transcript' => 'Hello', 'captions' => $this->draft('my name.vtt', $vtt)]);
        $reloaded = $this->reload($item->get_id());
        $this->assertSame('Hello', $reloaded->get_transcript());
        $file = captions::get($reloaded);
        $this->assertSame($vtt, $file->get_content());
        // Always stored under the same name, whatever the upload was called.
        $this->assertSame(captions::FILENAME, $file->get_filename());
        $this->assertEmpty($file->get_userid());

        // A draft area with no file removes the captions; an empty transcript removes the transcript.
        $emptydraft = file_get_unused_draft_itemid();
        $this->form($reloaded)->save_content((object) ['transcript' => '', 'captions' => $emptydraft]);
        $reloaded = $this->reload($item->get_id());
        $this->assertSame('', $reloaded->get_transcript());
        $this->assertNull(captions::get($reloaded));
    }

    /**
     * A caption file that is not WebVTT is refused by the form.
     */
    public function test_invalid_caption_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'));
        $errors = $this->form($item)->validation(['captions' => $this->draft('c.vtt', '<script>x</script>')], []);
        $this->assertArrayHasKey('captions', $errors);
    }

    /**
     * Restore and "convert links to copies" work from the form.
     */
    public function test_form_restores_and_detaches(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = media_samples::get('pdf');
        $item = link_helper::create_item('doc.pdf', $first);
        $link = link_helper::create_link($item, \context_course::instance($course->id));
        $draft = $this->draft('doc.pdf', $first . "\n% second");
        $file = get_file_storage()->get_file(\context_user::instance(get_admin()->id)->id, 'user', 'draft', $draft, '/', 'doc.pdf');
        $item->get_content_type_instance()->replace_content($file, $item);
        $this->assertSame(1, usage::count_links($item));

        $this->form($item)->save_content((object) ['restoreversion' => 1, 'detachlinks' => 1]);

        $item = $this->reload($item->get_id());
        $this->assertSame(sha1($first), $item->get_file()->get_contenthash());
        $this->assertSame(0, usage::count_links($item));
        // The link became a copy: it keeps the restored bytes and no longer follows the item.
        $copy = get_file_storage()->get_file_by_id($link->get_id());
        $this->assertFalse($copy->is_external_file());
        $this->assertSame($first, $copy->get_content());
    }

    /**
     * Captions and earlier versions move with the item and go with it when it is deleted.
     */
    public function test_captions_follow_delete_and_move(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'));
        $source = get_file_storage()->get_file(
            \context_user::instance(get_admin()->id)->id,
            'user',
            'draft',
            $this->draft('c.vtt', "WEBVTT\n"),
            '/',
            'c.vtt'
        );
        captions::save($item, $source);

        $item->get_content_type_instance()->move_content($item, \context_course::instance($course->id));
        $item = $this->reload($item->get_id());
        $this->assertEquals(\context_course::instance($course->id)->id, captions::get($item)->get_contextid());

        $item->get_content_type_instance()->delete_content($item);
        $this->assertSame(0, $this->count_caption_files());
    }

    /**
     * Caption files whose item is gone are swept.
     */
    public function test_orphaned_captions_are_purged(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'));
        $source = get_file_storage()->get_file(
            \context_user::instance(get_admin()->id)->id,
            'user',
            'draft',
            $this->draft('c.vtt', "WEBVTT\n"),
            '/',
            'c.vtt'
        );
        captions::save($item, $source);
        // Core's privacy deletion removes the row without calling the content type.
        $DB->delete_records('contentbank_content', ['id' => $item->get_id()]);
        $this->assertSame(1, $this->count_caption_files());
        $this->assertSame(1, captions::purge_orphans());
        $this->assertSame(0, $this->count_caption_files());
    }

    /**
     * Number of caption files, not counting directory rows.
     *
     * @return int
     */
    protected function count_caption_files(): int {
        global $DB;
        return $DB->count_records_select(
            'files',
            "component = 'contenttype_mediabank' AND filearea = :area AND filename <> '.'",
            ['area' => captions::FILEAREA]
        );
    }

    /**
     * A student cannot fetch an earlier version, and nobody can fetch a file for an item in another context.
     */
    public function test_pluginfile_refuses(): void {
        global $CFG;
        require_once($CFG->dirroot . '/contentbank/contenttype/mediabank/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'), $context);
        $draft = $this->draft('doc.pdf', media_samples::get('pdf') . "\n% two");
        $file = get_file_storage()->get_file(\context_user::instance(get_admin()->id)->id, 'user', 'draft', $draft, '/', 'doc.pdf');
        $item->get_content_type_instance()->replace_content($file, $item);
        $this->assertCount(1, versions::get_versions($item));

        $this->setUser($student);
        $args = [$item->get_id(), '1', 'doc.pdf'];
        $this->assertFalse(contenttype_mediabank_pluginfile($course, null, $context, 'versions', $args, true));

        // The item lives in the first course, so naming another course's context is refused.
        $this->setAdminUser();
        $othercontext = \context_course::instance($other->id);
        $this->assertFalse(contenttype_mediabank_pluginfile($other, null, $othercontext, 'versions', $args, true));
        // Unknown areas, and the public area (core serves that), are not handled here.
        $this->assertFalse(contenttype_mediabank_pluginfile($course, null, $context, 'public', $args, true));
        // Captions only come under their fixed name.
        $wrongname = [$item->get_id(), 'x.vtt'];
        $this->assertFalse(contenttype_mediabank_pluginfile($course, null, $context, 'captions', $wrongname, false));
    }

    /**
     * Captions of an unlisted item are not served to someone who may not see unlisted content.
     */
    public function test_captions_of_unlisted_item_are_hidden(): void {
        global $CFG;
        require_once($CFG->dirroot . '/contentbank/contenttype/mediabank/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('contenttype/mediabank:access', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('moodle/contentbank:access', CAP_ALLOW, $roleid, $context->id, true);
        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        role_assign($roleid, $viewer->id, $context->id);

        $item = link_helper::create_item('talk.mp3', media_samples::get('mp3'), $context);
        $source = get_file_storage()->get_file(
            \context_user::instance(get_admin()->id)->id,
            'user',
            'draft',
            $this->draft('c.vtt', "WEBVTT\n"),
            '/',
            'c.vtt'
        );
        captions::save($item, $source);
        $item->set_visibility(content::VISIBILITY_UNLISTED);

        $this->setUser($viewer);
        $args = [$item->get_id(), captions::FILENAME];
        $this->assertFalse(contenttype_mediabank_pluginfile($course, null, $context, 'captions', $args, false));
    }
}
