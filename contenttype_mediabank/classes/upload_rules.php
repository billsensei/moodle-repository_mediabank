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
 * Upload rules for the media bank: which types are allowed, how big they may be, and whether the content
 * really is what the extension says.
 *
 * Moodle sets a stored file's MIME type from its file name, so it says nothing about the content. The checks
 * here read the first bytes of the file.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_rules {
    /** @var string[][] Extensions (without dot) managed for each kind. */
    public const KINDS = [
        'video' => ['mp4', 'webm'],
        'audio' => ['mp3', 'm4a', 'ogg'],
        'pdf' => ['pdf'],
        'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
    ];

    /** @var string[][] Content types a content sniff may report for each extension. Anything else is refused. */
    public const ACCEPTED_TYPES = [
        'mp4' => ['video/mp4', 'video/x-m4v', 'video/quicktime', 'audio/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'mp3' => ['audio/mpeg'],
        'm4a' => ['audio/x-m4a', 'audio/mp4', 'video/mp4'],
        'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml'],
    ];

    /** @var string Kinds allowed when the admin has not chosen. */
    public const DEFAULT_KINDS = 'video,audio,pdf,image';

    /** @var int[] Default maximum size in bytes for each kind. Video allows 1 GB because typical files are 500 MB. */
    public const DEFAULT_MAXBYTES = [
        'video' => 1073741824,
        'audio' => 209715200,
        'pdf' => 104857600,
        'image' => 20971520,
    ];

    /** @var int How many bytes of the file are inspected. */
    protected const SNIFF_BYTES = 8192;

    /** @var int Bytes of an SVG file read at a time when looking for active content. */
    protected const SVG_SCAN_CHUNK = 1048576;

    /** @var int Bytes kept from one read to the next, longer than anything SVG_ACTIVE_CONTENT matches. */
    protected const SVG_SCAN_OVERLAP = 256;

    /** @var string What makes an SVG file refused: scripts, embedded pages and documents, event attributes, entity tricks. */
    protected const SVG_ACTIVE_CONTENT = '/<\s*(script|foreignobject|iframe|embed|object|applet|handler|listener)\b'
        . '|<!ENTITY|\bon[a-z]+\s*=|(java|vb)script\s*:|data\s*:\s*text\/html/i';

    /** @var int Largest ID3 tag skipped when sniffing an mp3. */
    protected const MAX_ID3_BYTES = 33554432;

    /**
     * The kinds the admin has enabled.
     *
     * @return string[]
     */
    public static function get_allowed_kinds(): array {
        $setting = get_config('contenttype_mediabank', 'allowedkinds');
        if ($setting === false || $setting === null) {
            $setting = self::DEFAULT_KINDS;
        }
        return array_values(array_intersect(array_keys(self::KINDS), explode(',', $setting)));
    }

    /**
     * Whether SVG files may be stored. On unless an admin turned it off. They are never served as a page: the preview is an
     * image, and the file itself is only sent as a download.
     *
     * @return bool
     */
    public static function is_svg_allowed(): bool {
        $setting = get_config('contenttype_mediabank', 'allowsvg');
        return $setting === false || $setting === null || $setting === '' ? true : (bool) $setting;
    }

    /**
     * Whether an SVG file has scripts or other active content in it.
     *
     * This is a second layer, not the protection. What keeps scripts from running is how the file is shown (as an image) and
     * sent (as a download with a sandbox policy). But a copy of the file in a course is served by Moodle itself, so a file with
     * obvious active content is refused at the door. A determined person can write the same thing in a way this does not
     * match, which is why it is not relied on.
     *
     * @param \stored_file $file The file.
     * @return bool True if anything on the list is found.
     */
    public static function svg_has_active_content(\stored_file $file): bool {
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            return true;
        }
        $carry = '';
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, self::SVG_SCAN_CHUNK);
                if ($chunk === false) {
                    return true;
                }
                // Read each stretch with the end of the one before, so a tag split between two reads is still seen.
                $window = $carry . $chunk;
                if (preg_match(self::SVG_ACTIVE_CONTENT, $window)) {
                    return true;
                }
                $carry = substr($window, -self::SVG_SCAN_OVERLAP);
            }
        } finally {
            fclose($handle);
        }
        return false;
    }

    /**
     * Extensions (without dot) that may be uploaded under the current settings.
     *
     * @return string[]
     */
    public static function get_allowed_extensions(): array {
        $extensions = [];
        foreach (self::get_allowed_kinds() as $kind) {
            $extensions = array_merge($extensions, self::KINDS[$kind]);
        }
        if (self::is_svg_allowed()) {
            $extensions[] = 'svg';
        }
        return $extensions;
    }

    /**
     * The kind an extension belongs to. SVG counts as an image.
     *
     * @param string $extension Lower-case extension without the dot.
     * @return string|null Null when the extension is not managed.
     */
    public static function get_kind(string $extension): ?string {
        if ($extension === 'svg') {
            return 'image';
        }
        foreach (self::KINDS as $kind => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $kind;
            }
        }
        return null;
    }

    /**
     * Maximum size in bytes for a kind.
     *
     * @param string $kind One of the KINDS keys.
     * @return int
     */
    public static function get_max_bytes(string $kind): int {
        $setting = get_config('contenttype_mediabank', 'maxbytes_' . $kind);
        if ($setting === false || $setting === null || $setting === '') {
            return self::DEFAULT_MAXBYTES[$kind];
        }
        return (int) $setting;
    }

    /**
     * Lower-case extension of a file name, without the dot.
     *
     * @param string $filename
     * @return string
     */
    public static function get_extension(string $filename): string {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Whether the start of the file looks like what its extension says.
     *
     * @param \stored_file $file The file to inspect.
     * @param string $extension Lower-case extension without the dot.
     * @return bool
     */
    public static function content_matches_extension(\stored_file $file, string $extension): bool {
        if (!isset(self::ACCEPTED_TYPES[$extension])) {
            return false;
        }
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            return false;
        }
        $head = fread($handle, self::SNIFF_BYTES);
        // An mp3 may start with a large ID3 tag (cover art), which hides the audio from the sniff. Look past it.
        // If the tag cannot be skipped cleanly, refuse the file rather than sniff the tag itself.
        if ($extension === 'mp3' && $head !== false && str_starts_with($head, 'ID3')) {
            $size = strlen($head) >= 10 ? self::get_id3_end($head) : null;
            if (
                $size === null || $size > self::MAX_ID3_BYTES || $size >= $file->get_filesize()
                    || fseek($handle, $size) !== 0
            ) {
                fclose($handle);
                return false;
            }
            $head = fread($handle, self::SNIFF_BYTES);
        }
        fclose($handle);
        if ($head === false || $head === '') {
            return false;
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
        return in_array($detected, self::ACCEPTED_TYPES[$extension], true);
    }

    /**
     * Where the audio starts in a file that begins with an ID3v2 tag.
     *
     * @param string $head At least the first 10 bytes of the file.
     * @return int|null Offset of the first byte after the tag (and its footer if flagged), or null if the header is invalid.
     */
    protected static function get_id3_end(string $head): ?int {
        // The size is four "syncsafe" bytes, seven bits each, so any byte with the top bit set is invalid.
        $sizebytes = array_map('ord', str_split(substr($head, 6, 4)));
        foreach ($sizebytes as $byte) {
            if ($byte & 0x80) {
                return null;
            }
        }
        $size = ($sizebytes[0] << 21) | ($sizebytes[1] << 14) | ($sizebytes[2] << 7) | $sizebytes[3];
        $footer = (ord($head[5]) & 0x10) ? 10 : 0;
        return 10 + $size + $footer;
    }

    /**
     * Check a file that is about to enter the media bank.
     *
     * On failure the reason is queued as an error notification, because core only shows its own error strings, and an
     * exception is thrown to stop the upload.
     *
     * @param \stored_file $file The incoming file.
     * @param string|null $currentextension For a replacement, the extension of the item being replaced. Core keeps the
     *      old file name when replacing, so a different extension would leave bytes under the wrong name.
     * @throws \moodle_exception If the file is refused.
     */
    public static function validate(\stored_file $file, ?string $currentextension = null): void {
        $extension = self::get_extension($file->get_filename());
        $a = (object) ['name' => s($file->get_filename())];

        if (!in_array($extension, self::get_allowed_extensions(), true)) {
            self::reject('errornotallowed', $a);
        }
        if ($currentextension !== null && $extension !== $currentextension) {
            $a->expected = s($currentextension);
            self::reject('errorextensionchange', $a);
        }
        if ($file->get_filesize() === 0) {
            self::reject('errorempty', $a);
        }
        $max = self::get_max_bytes(self::get_kind($extension));
        if ($file->get_filesize() > $max) {
            $a->max = display_size($max);
            self::reject('errortoolarge', $a);
        }
        if (!self::content_matches_extension($file, $extension)) {
            self::reject('errorcontentmismatch', $a);
        }
        if ($extension === 'svg' && self::svg_has_active_content($file)) {
            self::reject('errorsvgactive', $a);
        }
    }

    /**
     * Facts about a file worth keeping with the item.
     *
     * @param \stored_file $file The stored file.
     * @return array
     */
    public static function describe(\stored_file $file): array {
        $extension = self::get_extension($file->get_filename());
        $info = ['kind' => self::get_kind($extension)];
        if ($info['kind'] === 'image' && $extension !== 'svg') {
            $imageinfo = $file->get_imageinfo();
            if ($imageinfo) {
                $info['width'] = (int) $imageinfo['width'];
                $info['height'] = (int) $imageinfo['height'];
            }
        }
        return $info;
    }

    /**
     * Clear the Content Bank caches that depend on which extensions this type manages.
     *
     * Used as the update callback of the settings that change that list.
     */
    public static function purge_caches(): void {
        \cache::make('core', 'contentbank_enabled_extensions')->purge();
        \cache::make('core', 'contentbank_context_extensions')->purge();
    }

    /**
     * Queue the error for the user and stop.
     *
     * @param string $errorcode String identifier in this plugin's language file.
     * @param \stdClass $a Values for the string.
     * @throws \moodle_exception Always.
     */
    protected static function reject(string $errorcode, \stdClass $a): void {
        \core\notification::error(get_string($errorcode, 'contenttype_mediabank', $a));
        throw new \moodle_exception($errorcode, 'contenttype_mediabank', '', $a);
    }
}
