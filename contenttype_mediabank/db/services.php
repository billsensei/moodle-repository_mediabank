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

/**
 * Web service functions of the media bank content type.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'contenttype_mediabank_get_items' => [
        'classname' => 'contenttype_mediabank\external\get_items',
        'description' => 'Lists the media bank items in a context that the user can see.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'moodle/contentbank:access, contenttype/mediabank:access',
    ],
    'contenttype_mediabank_get_item' => [
        'classname' => 'contenttype_mediabank\external\get_item',
        'description' => 'Gets one media bank item with the addresses of its file and captions.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'moodle/contentbank:access, contenttype/mediabank:access',
    ],
    'contenttype_mediabank_get_usage' => [
        'classname' => 'contenttype_mediabank\external\get_usage',
        'description' => 'Lists the courses that link to a media bank item, for people who can manage it.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'moodle/contentbank:access, contenttype/mediabank:access',
    ],
];
