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

use contenttype_mediabank\video_optimizer;

/**
 * Ad hoc task: make one media bank video ready for streaming.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class optimize_video extends \core\task\adhoc_task {
    /**
     * Name shown on the task pages.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskoptimizevideo', 'contenttype_mediabank');
    }

    #[\Override]
    public function execute() {
        $contentid = (int) ($this->get_custom_data()->contentid ?? 0);
        mtrace('Media bank item ' . $contentid . ': ' . video_optimizer::optimize($contentid));
    }
}
