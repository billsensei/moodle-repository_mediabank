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

namespace contenttype_mediabank\form;

use contenttype_mediabank\captions;
use contenttype_mediabank\content;
use contenttype_mediabank\upload_rules;
use contenttype_mediabank\usage;
use contenttype_mediabank\versions;

/**
 * Form to edit what is kept with a media bank item: text alternative, transcript, captions, restore, links.
 *
 * Core's edit.php checks that the user may edit the item before this form is built.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor extends \core_contentbank\form\edit_content {
    /** @var content The item being edited. */
    protected $content;

    /** @var string Kind of file: video, audio, pdf or image. */
    protected $kind = '';

    #[\Override]
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $record = $DB->get_record('contentbank_content', ['id' => $this->_customdata['id']], '*', MUST_EXIST);
        $this->content = new content($record);
        $file = $this->content->get_file();
        $this->kind = $file ? (string) upload_rules::get_kind(upload_rules::get_extension($file->get_filename())) : '';

        $mform->addElement('header', 'accessibilityhdr', get_string('accessibility', 'contenttype_mediabank'));
        if ($this->kind === 'image') {
            $mform->addElement('text', 'alt', get_string('alttext', 'contenttype_mediabank'), ['size' => 60, 'maxlength' => 255]);
            $mform->setType('alt', PARAM_TEXT);
            $mform->addRule('alt', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('alt', 'alttext', 'contenttype_mediabank');
        }
        if ($this->kind === 'video' || $this->kind === 'audio') {
            $mform->addElement(
                'textarea',
                'transcript',
                get_string('transcript', 'contenttype_mediabank'),
                ['rows' => 8, 'cols' => 60]
            );
            $mform->setType('transcript', PARAM_TEXT);
            $mform->addHelpButton('transcript', 'transcript', 'contenttype_mediabank');
            $mform->addElement('filemanager', 'captions', get_string('captions', 'contenttype_mediabank'), null, [
                'subdirs' => 0,
                'maxfiles' => 1,
                'maxbytes' => captions::MAXBYTES,
                'accepted_types' => ['.vtt'],
            ]);
            $mform->addHelpButton('captions', 'captions', 'contenttype_mediabank');
        }
        if ($this->kind === 'pdf') {
            $mform->addElement('static', 'pdfnote', '', get_string('pdfnote', 'contenttype_mediabank'));
        }

        $versions = versions::get_versions($this->content);
        if ($versions) {
            $mform->addElement('header', 'versionshdr', get_string('versions', 'contenttype_mediabank'));
            $options = [0 => get_string('keepcurrent', 'contenttype_mediabank')];
            foreach ($versions as $number => $version) {
                $options[$number] = get_string('versionoption', 'contenttype_mediabank', (object) [
                    'number' => $number,
                    'date' => userdate($version->get_timecreated()),
                    'size' => display_size($version->get_filesize()),
                ]);
            }
            $mform->addElement('select', 'restoreversion', get_string('restoreversion', 'contenttype_mediabank'), $options);
            $mform->addHelpButton('restoreversion', 'restoreversion', 'contenttype_mediabank');
            $links = [];
            foreach ($versions as $number => $version) {
                $url = \moodle_url::make_pluginfile_url(
                    $version->get_contextid(),
                    'contenttype_mediabank',
                    versions::FILEAREA,
                    $this->content->get_id(),
                    $version->get_filepath(),
                    $version->get_filename(),
                    true
                );
                $text = get_string('downloadversion', 'contenttype_mediabank', $number);
                $links[] = \html_writer::tag('li', \html_writer::link($url, $text));
            }
            $mform->addElement(
                'static',
                'versionlinks',
                get_string('downloadversions', 'contenttype_mediabank'),
                \html_writer::tag('ul', implode('', $links))
            );
        }

        $linkcount = usage::count_links($this->content);
        if ($linkcount) {
            $mform->addElement('header', 'linkshdr', get_string('linkshdr', 'contenttype_mediabank'));
            $mform->addElement('advcheckbox', 'detachlinks', '', get_string('detachlinks', 'contenttype_mediabank', $linkcount));
            $mform->addElement('static', 'detachwarning', '', get_string('detachwarning', 'contenttype_mediabank'));
        }

        $this->add_action_buttons();
        $this->set_data($this->get_initial_data());
    }

    /**
     * Values the form starts with.
     *
     * @return \stdClass
     */
    protected function get_initial_data(): \stdClass {
        $data = new \stdClass();
        $data->alt = $this->content->get_alt();
        $data->transcript = $this->content->get_transcript();
        if ($this->kind === 'video' || $this->kind === 'audio') {
            $draftid = file_get_submitted_draft_itemid('captions');
            file_prepare_draft_area(
                $draftid,
                $this->content->get_contextid(),
                'contenttype_mediabank',
                captions::FILEAREA,
                $this->content->get_id(),
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $data->captions = $draftid;
        }
        return $data;
    }

    /**
     * The caption file the user put in the form, if any.
     *
     * @param int $draftid Draft area id.
     * @return \stored_file|null
     */
    protected function get_draft_caption(int $draftid): ?\stored_file {
        global $USER;
        $files = get_file_storage()->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id', false);
        return $files ? reset($files) : null;
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($this->kind === 'image' && trim((string) ($data['alt'] ?? '')) === '') {
            $errors['alt'] = get_string('required');
        }
        if (!empty($data['captions'])) {
            $caption = $this->get_draft_caption((int) $data['captions']);
            if ($caption && !captions::is_valid($caption)) {
                $errors['captions'] = get_string('errorcaptions', 'contenttype_mediabank');
            }
        }
        return $errors;
    }

    /**
     * Save what the user changed.
     *
     * @param \stdClass $data Form data.
     * @return int Id of the item.
     */
    public function save_content(\stdClass $data): int {
        $content = $this->content;
        if (!empty($data->restoreversion)) {
            $content = versions::restore($content, (int) $data->restoreversion);
        }
        if (!empty($data->detachlinks)) {
            usage::detach_all($content);
        }
        $values = [];
        if ($this->kind === 'image') {
            $values['alt'] = trim($data->alt);
        }
        if ($this->kind === 'video' || $this->kind === 'audio') {
            $transcript = trim($data->transcript);
            $values['transcript'] = $transcript === '' ? null : $transcript;
            $caption = $this->get_draft_caption((int) $data->captions);
            if ($caption) {
                captions::save($content, $caption);
            } else {
                captions::delete($content);
            }
        }
        $content->merge_metadata($values);
        $content->update_content();
        return $content->get_id();
    }
}
