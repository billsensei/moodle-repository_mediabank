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

/**
 * Admin tool for one media bank item: where it is used, convert its links to copies, list and restore versions.
 *
 * Usage: php local/mediabank/cli/item.php --id=12 [--usage] [--detach] [--versions] [--restore=N]
 *
 * @package    local_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['id' => 0, 'usage' => false, 'detach' => false, 'versions' => false, 'restore' => 0, 'help' => false],
    ['h' => 'help']
);

if ($unrecognised || $options['help'] || !$options['id']) {
    cli_writeln("Usage: php local/mediabank/cli/item.php --id=<content id> [--usage] [--detach] [--versions] [--restore=N]");
    cli_writeln("  --usage     list the courses that link to the item");
    cli_writeln("  --detach    convert every link to an independent copy (the item can then be deleted)");
    cli_writeln("  --versions  list kept versions of the item");
    cli_writeln("  --restore=N put version N back (the current file is kept as a new version)");
    exit($options['help'] ? 0 : 1);
}

try {
    $content = (new \core_contentbank\contentbank())->get_content_from_id((int) $options['id']);
} catch (\dml_missing_record_exception $e) {
    cli_error('There is no content bank item with id ' . (int) $options['id'] . '.');
}
if ($content->get_content_type() !== 'contenttype_mediabank') {
    cli_error('Content ' . $options['id'] . ' is not a media bank item.');
}
cli_writeln('Item ' . $content->get_id() . ': ' . $content->get_name());

if ($options['usage']) {
    $courses = \contenttype_mediabank\usage::get_courses($content);
    if (!$courses) {
        cli_writeln('Not linked from anywhere.');
    }
    foreach ($courses as $course) {
        $name = $course->id ? "course {$course->id} ({$course->shortname})" : 'outside courses';
        cli_writeln("  $name: {$course->links} link(s)");
    }
}

if ($options['detach']) {
    cli_writeln('Converted ' . \contenttype_mediabank\usage::detach_all($content) . ' link(s) to copies.');
}

if ($options['versions']) {
    $versions = \contenttype_mediabank\versions::get_versions($content);
    if (!$versions) {
        cli_writeln('No kept versions.');
    }
    foreach ($versions as $number => $file) {
        cli_writeln("  version $number: " . userdate($file->get_timecreated()) . ', ' . display_size($file->get_filesize()));
    }
}

if ($options['restore']) {
    \contenttype_mediabank\versions::restore($content, (int) $options['restore']);
    cli_writeln('Restored version ' . (int) $options['restore'] . '.');
}
