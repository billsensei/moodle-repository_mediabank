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
 * Test helper: small MP4 files made by hand, with chunk offsets that point at known bytes, so a test can check that the
 * offsets still point at the same bytes after the file has been rearranged. They are not playable.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mp4_builder {
    /** @var string[] The three chunks of data. The first and third belong to the first track, the second to the second. */
    public const CHUNKS = ['AAAAAAAA', 'BBBBBBBBBB', 'CCCC'];

    /**
     * One box.
     *
     * @param string $type Four character type.
     * @param string $payload Content.
     * @return string
     */
    public static function box(string $type, string $payload): string {
        return pack('N', 8 + strlen($payload)) . $type . $payload;
    }

    /**
     * The index, with the given chunk offsets.
     *
     * @param int[] $offsets Absolute offset of each of the three chunks.
     * @param bool $secondwide Whether the second track uses 64 bit offsets (co64).
     * @return string
     */
    protected static function moov(array $offsets, bool $secondwide): string {
        $track = fn(string $table) => self::box('trak', self::box('mdia', self::box('minf', self::box('stbl', $table))));
        $first = self::box('stco', pack('N', 0) . pack('N', 2) . pack('N2', $offsets[0], $offsets[2]));
        $second = $secondwide
            ? self::box('co64', pack('N', 0) . pack('N', 1) . pack('J', $offsets[1]))
            : self::box('stco', pack('N', 0) . pack('N', 1) . pack('N', $offsets[1]));
        return self::box('moov', self::box('mvhd', str_repeat("\0", 100)) . $track($first) . $track($second));
    }

    /**
     * A whole file.
     *
     * @param array $options moovfirst: put the index before the data. wide: second track uses co64. before: boxes between the
     *      header and the data. after: boxes after everything else. fragmented: add a movie fragment.
     * @return string
     */
    public static function build(array $options = []): string {
        $ftyp = self::box('ftyp', 'isom' . pack('N', 512) . 'isomiso2');
        $before = $options['before'] ?? '';
        $after = $options['after'] ?? '';
        $wide = !empty($options['wide']);
        $mdat = self::box('mdat', implode('', self::CHUNKS));
        $tail = !empty($options['fragmented']) ? self::box('moof', str_repeat("\0", 16)) : '';
        $moovsize = strlen(self::moov([0, 0, 0], $wide));
        $datastart = strlen($ftyp) + strlen($before) + (!empty($options['moovfirst']) ? $moovsize : 0) + 8;
        $second = $datastart + strlen(self::CHUNKS[0]);
        $offsets = [$datastart, $second, $second + strlen(self::CHUNKS[1])];
        $moov = self::moov($offsets, $wide);
        return !empty($options['moovfirst'])
            ? $ftyp . $before . $moov . $mdat . $tail . $after
            : $ftyp . $before . $mdat . $moov . $tail . $after;
    }

    /**
     * The three chunks' offsets as stored in a file made by build(), in the order of CHUNKS.
     *
     * @param string $mp4 File content.
     * @return int[]
     */
    public static function offsets(string $mp4): array {
        $stco = strpos($mp4, 'stco');
        $first = unpack('N2', substr($mp4, $stco + 12, 8));
        $second = strpos($mp4, 'stco', $stco + 4);
        if ($second !== false) {
            $middle = unpack('N', substr($mp4, $second + 12, 4))[1];
        } else {
            $middle = unpack('J', substr($mp4, strpos($mp4, 'co64') + 12, 8))[1];
        }
        return [$first[1], $middle, $first[2]];
    }

    /**
     * Whether every chunk is found at the offset the index gives for it.
     *
     * @param string $mp4 File content.
     * @return bool
     */
    public static function offsets_hit_data(string $mp4): bool {
        foreach (self::offsets($mp4) as $i => $offset) {
            if (substr($mp4, $offset, strlen(self::CHUNKS[$i])) !== self::CHUNKS[$i]) {
                return false;
            }
        }
        return true;
    }
}
