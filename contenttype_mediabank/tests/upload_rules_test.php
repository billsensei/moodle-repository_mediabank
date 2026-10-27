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
     * @param string \$code Expected error code.
     * @param callable \$action What to try.
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
     * SVG is off by default, and when switched on it is stored but never served inline.
     */
    public function test_svg_off_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        try {
            $this->upload('x.svg', media_samples::get('svg'));
            $this->fail('SVG was accepted by default.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornotallowed', $e->errorcode);
        }

        set_config('allowsvg', 1, 'contenttype_mediabank');
        $content = $this->upload('x.svg', media_samples::get('svg'));
        $method = new \ReflectionMethod(contenttype::class, 'is_safe_to_serve_inline');
        $this->assertFalse($method->invoke(null, $content->get_file(), 'svg'));
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
