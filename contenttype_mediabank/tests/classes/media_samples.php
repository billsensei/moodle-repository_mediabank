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

namespace contenttype_mediabank\tests;

/**
 * Minimal files with real magic bytes, for tests. They are not playable media.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_samples {
    /**
     * Bytes that a content sniff identifies as the given extension.
     *
     * @param string $extension Extension without the dot.
     * @return string
     */
    public static function get(string $extension): string {
        $pad = str_repeat("\0", 64);
        $ogg = "OggS\x00\x02" . str_repeat("\0", 8) . "\x01\0\0\0\0\0\0\0\0\0\0\0\x01\x1e";
        switch ($extension) {
            case 'mp4':
                return "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . $pad;
            case 'm4a':
                return "\x00\x00\x00\x18ftypM4A \x00\x00\x00\x00M4A mp42" . $pad;
            case 'webm':
                return "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04"
                    . "\x42\xF3\x81\x08\x42\x82\x84webm" . $pad;
            case 'mp3':
                return "ID3\x03\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00" . $pad;
            case 'ogg':
                return $ogg . "\x01vorbis" . $pad;
            case 'pdf':
                return "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
            case 'png':
                return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5E'
                    . 'rkJggg==');
            case 'jpg':
            case 'jpeg':
                return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01" . $pad;
            case 'gif':
                return "GIF89a\x01\x00\x01\x00\x00\x00\x00;";
            case 'webp':
                return "RIFF\x1A\x00\x00\x00WEBPVP8 " . $pad;
            case 'mp3-bigtag':
                // A 20 KB ID3 tag (like embedded cover art) before the first audio frame.
                $size = 20000;
                $syncsafe = chr(($size >> 21) & 0x7f) . chr(($size >> 14) & 0x7f) . chr(($size >> 7) & 0x7f) . chr($size & 0x7f);
                return "ID3\x03\x00\x00" . $syncsafe . str_repeat("\0", $size) . "\xFF\xFB\x90\x00" . $pad;
            case 'mp4-m4v':
                return "\x00\x00\x00\x18ftypM4V \x00\x00\x02\x00M4V iso2" . $pad;
            case 'mp4-quicktime':
                return "\x00\x00\x00\x18ftypqt  \x00\x00\x02\x00qt  iso2" . $pad;
            case 'svg':
                return '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
        }
        throw new \coding_exception("No sample for $extension");
    }
}
