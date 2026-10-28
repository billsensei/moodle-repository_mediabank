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

namespace contenttype_mediabank\external;

use contenttype_mediabank\usage;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\util;

/**
 * Say which courses link to a media bank item.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_usage extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Item id'),
        ]);
    }

    /**
     * List the courses that link to an item. Courses the caller may not see are counted, not named.
     *
     * @param int $id The item id.
     * @return array
     */
    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);
        [$content, $type] = item::require_item($params['id']);
        if (!$type->can_manage($content)) {
            throw new \core\exception\moodle_exception('errornosuchitem', 'contenttype_mediabank');
        }
        $total = 0;
        $hidden = 0;
        $other = 0;
        $courses = [];
        foreach (usage::get_courses($content) as $course) {
            $total += $course->links;
            if (!$course->id) {
                $other += $course->links;
            } else if (\core_course_category::can_view_course_info($course)) {
                $courses[] = [
                    'id' => (int) $course->id,
                    'fullname' => util::format_string($course->fullname, \context_course::instance($course->id)),
                    'links' => $course->links,
                ];
            } else {
                $hidden += $course->links;
            }
        }
        return ['links' => $total, 'courses' => $courses, 'hiddenlinks' => $hidden, 'otherlinks' => $other];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'links' => new external_value(PARAM_INT, 'Links to the item, in total'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id'),
                'fullname' => new external_value(PARAM_RAW, 'Course name, formatted for display'),
                'links' => new external_value(PARAM_INT, 'Links to the item in this course'),
            ]), 'Courses the caller can see'),
            'hiddenlinks' => new external_value(PARAM_INT, 'Links in courses the caller cannot see'),
            'otherlinks' => new external_value(PARAM_INT, 'Links outside courses'),
        ]);
    }
}
