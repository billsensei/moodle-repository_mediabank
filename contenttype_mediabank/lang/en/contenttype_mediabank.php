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
$string['mediabank:useeditor'] = 'Edit media bank content';
$string['pluginname_help'] = 'Content bank to upload videos, audio, PDFs and images once and use them in any course.';
$string['privacy:metadata:core_contentbank'] = 'Alt text and transcripts written by the person who created a media bank item are kept in that item\'s settings.';
$string['privacy:metadata:core_files'] = 'Captions and earlier versions of media bank items are kept as files, linked to the item and not to a user.';
$string['allowedkinds'] = 'Allowed file kinds';
$string['allowedkinds_desc'] = 'Kinds of file that can be uploaded to the media bank. Items already uploaded stay available.';
$string['allowsvg'] = 'Allow SVG images';
$string['allowsvg_desc'] = 'SVG files can contain scripts. If allowed they are stored but always downloaded, never shown inline.';
$string['embedinbackups'] = 'Include linked files in course backups';
$string['embedinbackups_desc'] = 'A course backup normally holds only a reference to a linked media bank file, so restoring it on a different site leaves the file out. If enabled, manual course backups also include the linked files, which are restored as ordinary copies where the link cannot be kept. This makes backups larger: a course that links three 500 MB videos adds about 1.5 GB. Backups made for import, course copy and automated backups are not changed.';
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
$string['errornosuchitem'] = 'There is no such media bank item that you can use.';
$string['errornosuchversion'] = 'Version {$a} of this item does not exist.';
$string['taskpurgeversions'] = 'Delete expired media bank versions';
$string['versionretentiondays'] = 'Keep replaced files for (days)';
$string['versionretentiondays_desc'] = 'When an item is replaced, the old file is kept for this many days so the replacement can be undone. 0 keeps nothing. Each kept file uses its full size in disk space until it expires: replacing a 500 MB video holds 500 MB for the whole period.';
$string['unsupportedpreview'] = 'No preview is available for this file type.';
$string['accessibility'] = 'Accessibility';
$string['alttext'] = 'Text alternative';
$string['alttext_help'] = 'Describe the image for people who cannot see it. Leave nothing out that the image is there to show. This text is kept with the bank item.';
$string['captions'] = 'Captions (WebVTT)';
$string['captions_help'] = 'A .vtt caption file for this video or audio. It is shown with the preview in the bank. A course that links to the file plays the bare file without captions.';
$string['detachlinks'] = 'Convert all {$a} link(s) to copies';
$string['detachwarning'] = 'Courses keep the file as it is now, but later replacements no longer reach them. This cannot be undone.';
$string['downloadversion'] = 'Download version {$a}';
$string['downloadversions'] = 'Earlier versions';
$string['errorcaptions'] = 'The caption file must be a WebVTT file: it starts with the line WEBVTT and is not larger than 1 MB.';
$string['keepcurrent'] = 'Keep the current file';
$string['linkshdr'] = 'Links from courses';
$string['pdfnote'] = 'Screen reader users need a PDF that is tagged. Check this file before you use it.';
$string['restoreversion'] = 'Restore an earlier version';
$string['restoreversion_help'] = 'Puts an earlier file back for every course that links to this item. The file it replaces is kept as a version, so this can be undone.';
$string['transcript'] = 'Transcript';
$string['transcript_help'] = 'The words spoken in the video or audio, as text. It is shown under the preview in the bank.';
$string['versionoption'] = 'Version {$a->number}: {$a->date} ({$a->size})';
$string['versions'] = 'Versions';
