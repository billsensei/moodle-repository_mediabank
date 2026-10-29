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

/**
 * Media bank content type: video, audio, PDF and image files.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contenttype extends \core_contentbank\contenttype {
    /** @var string[] Extension (without dot) => MIME type that may be served inline. */
    public const INLINE_MIMETYPES = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'ogg' => 'audio/ogg',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /** @var string[] Headers sent with an SVG file. It is a download, never a page, and nothing in it may run or load. */
    public const SVG_HEADERS = [
        'X-Content-Type-Options: nosniff',
        "Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:",
    ];

    /**
     * Serve a media bank file.
     *
     * Core forces a download when this returns false. Files whose extension is on the allow-list are
     * sent inline so they can play or display. Anything else stays download-only. An SVG is sent as a download with
     * SVG_HEADERS, which a picture on a page can still show but which never runs as a page.
     *
     * @param \stdClass|null $course Course record.
     * @param \cm_info|null $cm Course module (always null for the content bank).
     * @param \context $context File context.
     * @param string $filearea File area.
     * @param array $args Remaining path: itemid, optional subdirectories, filename.
     * @param bool $forcedownload Whether the user asked for a download.
     * @param array $options Options for send_stored_file().
     * @return bool False to let core serve the file as a download. Otherwise the request ends here.
     */
    public static function pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options): bool {
        if ($filearea !== 'public') {
            return false;
        }
        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        // Core only checks that the user is logged in. Answer a denial as if the file did not exist, and not with false,
        // because core would then serve the file as a download anyway.
        if (!self::can_serve($context, $itemid)) {
            send_file_not_found();
        }

        $file = get_file_storage()->get_file($context->id, 'contentbank', 'public', $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if ($extension === 'svg' && upload_rules::content_matches_extension($file, 'svg')) {
            foreach (self::SVG_HEADERS as $header) {
                header($header);
            }
            send_stored_file($file, 0, 0, true, $options);
            return true;
        }
        if (!self::is_safe_to_serve_inline($file, $extension)) {
            return false;
        }

        header('X-Content-Type-Options: nosniff');
        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    /**
     * Whether the current user may be given the file of an item.
     *
     * The same rules as for seeing the item: access to the media bank in the context, and an item that is public, the
     * user's own, or visible to people who may see unlisted content.
     *
     * @param \context $context Context named by the URL.
     * @param int $itemid Item id named by the URL.
     * @return bool
     */
    public static function can_serve(\context $context, int $itemid): bool {
        global $DB;
        $record = $DB->get_record('contentbank_content', ['id' => $itemid, 'contenttype' => 'contenttype_mediabank']);
        if (!$record || (int) $record->contextid !== (int) $context->id) {
            return false;
        }
        return (new self($context))->can_access() && (new content($record))->is_view_allowed();
    }

    /**
     * Whether a file may be sent inline.
     *
     * The extension must be on the inline allow-list and the first bytes of the file must really be that kind of
     * file. Moodle sets the stored MIME type from the file name, so that field is not used.
     *
     * @param \stored_file $file The file to check.
     * @param string $extension Lower-case extension without the dot.
     * @return bool
     */
    protected static function is_safe_to_serve_inline(\stored_file $file, string $extension): bool {
        return array_key_exists($extension, self::INLINE_MIMETYPES)
            && upload_rules::content_matches_extension($file, $extension);
    }

    #[\Override]
    public function upload_content(\stored_file $file, ?\stdClass $record = null): \core_contentbank\content {
        upload_rules::validate($file);
        $content = parent::upload_content($file, $record);
        $content->merge_metadata(upload_rules::describe($file));
        video_optimizer::queue($content);
        return $content;
    }

    #[\Override]
    public function replace_content(\stored_file $file, \core_contentbank\content $content): \core_contentbank\content {
        $current = $content->get_file();
        upload_rules::validate($file, $current ? upload_rules::get_extension($current->get_filename()) : null);
        versions::keep_current($content, $file);
        $content = parent::replace_content($file, $content);
        $content->merge_metadata(upload_rules::describe($content->get_file()));
        video_optimizer::queue($content);
        return $content;
    }

    #[\Override]
    public function delete_content(\core_contentbank\content $content): bool {
        // Courses and categories delete their content in bulk. Other courses must keep what they use.
        usage::detach_all($content);
        versions::delete_all($content);
        captions::delete($content);
        return parent::delete_content($content);
    }

    #[\Override]
    public function move_content(\core_contentbank\content $content, \context $context): bool {
        // Links point at the item's current context, so they would break. Keep them working as copies.
        usage::detach_all($content);
        $from = $content->get_contextid();
        $moved = parent::move_content($content, $context);
        if ($moved) {
            versions::move_all($from, $context->id, $content->get_id());
            captions::move($from, $context->id, $content->get_id());
        }
        return $moved;
    }

    #[\Override]
    protected function is_delete_allowed(\core_contentbank\content $content): bool {
        // An item that courses link to is not deleted by hand. Its links have to be converted first.
        return usage::count_links($content) === 0;
    }

    #[\Override]
    public function get_view_content(\core_contentbank\content $content): string {
        global $OUTPUT;
        $html = parent::get_view_content($content);
        $url = $content->get_file_url();
        $file = $content->get_file();
        if (!$url || !$file) {
            return $html;
        }
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        // An SVG is shown as a picture. The file is still only ever sent as a download.
        $mime = self::INLINE_MIMETYPES[$extension] ?? ($extension === 'svg' ? 'image/svg+xml' : '');
        $captions = captions::get($content);
        $captionsurl = $captions ? \moodle_url::make_pluginfile_url(
            $captions->get_contextid(),
            'contenttype_mediabank',
            captions::FILEAREA,
            $content->get_id(),
            '/',
            captions::FILENAME
        )->out(false) : '';
        $name = $content->get_name();
        $media = $OUTPUT->render_from_template('contenttype_mediabank/preview', [
            'name' => $name,
            'url' => $url,
            'isvideo' => str_starts_with($mime, 'video/'),
            'isaudio' => str_starts_with($mime, 'audio/'),
            'ispdf' => $mime === 'application/pdf',
            'isimage' => str_starts_with($mime, 'image/'),
            'nopreview' => $mime === '',
            // An image without a text alternative falls back to its name.
            'alt' => $content->get_alt() ?: $name,
            'captionsurl' => $captionsurl,
            'transcript' => $content->get_transcript(),
        ]);
        return $media . $this->render_usage($content) . $html;
    }

    /**
     * Which courses link to the item, shown under the preview.
     *
     * Course names are only shown for courses the user may see. Others are counted.
     *
     * @param \core_contentbank\content $content The item.
     * @return string HTML.
     */
    protected function render_usage(\core_contentbank\content $content): string {
        $courses = usage::get_courses($content);
        $heading = \html_writer::tag('h3', get_string('usageheading', 'contenttype_mediabank'), ['class' => 'h5 mt-3']);
        if (!$courses) {
            return $heading . \html_writer::tag('p', get_string('usagenone', 'contenttype_mediabank'));
        }
        $items = [];
        $hidden = 0;
        $other = 0;
        foreach ($courses as $course) {
            if (!$course->id) {
                $other += $course->links;
            } else if (\core_course_category::can_view_course_info($course)) {
                $name = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
                $items[] = \html_writer::tag('li', get_string(
                    'usagecourse',
                    'contenttype_mediabank',
                    (object) ['name' => $name, 'links' => $course->links]
                ));
            } else {
                $hidden += $course->links;
            }
        }
        if ($hidden) {
            $items[] = \html_writer::tag('li', get_string('usagehidden', 'contenttype_mediabank', $hidden));
        }
        if ($other) {
            $items[] = \html_writer::tag('li', get_string('usageother', 'contenttype_mediabank', $other));
        }
        return $heading . \html_writer::tag('ul', implode('', $items))
            . \html_writer::tag('p', get_string('usagedeletenote', 'contenttype_mediabank'), ['class' => 'text-muted']);
    }

    #[\Override]
    protected function get_implemented_features(): array {
        return [self::CAN_UPLOAD, self::CAN_DOWNLOAD, self::CAN_COPY, self::CAN_EDIT];
    }

    #[\Override]
    public function get_manageable_extensions(): array {
        return array_map(fn($extension) => ".$extension", upload_rules::get_allowed_extensions());
    }

    #[\Override]
    public function get_contenttype_types(): array {
        // Files are uploaded, not created with an editor.
        return [];
    }

    #[\Override]
    protected function is_access_allowed(): bool {
        return true;
    }
}
