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
 * Moves the index of an MP4 file to the front so that playback can start before the whole file has arrived.
 *
 * Most encoders write the index (the "moov" box) after the picture and sound data (the "mdat" box). A browser must then
 * download the whole file, or make a second request for its end, before it can play. This moves the index to the front and
 * corrects the data positions stored in it. Nothing is decoded or re-encoded, so the picture and sound are untouched and the
 * file stays the same size.
 *
 * Only plain MP4 files are changed. A file that is already streamable, is fragmented, has a compressed index or does not
 * look like an MP4 is left alone and reported as not optimizable.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faststart {
    /** @var int Largest index (moov box) that will be loaded into memory, in bytes. */
    public const MAX_MOOV_BYTES = 134217728;

    /** @var int Most top-level boxes read before giving up. A real file has a handful. */
    protected const MAX_TOP_BOXES = 1000;

    /** @var int Bytes copied at a time. */
    protected const CHUNK = 1048576;

    /** @var string[] Boxes that contain other boxes on the way to the chunk offset tables. */
    protected const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl'];

    /**
     * Whether the file is an MP4 whose index comes after its data, and can be moved.
     *
     * @param resource $handle Seekable, readable stream of the file.
     * @param int $size The file size in bytes.
     * @return bool
     */
    public static function needs_optimizing($handle, int $size): bool {
        return self::plan($handle, $size) !== null;
    }

    /**
     * Write an optimized copy of the file.
     *
     * @param resource $handle Seekable, readable stream of the source file.
     * @param int $size The source size in bytes.
     * @param string $destination Path to write. It is replaced if it exists.
     * @throws \RuntimeException If the file cannot be optimized or the result does not check out. The destination is removed.
     */
    public static function rewrite($handle, int $size, string $destination): void {
        $plan = self::plan($handle, $size);
        if ($plan === null) {
            throw new \RuntimeException('The file does not need, or cannot take, the optimization.');
        }
        $out = fopen($destination, 'wb');
        if (!$out) {
            throw new \RuntimeException('Cannot write the optimized file.');
        }
        try {
            $moov = self::read($handle, $plan['moovstart'], $plan['moovsize']);
            $moov = self::shift_offsets($moov, $plan['headersize'], $plan['insert'], $plan['moovstart'], $plan['moovsize']);
            self::copy($handle, $out, 0, $plan['insert']);
            self::put($out, $moov);
            self::copy($handle, $out, $plan['insert'], $plan['moovstart'] - $plan['insert']);
            $moovend = $plan['moovstart'] + $plan['moovsize'];
            self::copy($handle, $out, $moovend, $size - $moovend);
            fclose($out);
            $out = null;
            self::check_result($destination, $size, $plan['moovsize']);
        } catch (\Throwable $e) {
            if ($out) {
                fclose($out);
            }
            @unlink($destination);
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Work out where the pieces are, or null if the file should not be changed.
     *
     * @param resource $handle Source stream.
     * @param int $size Source size.
     * @return array|null Keys: insert (where the index will go), moovstart, moovsize, headersize.
     */
    protected static function plan($handle, int $size): ?array {
        try {
            $boxes = self::top_boxes($handle, $size);
        } catch (\RuntimeException $e) {
            return null;
        }
        $firstdata = null;
        $moov = null;
        $types = [];
        foreach ($boxes as $box) {
            $types[] = $box['type'];
            if ($box['type'] === 'mdat' && $firstdata === null) {
                $firstdata = $box;
            }
            if ($box['type'] === 'moov') {
                if ($moov !== null) {
                    return null;
                }
                $moov = $box;
            }
        }
        // It must start like an MP4, have both parts, have the index after the data, and not be fragmented.
        if (
            ($types[0] ?? '') !== 'ftyp' || $moov === null || $firstdata === null
            || $moov['start'] < $firstdata['start'] || in_array('moof', $types, true)
            || $moov['size'] > self::MAX_MOOV_BYTES
        ) {
            return null;
        }
        // A compressed or fragment-based index is not something to patch.
        $body = self::read($handle, $moov['start'], $moov['size']);
        if (str_contains($body, 'cmov') || str_contains($body, 'mvex')) {
            return null;
        }
        return [
            'insert' => $firstdata['start'],
            'moovstart' => $moov['start'],
            'moovsize' => $moov['size'],
            'headersize' => $moov['header'],
        ];
    }

    /**
     * The boxes at the top level of the file.
     *
     * @param resource $handle Stream.
     * @param int $size File size.
     * @return array[] Each with type, start, size (including the header) and header (header length).
     * @throws \RuntimeException If the structure is broken.
     */
    protected static function top_boxes($handle, int $size): array {
        $boxes = [];
        $position = 0;
        while ($position < $size) {
            if (count($boxes) >= self::MAX_TOP_BOXES || $size - $position < 8) {
                throw new \RuntimeException('Not a well formed MP4.');
            }
            $head = self::read($handle, $position, 8);
            $length = unpack('N', $head)[1];
            $type = substr($head, 4, 4);
            $header = 8;
            if ($length === 1) {
                $length = unpack('J', self::read($handle, $position + 8, 8))[1];
                $header = 16;
            } else if ($length === 0) {
                $length = $size - $position;
            }
            if ($length < $header || $position + $length > $size) {
                throw new \RuntimeException('Not a well formed MP4.');
            }
            $boxes[] = ['type' => $type, 'start' => $position, 'size' => $length, 'header' => $header];
            $position += $length;
        }
        return $boxes;
    }

    /**
     * Correct every chunk offset in the index for the move.
     *
     * Data that sat between the new place of the index and its old place moves forward by the index's size. Data before the
     * new place, or after the old place, stays where it is.
     *
     * @param string $moov The whole moov box.
     * @param int $header Length of the moov box header.
     * @param int $insert Where the index will be written.
     * @param int $oldstart Where the index was.
     * @param int $shift Size of the index.
     * @return string The corrected box, the same length.
     * @throws \RuntimeException If an offset cannot be corrected.
     */
    protected static function shift_offsets(string $moov, int $header, int $insert, int $oldstart, int $shift): string {
        $found = 0;
        self::walk($moov, $header, strlen($moov), $insert, $oldstart, $shift, $found);
        if ($found === 0) {
            throw new \RuntimeException('The index has no chunk offsets.');
        }
        return $moov;
    }

    /**
     * Walk the boxes between two positions, correcting offset tables in place.
     *
     * @param string $data The moov box, changed in place.
     * @param int $from First position to read.
     * @param int $to End position.
     * @param int $insert Where the index will be written.
     * @param int $oldstart Where the index was.
     * @param int $shift Size of the index.
     * @param int $found Number of offset tables corrected.
     * @throws \RuntimeException If a box is malformed.
     */
    protected static function walk(string &$data, int $from, int $to, int $insert, int $oldstart, int $shift, int &$found): void {
        $position = $from;
        while ($position + 8 <= $to) {
            $length = unpack('N', substr($data, $position, 4))[1];
            $type = substr($data, $position + 4, 4);
            $header = 8;
            if ($length === 1) {
                $length = unpack('J', substr($data, $position + 8, 8))[1];
                $header = 16;
            } else if ($length === 0) {
                $length = $to - $position;
            }
            if ($length < $header || $position + $length > $to) {
                throw new \RuntimeException('Malformed box inside the index.');
            }
            $end = $position + $length;
            if (in_array($type, self::CONTAINERS, true)) {
                self::walk($data, $position + $header, $end, $insert, $oldstart, $shift, $found);
            } else if ($type === 'stco' || $type === 'co64') {
                $wide = $type === 'co64';
                $width = $wide ? 8 : 4;
                $table = $position + $header + 4;
                $count = unpack('N', substr($data, $table, 4))[1];
                if ($table + 4 + $count * $width > $end) {
                    throw new \RuntimeException('Malformed chunk offset table.');
                }
                if ($count > 0) {
                    $values = unpack($wide ? 'J*' : 'N*', substr($data, $table + 4, $count * $width));
                    foreach ($values as $i => $value) {
                        if ($value >= $insert && $value < $oldstart) {
                            $value += $shift;
                            if (!$wide && $value > 0xFFFFFFFF) {
                                throw new \RuntimeException('A chunk offset no longer fits in 32 bits.');
                            }
                            $values[$i] = $value;
                        }
                    }
                    $packed = pack(($wide ? 'J' : 'N') . $count, ...array_values($values));
                    $data = substr_replace($data, $packed, $table + 4, $count * $width);
                }
                $found++;
            }
            $position = $end;
        }
    }

    /**
     * Check the written file: the same size, with the index now ahead of the data and the same length as before.
     *
     * @param string $path The written file.
     * @param int $size Expected size.
     * @param int $moovsize Expected index size.
     * @throws \RuntimeException If it does not check out.
     */
    protected static function check_result(string $path, int $size, int $moovsize): void {
        clearstatcache(true, $path);
        if (filesize($path) !== $size) {
            throw new \RuntimeException('The optimized file has a different size.');
        }
        $handle = fopen($path, 'rb');
        try {
            $boxes = self::top_boxes($handle, $size);
        } finally {
            fclose($handle);
        }
        $order = array_column($boxes, 'type');
        $moov = array_search('moov', $order, true);
        $mdat = array_search('mdat', $order, true);
        if ($moov === false || $mdat === false || $moov > $mdat || $boxes[$moov]['size'] !== $moovsize) {
            throw new \RuntimeException('The optimized file is not laid out as expected.');
        }
    }

    /**
     * Read exactly the given bytes.
     *
     * @param resource $handle Stream.
     * @param int $offset Position.
     * @param int $length Bytes to read.
     * @return string
     * @throws \RuntimeException If the file ends early.
     */
    protected static function read($handle, int $offset, int $length): string {
        if (fseek($handle, $offset) !== 0) {
            throw new \RuntimeException('Cannot seek in the file.');
        }
        $data = '';
        while (strlen($data) < $length) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                throw new \RuntimeException('The file ended early.');
            }
            $data .= $part;
        }
        return $data;
    }

    /**
     * Write all of a string.
     *
     * @param resource $out Stream.
     * @param string $data Bytes.
     * @throws \RuntimeException If writing fails, for example because the disk is full.
     */
    protected static function put($out, string $data): void {
        $length = strlen($data);
        for ($written = 0; $written < $length;) {
            $count = fwrite($out, substr($data, $written, self::CHUNK));
            if ($count === false || $count === 0) {
                throw new \RuntimeException('Cannot write the optimized file.');
            }
            $written += $count;
        }
    }

    /**
     * Copy a range from one stream to another.
     *
     * @param resource $from Source.
     * @param resource $to Destination, written at its current position.
     * @param int $offset Start in the source.
     * @param int $length Bytes to copy.
     */
    protected static function copy($from, $to, int $offset, int $length): void {
        for ($done = 0; $done < $length;) {
            $take = min(self::CHUNK, $length - $done);
            self::put($to, self::read($from, $offset + $done, $take));
            $done += $take;
        }
    }
}
