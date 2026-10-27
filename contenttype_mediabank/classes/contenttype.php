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

    /**
     * Serve a media bank file.
     *
     * Core forces a download when this returns false. Files whose extension is on the allow-list are
     * sent inline so they can play or display. Anything else, including SVG, stays download-only.
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

        $file = get_file_storage()->get_file($context->id, 'contentbank', 'public', $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!self::is_safe_to_serve_inline($file, $extension)) {
            return false;
        }

        header('X-Content-Type-Options: nosniff');
        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    /**
     * Whether a file may be sent inline.
     *
     * The extension must be on the allow-list, Moodle's stored MIME type must match it, and the real
     * content must not look like text, HTML, XML or script (for example HTML renamed to .png).
     *
     * @param \stored_file $file The file to check.
     * @param string $extension Lower-case extension without the dot.
     * @return bool
     */
    protected static function is_safe_to_serve_inline(\stored_file $file, string $extension): bool {
        if (!array_key_exists($extension, self::INLINE_MIMETYPES)) {
            return false;
        }
        if ($file->get_mimetype() !== self::INLINE_MIMETYPES[$extension]) {
            return false;
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file->get_content_file_location());
        if (
            $detected === false || str_starts_with($detected, 'text/') || str_contains($detected, 'xml')
                || str_contains($detected, 'html') || str_contains($detected, 'javascript')
        ) {
            return false;
        }
        return true;
    }

    #[\Override]
    public function get_view_content(\core_contentbank\content $content): string {
        $html = parent::get_view_content($content);
        $url = $content->get_file_url();
        $file = $content->get_file();
        if (!$url || !$file) {
            return $html;
        }
        $name = s($content->get_name());
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $mime = self::INLINE_MIMETYPES[$extension] ?? '';

        if (str_starts_with($mime, 'video/')) {
            $media = \html_writer::tag('video', '', ['src' => $url, 'controls' => 'controls',
                'preload' => 'metadata', 'class' => 'w-100', 'aria-label' => $name]);
        } else if (str_starts_with($mime, 'audio/')) {
            $media = \html_writer::tag('audio', '', ['src' => $url, 'controls' => 'controls',
                'preload' => 'metadata', 'class' => 'w-100', 'aria-label' => $name]);
        } else if ($mime === 'application/pdf') {
            $media = \html_writer::tag('iframe', '', ['src' => $url, 'title' => $name,
                'class' => 'w-100', 'style' => 'height: 80vh;']);
        } else if (str_starts_with($mime, 'image/')) {
            $media = \html_writer::empty_tag('img', ['src' => $url, 'alt' => $name, 'class' => 'img-fluid']);
        } else {
            $media = \html_writer::div(get_string('unsupportedpreview', 'contenttype_mediabank'));
        }
        return $media . $html;
    }

    #[\Override]
    protected function get_implemented_features(): array {
        return [self::CAN_UPLOAD, self::CAN_DOWNLOAD, self::CAN_COPY];
    }

    #[\Override]
    public function get_manageable_extensions(): array {
        $extensions = array_map(fn($ext) => ".$ext", array_keys(self::INLINE_MIMETYPES));
        // SVG can contain scripts, so it is stored but never served inline.
        $extensions[] = '.svg';
        return $extensions;
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
