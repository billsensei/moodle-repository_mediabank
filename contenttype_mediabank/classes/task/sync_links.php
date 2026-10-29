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

namespace contenttype_mediabank\task;

use contenttype_mediabank\usage;

/**
 * Scheduled task: make every link to a media bank item carry the item's current content.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_links extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('tasksynclinks', 'contenttype_mediabank');
    }

    #[\Override]
    public function execute() {
        mtrace('Refreshed ' . usage::sync_all_links() . ' media bank link(s) that were out of date.');
    }
}
