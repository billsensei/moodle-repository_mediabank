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
 * Strings for the media bank content type.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['description'] = 'Video, audio, PDF and image files';
$string['pluginname'] = 'Media bank';
$string['mediabank:access'] = 'Access media bank content';
$string['mediabank:upload'] = 'Upload new media bank content';
$string['pluginname_help'] = 'Content bank to upload videos, audio, PDFs and images once and use them in any course.';
$string['privacy:metadata'] = 'The media bank content type does not store any personal data.';
$string['allowedkinds'] = 'Allowed file kinds';
$string['allowedkinds_desc'] = 'Kinds of file that can be uploaded to the media bank. Items already uploaded stay available.';
$string['allowsvg'] = 'Allow SVG images';
$string['allowsvg_desc'] = 'SVG files can contain scripts. If allowed they are stored but always downloaded, never shown inline.';
$string['errorcontentmismatch'] = 'The file {$a->name} was not accepted because its content is not the kind of file its name says.';
$string['errorempty'] = 'The file {$a->name} was not accepted because it is empty.';
$string['errorextensionchange'] = 'The file {$a->name} cannot replace this item. A replacement must be a .{$a->expected} file, so that existing links keep working.';
$string['errornotallowed'] = 'The file {$a->name} was not accepted because this kind of file is not allowed in the media bank.';
$string['errortoolarge'] = 'The file {$a->name} was not accepted because it is larger than the limit of {$a->max}.';
$string['kind_audio'] = 'Audio (mp3, m4a, ogg)';
$string['kind_image'] = 'Images (png, jpg, gif, webp)';
$string['kind_pdf'] = 'PDF documents';
$string['kind_video'] = 'Video (mp4, webm)';
$string['maxbytes_audio'] = 'Maximum audio size';
$string['maxbytes_desc'] = 'Largest file accepted for this kind. The web server and PHP limits, and the site user quota, must also allow it.';
$string['maxbytes_image'] = 'Maximum image size';
$string['maxbytes_pdf'] = 'Maximum PDF size';
$string['maxbytes_video'] = 'Maximum video size';
$string['usagecourse'] = '{$a->name}: {$a->links} link(s)';
$string['usagedeletenote'] = 'This item cannot be deleted while courses link to it. Replace the file to update every course, or convert the links to copies first.';
$string['usageheading'] = 'Where this item is used';
$string['usagehidden'] = 'Courses you cannot see: {$a} link(s)';
$string['usagenone'] = 'No course links to this item.';
$string['usageother'] = 'Outside courses: {$a} link(s)';
$string['unsupportedpreview'] = 'No preview is available for this file type.';
