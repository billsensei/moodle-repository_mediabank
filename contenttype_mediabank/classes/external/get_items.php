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

use contenttype_mediabank\content;
use contenttype_mediabank\upload_rules;
use core_contentbank\contentbank;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List the media bank items in a context that the caller may see.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_items extends external_api {
    /** @var int Most items one call returns. */
    public const MAX_PER_PAGE = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'System, category or course context to list'),
            'kind' => new external_value(PARAM_ALPHA, 'Only this kind: video, audio, pdf, image. Empty for all', VALUE_DEFAULT, ''),
            'query' => new external_value(PARAM_TEXT, 'Only items whose name contains this text', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Page number, starting at 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page, at most ' . self::MAX_PER_PAGE, VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * List items, sorted by name.
     *
     * A course tool that wants everything usable in a course asks for the course context and for the system context.
     *
     * @param int $contextid The context.
     * @param string $kind Kind filter or empty.
     * @param string $query Name filter or empty.
     * @param int $page Page number from 0.
     * @param int $perpage Items per page.
     * @return array
     */
    public static function execute(int $contextid, string $kind = '', string $query = '', int $page = 0, int $perpage = 50): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'kind' => $kind, 'query' => $query, 'page' => $page, 'perpage' => $perpage]
        );
        if ($params['kind'] !== '' && !array_key_exists($params['kind'], upload_rules::KINDS)) {
            throw new \invalid_parameter_exception('kind must be one of ' . implode(', ', array_keys(upload_rules::KINDS)));
        }
        if ($params['page'] < 0 || $params['perpage'] < 1 || $params['perpage'] > self::MAX_PER_PAGE) {
            throw new \invalid_parameter_exception('page must be 0 or more and perpage between 1 and ' . self::MAX_PER_PAGE);
        }
        [$context, $type] = item::require_access($params['contextid']);

        $contents = (new contentbank())->search_contents($params['query'] ?: null, $context->id, ['mediabank']);
        if ($params['kind'] !== '') {
            $contents = array_filter($contents, fn(content $content) => item::get_kind($content) === $params['kind']);
        }
        $total = count($contents);
        $pageitems = array_slice(array_values($contents), $params['page'] * $params['perpage'], $params['perpage']);

        return [
            'total' => $total,
            'items' => array_map(fn(content $content) => item::export($content, $type), $pageitems),
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Items matching, over all pages'),
            'items' => new external_multiple_structure(item::structure()),
            'warnings' => new \core_external\external_warnings(),
        ]);
    }
}
