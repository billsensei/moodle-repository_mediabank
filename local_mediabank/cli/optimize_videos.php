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
 * Queue every MP4 video already in the media bank that is not yet ready for streaming.
 *
 * Usage: php local/mediabank/cli/optimize_videos.php [--dry-run]
 *
 * New uploads are queued by themselves. This is for videos uploaded before that was switched on. Cron does the work.
 *
 * @package    local_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['dry-run' => false, 'help' => false], ['h' => 'help']);

if ($unrecognised || $options['help']) {
    cli_writeln('Usage: php local/mediabank/cli/optimize_videos.php [--dry-run]');
    cli_writeln('  --dry-run   only count the videos that would be queued');
    exit($options['help'] ? 0 : 1);
}

if (!\contenttype_mediabank\video_optimizer::is_enabled()) {
    cli_error('"Prepare MP4 videos for streaming" is switched off in the Media bank settings.');
}

if ($options['dry-run']) {
    cli_writeln(count(\contenttype_mediabank\video_optimizer::find_unprepared()) . ' video(s) would be queued.');
    exit(0);
}

cli_writeln(\contenttype_mediabank\video_optimizer::queue_all() . ' video(s) queued. Cron prepares them.');
