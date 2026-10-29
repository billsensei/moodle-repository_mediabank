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

use contenttype_mediabank\tests\media_samples;

/**
 * Tests for the media bank upload rules, run on both upload and replace.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(upload_rules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(contenttype::class)]
final class upload_rules_test extends \advanced_testcase {
    /**
     * Put a draft file in place.
     *
     * @param string $filename File name.
     * @param string $data Content.
     * @return \stored_file
     */
    protected function draft(string $filename, string $data): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ], $data);
    }

    /**
     * The content type for the system context.
     *
     * @return contenttype
     */
    protected function type(): contenttype {
        return new contenttype(\context_system::instance());
    }

    /**
     * Upload through the content type.
     *
     * @param string $filename File name.
     * @param string $data Content.
     * @return content
     */
    protected function upload(string $filename, string $data): content {
        return $this->type()->upload_content($this->draft($filename, $data));
    }

    /**
     * Assert that an action is refused with the given error code.
     *
     * @param string $code Expected error code.
     * @param callable $action What to try.
     */
    protected function assert_refused(string $code, callable $action): void {
        try {
            $action();
            $this->fail("Expected the rejection $code, but the file was accepted.");
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    /**
     * Extensions with a sample that must be accepted by default.
     *
     * @return array
     */
    public static function accepted_provider(): array {
        $cases = [];
        foreach (['mp4', 'webm', 'mp3', 'm4a', 'ogg', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'] as $extension) {
            $cases[$extension] = [$extension];
        }
        return $cases;
    }

    /**
     * Every managed kind is accepted on upload, and its kind is recorded.
     *
     * @dataProvider accepted_provider
     * @param string $extension Extension without the dot.
     */
    public function test_upload_accepts_valid_files(string $extension): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload("sample.$extension", media_samples::get($extension));
        $this->assertSame(upload_rules::get_kind($extension), $content->get_metadata()['kind']);
    }

    /**
     * Real-world variants: an mp3 with a large ID3 tag and an mp4 from HandBrake or Final Cut (M4V brand).
     */
    public function test_upload_accepts_real_world_variants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->upload('podcast.mp3', media_samples::get('mp3-bigtag'));
        $this->upload('export.mp4', media_samples::get('mp4-m4v'));
    }

    /**
     * An ID3 tag does not let other content through under an audio name.
     */
    public function test_id3_tag_does_not_hide_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tag = "ID3\x03\x00\x00\x00\x00\x00\x0a" . str_repeat("\0", 10);
        $this->assert_refused('errorcontentmismatch', fn() => $this->upload('sneaky.mp3', $tag . '<html><script></script></html>'));
    }

    /**
     * An ID3 header that cannot be skipped cleanly fails closed: a size past the end of the file, or invalid size bytes.
     */
    public function test_bad_id3_header_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $frame = "\xFF\xFB\x90\x00" . str_repeat("\0", 64);
        // Claims a 1 MB tag in a tiny file, with a valid frame right after the header.
        $toolong = "ID3\x03\x00\x00\x00\x40\x00\x00" . $frame;
        $this->assert_refused('errorcontentmismatch', fn() => $this->upload('long.mp3', $toolong));
        // Size bytes with the top bit set are not valid.
        $invalid = "ID3\x03\x00\x00\xFF\xFF\xFF\xFF" . $frame;
        $this->assert_refused('errorcontentmismatch', fn() => $this->upload('invalid.mp3', $invalid));
    }

    /**
     * A tag with the footer flag is skipped including its footer.
     */
    public function test_id3_footer_is_skipped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tag = 20;
        $data = "ID3\x04\x00\x10\x00\x00\x00\x14" . str_repeat("\0", $tag) . "3DI\x04\x00\x10\x00\x00\x00\x14"
            . "\xFF\xFB\x90\x00" . str_repeat("\0", 64);
        $this->upload('footer.mp3', $data);
    }

    /**
     * QuickTime-brand mp4 files, such as iPhone recordings, are accepted.
     */
    public function test_quicktime_brand_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->upload('phone.mp4', media_samples::get('mp4-quicktime'));
    }

    /**
     * Image size is kept with the item.
     */
    public function test_image_dimensions_are_recorded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $metadata = $this->upload('one.png', media_samples::get('png'))->get_metadata();
        $this->assertSame(1, $metadata['width']);
        $this->assertSame(1, $metadata['height']);
    }

    /**
     * Content that is not what the extension says is refused, however it is named.
     */
    public function test_upload_rejects_wrong_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (['png', 'pdf', 'mp4', 'mp3', 'ogg'] as $extension) {
            try {
                $this->upload("fake.$extension", '<html><script>alert(1)</script></html>');
                $this->fail("HTML named .$extension was accepted.");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorcontentmismatch', $e->errorcode);
            }
        }
        // A real file of another kind is also refused.
        $this->assert_refused('errorcontentmismatch', fn() => $this->upload('pdf-as.png', media_samples::get('pdf')));
    }

    /**
     * An empty file is refused.
     */
    public function test_upload_rejects_empty_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assert_refused('errorempty', fn() => $this->upload('empty.png', ''));
    }

    /**
     * SVG is accepted by default and is never served inline. An admin can switch it off.
     */
    public function test_svg_accepted_by_default_and_never_inline(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // A site that has never saved the setting, as a new install is before anyone opens the page.
        unset_config('allowsvg', 'contenttype_mediabank');
        upload_rules::purge_caches();
        $this->assertTrue(upload_rules::is_svg_allowed());
        $content = $this->upload('x.svg', media_samples::get('svg'));
        $this->assertSame('image', $content->get_metadata()['kind']);
        $method = new \ReflectionMethod(contenttype::class, 'is_safe_to_serve_inline');
        $this->assertFalse($method->invoke(null, $content->get_file(), 'svg'));

        set_config('allowsvg', 0, 'contenttype_mediabank');
        $this->assertFalse(upload_rules::is_svg_allowed());
        $this->assert_refused('errornotallowed', fn() => $this->upload('y.svg', media_samples::get('svg')));
    }

    /**
     * An SVG is checked when it is uploaded: a plain one is accepted and one with a script is refused. The many shapes of both are
     * in svg_validator_test.
     */
    public function test_svg_is_checked_on_upload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowsvg', 1, 'contenttype_mediabank');
        upload_rules::purge_caches();
        $ns = 'xmlns="http://www.w3.org/2000/svg"';

        $ok = $this->upload('ok.svg', "<svg $ns><rect width=\"1\" height=\"1\"/></svg>");
        $this->assertSame('image', $ok->get_metadata()['kind']);
        $this->assert_refused('errorsvgactive', fn() => $this->upload('bad.svg', "<svg $ns><script>alert(1)</script></svg>"));
        $this->assert_refused('errorsvgactive', fn() => $this->upload('broken.svg', "<svg $ns><g></svg>"));
        // A replacement is checked in the same way.
        $item = $this->upload('keep.svg', "<svg $ns/>");
        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'keep.svg',
        ], "<svg $ns onload=\"alert(1)\"/>");
        $this->assert_refused('errorsvgactive', fn() => $item->get_content_type_instance()->replace_content($draft, $item));
    }

    /**
     * An SVG over the size limit is refused as too large, before it is read into memory.
     */
    public function test_oversized_svg_is_refused_as_too_large(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowsvg', 1, 'contenttype_mediabank');
        upload_rules::purge_caches();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><!-- ' . str_repeat('x', svg_validator::MAX_BYTES) . ' --></svg>';
        $this->assert_refused('errortoolarge', fn() => $this->upload('big.svg', $svg));
    }

    /**
     * A kind the admin has switched off is refused.
     */
    public function test_disabled_kind_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowedkinds', 'image', 'contenttype_mediabank');
        $this->upload('ok.png', media_samples::get('png'));
        $this->assert_refused('errornotallowed', fn() => $this->upload('no.mp4', media_samples::get('mp4')));
    }

    /**
     * A file over the limit for its kind is refused, and the limit of another kind does not matter.
     */
    public function test_oversize_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbytes_image', 10, 'contenttype_mediabank');
        $this->upload('ok.pdf', media_samples::get('pdf'));
        $this->assert_refused('errortoolarge', fn() => $this->upload('big.png', media_samples::get('png')));
    }

    /**
     * Defaults allow the 500 MB videos this plugin is for.
     */
    public function test_default_video_limit_allows_500mb(): void {
        $this->resetAfterTest();
        $this->assertGreaterThanOrEqual(500 * 1024 * 1024, upload_rules::get_max_bytes('video'));
    }

    /**
     * A replacement with the same extension passes, and its metadata is refreshed.
     */
    public function test_replace_accepts_same_extension(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload('pic.png', media_samples::get('png'));
        $replaced = $this->type()->replace_content($this->draft('other.png', media_samples::get('png')), $content);
        $this->assertSame('pic.png', $replaced->get_file()->get_filename());
        $this->assertSame('image', $replaced->get_metadata()['kind']);
    }

    /**
     * A replacement must keep the extension, because core keeps the old file name.
     */
    public function test_replace_rejects_other_extension(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload('pic.png', media_samples::get('png'));
        $this->assert_refused(
            'errorextensionchange',
            fn() => $this->type()->replace_content($this->draft('pic.jpg', media_samples::get('jpg')), $content)
        );
    }

    /**
     * A replacement is checked for content too, and the old file is untouched when it fails.
     */
    public function test_replace_rejects_wrong_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = $this->upload('pic.png', media_samples::get('png'));
        $hash = $content->get_file()->get_contenthash();
        try {
            $this->type()->replace_content($this->draft('pic.png', '<html></html>'), $content);
            $this->fail('HTML was accepted as a replacement.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcontentmismatch', $e->errorcode);
        }
        $this->assertSame($hash, $content->get_file()->get_contenthash());
    }

    /**
     * The settings that change the extension list clear the Content Bank caches, and doing so takes effect.
     */
    public function test_settings_purge_extension_caches(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \context_system::instance();
        $bank = new \core_contentbank\contentbank();
        $this->assertStringContainsString('.mp4', $bank->get_supported_extensions_as_string($context));

        set_config('allowedkinds', 'image', 'contenttype_mediabank');
        upload_rules::purge_caches();
        $supported = $bank->get_supported_extensions_as_string($context);
        $this->assertStringNotContainsString('.mp4', $supported);
        $this->assertStringContainsString('.png', $supported);

        // The admin settings really call the purge.
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $admin = admin_get_root(true, true);
        $page = $admin->locate('contentbanksettingmediabank');
        $this->assertNotNull($page, 'The settings page is missing.');
        foreach (['allowedkinds', 'allowsvg'] as $name) {
            $setting = $page->settings->{'contenttype_mediabank' . $name};
            $this->assertSame([upload_rules::class, 'purge_caches'], $setting->updatedcallback);
        }
    }
}
