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

namespace contenttype_mediabank\task;

use contenttype_mediabank\versions;

/**
 * Scheduled task: delete media bank versions older than the retention time.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_versions extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('taskpurgeversions', 'contenttype_mediabank');
    }

    #[\Override]
    public function execute() {
        $count = versions::purge_expired();
        mtrace("Deleted $count expired media bank version(s).");
    }
}
