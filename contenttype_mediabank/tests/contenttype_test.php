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

/**
 * Tests for the media bank content type.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \contenttype_mediabank\contenttype
 */
final class contenttype_test extends \advanced_testcase {
    /**
     * The content type manages all four media kinds. SVG is managed only when an admin allows it, and is never served inline.
     */
    public function test_manageable_extensions(): void {
        $this->resetAfterTest();
        unset_config('allowsvg', 'contenttype_mediabank');
        upload_rules::purge_caches();
        $type = new contenttype(\context_system::instance());
        $extensions = $type->get_manageable_extensions();
        foreach (['.mp4', '.mp3', '.pdf', '.png'] as $expected) {
            $this->assertContains($expected, $extensions);
        }
        $this->assertNotContains('.svg', $extensions, 'SVG is off by default.');
        $this->assertArrayNotHasKey('svg', contenttype::INLINE_MIMETYPES);

        set_config('allowsvg', 1, 'contenttype_mediabank');
        upload_rules::purge_caches();
        $this->assertContains('.svg', (new contenttype(\context_system::instance()))->get_manageable_extensions());
    }

    /**
     * Upload a string as a media bank item in the system context.
     *
     * @param string $filename File name, which decides the content type.
     * @param string $data File content.
     * @return \core_contentbank\content
     */
    protected function upload_item(string $filename, string $data): \core_contentbank\content {
        $context = \context_system::instance();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ], $data);
        $content = (new \core_contentbank\contentbank())->create_content_from_file($context, get_admin()->id, $file);
        $this->assertNotNull($content);
        return $content;
    }

    /**
     * Admins can upload, and the item is handled by this content type.
     */
    public function test_upload_by_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload_item('doc.pdf', "%PDF-1.4\n%%EOF");
        $this->assertInstanceOf(content::class, $content);
        $this->assertSame('contenttype_mediabank', $content->get_content_type());
    }

    /**
     * Files that look like HTML or script are never served inline, whatever their extension.
     */
    public function test_inline_serving_checks_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $method = new \ReflectionMethod(contenttype::class, 'is_safe_to_serve_inline');

        $good = $this->upload_item('ok.png', $png)->get_file();
        $this->assertTrue($method->invoke(null, $good, 'png'));

        // Upload refuses this, so store it directly to prove serving would also refuse it.
        $html = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => 999,
            'filepath' => '/',
            'filename' => 'evil.png',
        ], '<html><script>alert(1)</script></html>');
        $this->assertFalse($method->invoke(null, $html, 'png'));

        set_config('allowsvg', 1, 'contenttype_mediabank');
        upload_rules::purge_caches();
        $svg = $this->upload_item('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
        $this->assertFalse($method->invoke(null, $svg->get_file(), 'svg'));

        // The item page shows it as a picture, and the file is sent only as a download that cannot run as a page.
        $page = $svg->get_content_type_instance()->get_view_content($svg);
        $this->assertStringContainsString('<img', $page);
        $headers = implode("\n", contenttype::SVG_HEADERS);
        $this->assertStringContainsString('nosniff', $headers);
        $this->assertStringContainsString("Content-Security-Policy: sandbox; default-src 'none'", $headers);
    }

    /**
     * A linked copy (file reference) follows the bank item when it is replaced.
     */
    public function test_link_follows_replace(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload_item('doc.pdf', "%PDF-1.4\nversion one\n%%EOF");
        $source = $content->get_file();

        $repos = \repository::get_instances(['type' => 'contentbank']);
        $repo = reset($repos);
        $this->assertNotEmpty($repo, 'The contentbank repository instance is missing.');

        $usercontext = \context_user::instance($USER->id);
        $reference = \file_storage::pack_reference([
            'contextid' => $source->get_contextid(),
            'component' => $source->get_component(),
            'filearea' => $source->get_filearea(),
            'itemid' => $source->get_itemid(),
            'filepath' => $source->get_filepath(),
            'filename' => $source->get_filename(),
        ]);
        $alias = get_file_storage()->create_file_from_reference([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'doc.pdf',
        ], $repo->id, $reference);
        $this->assertSame($source->get_contenthash(), $alias->get_contenthash());

        $replacement = get_file_storage()->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'doc.pdf',
        ], "%PDF-1.4\nversion two\n%%EOF");
        (new contenttype(\context_system::instance()))->replace_content($replacement, $content);

        $alias = get_file_storage()->get_file_by_id($alias->get_id());
        $this->assertSame($content->get_file()->get_contenthash(), $alias->get_contenthash());
        $this->assertNotSame($source->get_contenthash(), $alias->get_contenthash());
    }
}
