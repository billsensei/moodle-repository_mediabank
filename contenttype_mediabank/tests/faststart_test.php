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

use contenttype_mediabank\tests\mp4_builder;

/**
 * Tests for moving an MP4's index to the front.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(faststart::class)]
final class faststart_test extends \advanced_testcase {
    /**
     * A stream over some bytes.
     *
     * @param string $data The bytes.
     * @return resource
     */
    protected function stream(string $data) {
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $data);
        rewind($handle);
        return $handle;
    }

    /**
     * Optimize bytes and return the result.
     *
     * @param string $data The source file.
     * @return string The optimized file.
     */
    protected function optimize(string $data): string {
        $path = make_request_directory() . '/out.mp4';
        faststart::rewrite($this->stream($data), strlen($data), $path);
        return file_get_contents($path);
    }

    /**
     * Whether the bytes would be changed.
     *
     * @param string $data The file.
     * @return bool
     */
    protected function needs(string $data): bool {
        return faststart::needs_optimizing($this->stream($data), strlen($data));
    }

    /**
     * The index moves to the front, the chunk offsets are corrected, and nothing else changes.
     */
    public function test_index_moves_and_offsets_still_hit_the_data(): void {
        $source = mp4_builder::build();
        $this->assertTrue(mp4_builder::offsets_hit_data($source));
        $this->assertTrue($this->needs($source));

        $result = $this->optimize($source);

        $this->assertSame(strlen($source), strlen($result));
        $this->assertLessThan(strpos($result, 'mdat'), strpos($result, 'moov'));
        $this->assertTrue(mp4_builder::offsets_hit_data($result));
        $this->assertNotSame(mp4_builder::offsets($source), mp4_builder::offsets($result));
        $this->assertFalse($this->needs($result));
        // The data itself is byte for byte what it was.
        $this->assertSame(implode('', mp4_builder::CHUNKS), substr($result, strpos($result, 'mdat') + 4, 22));
    }

    /**
     * Control: if the offsets were not corrected, they would point at the wrong bytes.
     */
    public function test_uncorrected_offsets_would_miss_the_data(): void {
        $source = mp4_builder::build();
        $moved = substr($source, 0, 24) . substr($source, strpos($source, 'moov') - 4)
            . substr($source, 24, strpos($source, 'moov') - 4 - 24);
        $this->assertFalse(mp4_builder::offsets_hit_data($moved));
    }

    /**
     * Boxes between the header and the data stay in front of the index, and boxes after it stay after.
     */
    public function test_other_boxes_keep_their_places(): void {
        $free = mp4_builder::box('free', str_repeat("\0", 30));
        $source = mp4_builder::build(['before' => $free, 'after' => mp4_builder::box('free', 'tail')]);

        $result = $this->optimize($source);

        $this->assertTrue(mp4_builder::offsets_hit_data($result));
        $this->assertLessThan(strpos($result, 'moov'), strpos($result, 'free'));
        $this->assertSame('tail', substr($result, -4));
        $this->assertSame(strlen($source), strlen($result));
    }

    /**
     * A track with 64 bit offsets is corrected too.
     */
    public function test_64_bit_offsets(): void {
        $result = $this->optimize(mp4_builder::build(['wide' => true]));

        $this->assertStringContainsString('co64', $result);
        $this->assertTrue(mp4_builder::offsets_hit_data($result));
    }

    /**
     * Files that should be left alone say so.
     */
    public function test_files_that_are_not_changed(): void {
        $this->assertFalse($this->needs(mp4_builder::build(['moovfirst' => true])), 'already streamable');
        $this->assertFalse($this->needs(mp4_builder::build(['fragmented' => true])), 'fragmented');
        $this->assertFalse($this->needs('not an mp4 at all, just text that goes on for a while'), 'not an mp4');
        $this->assertFalse($this->needs(''), 'empty');
        $this->assertFalse($this->needs(substr(mp4_builder::build(), 0, 50)), 'cut short');
        $onlydata = mp4_builder::box('ftyp', 'isom' . pack('N', 0) . 'isom') . mp4_builder::box('mdat', 'xxxx');
        $this->assertFalse($this->needs($onlydata), 'no index');
        $compressed = str_replace('mvhd', 'cmov', mp4_builder::build());
        $this->assertFalse($this->needs($compressed), 'compressed index');
    }

    /**
     * Asking to rewrite a file that cannot be changed fails and leaves nothing behind.
     */
    public function test_rewrite_refuses_and_leaves_no_file(): void {
        $data = mp4_builder::build(['moovfirst' => true]);
        $path = make_request_directory() . '/out.mp4';

        try {
            faststart::rewrite($this->stream($data), strlen($data), $path);
            $this->fail('Expected an exception.');
        } catch (\RuntimeException $e) {
            $this->assertFileDoesNotExist($path);
        }
    }

    /**
     * A damaged offset table is refused, not guessed at.
     */
    public function test_damaged_offset_table_is_refused(): void {
        $data = mp4_builder::build();
        // Say the first table has far more entries than the box holds.
        $at = strpos($data, 'stco') + 8;
        $data = substr_replace($data, pack('N', 1000), $at, 4);
        $path = make_request_directory() . '/out.mp4';

        $this->expectException(\RuntimeException::class);
        faststart::rewrite($this->stream($data), strlen($data), $path);
    }

    /**
     * With ffmpeg available, a real file still decodes to the same pictures after the change.
     */
    public function test_real_file_decodes_the_same(): void {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            $this->markTestSkipped('ffmpeg is not installed.');
        }
        $dir = make_request_directory();
        $source = $dir . '/source.mp4';
        exec(escapeshellarg($ffmpeg) . ' -v error -f lavfi -i testsrc=duration=2:size=160x120:rate=10 -f lavfi '
            . '-i sine=duration=2 -c:v libx264 -c:a aac -shortest ' . escapeshellarg($source) . ' 2>&1', $output, $status);
        if ($status !== 0 || !is_file($source)) {
            $this->markTestSkipped('This ffmpeg cannot make a test video.');
        }
        $handle = fopen($source, 'rb');
        $this->assertTrue(faststart::needs_optimizing($handle, filesize($source)), 'ffmpeg writes the index last by default');

        $result = $dir . '/result.mp4';
        faststart::rewrite($handle, filesize($source), $result);
        fclose($handle);

        $hash = function (string $path) use ($ffmpeg): string {
            exec(escapeshellarg($ffmpeg) . ' -v error -i ' . escapeshellarg($path) . ' -f framemd5 - 2>&1', $lines, $code);
            $this->assertSame(0, $code, implode("\n", $lines));
            return implode("\n", array_filter($lines, fn($line) => $line !== '' && $line[0] !== '#'));
        };
        $this->assertSame($hash($source), $hash($result));
        $this->assertSame(filesize($source), filesize($result));
        $handle = fopen($result, 'rb');
        $this->assertFalse(faststart::needs_optimizing($handle, filesize($result)));
        fclose($handle);
    }
}
