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

namespace contenttype_mediabank;

use contenttype_mediabank\tests\link_helper;
use contenttype_mediabank\tests\media_samples;

/**
 * Tests for taking the content type out of a site.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(contenttype::class)]
final class uninstall_test extends \advanced_testcase {
    /**
     * A File resource with no file in it, in a new course.
     *
     * @return \context_module
     */
    protected function resource_context(): \context_module {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        return $context;
    }

    /**
     * Uninstalling deletes every item but leaves each course with its file as an ordinary copy, whether the link was saved
     * through a form or made the way a restore makes it.
     */
    public function test_uninstall_deletes_items_and_keeps_course_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $item = link_helper::create_item('doc.pdf', media_samples::get('pdf'));
        $second = link_helper::create_item('pic.png', media_samples::get('png'));
        $hash = $item->get_file()->get_contenthash();
        $size = (int) $item->get_file()->get_filesize();
        $secondhash = $second->get_file()->get_contenthash();
        $formlink = link_helper::create_link_by_saving_form($item, $this->resource_context());
        $restorelink = link_helper::create_link($item, $this->resource_context());
        $imagelink = link_helper::create_link($second, $this->resource_context());
        // Links to one item share a sync record, so only the link to the second item is still without content.
        $this->assertSame(sha1(''), $DB->get_field('files', 'contenthash', ['id' => $imagelink->get_id()]));

        $plugin = \core_plugin_manager::instance()->get_plugin_info('contenttype_mediabank');
        $this->assertNotEmpty($plugin->get_uninstall_extra_warning(), 'Moodle warns that items will be deleted');
        $plugin->uninstall_cleanup();

        $this->assertSame(0, $DB->count_records('contentbank_content', ['contenttype' => 'contenttype_mediabank']));
        $this->assertSame(0, $DB->count_records_select(
            'files',
            "component IN ('contentbank', 'contenttype_mediabank') AND filename <> '.'"
        ));
        foreach ([[$formlink, $hash], [$restorelink, $hash], [$imagelink, $secondhash]] as [$link, $expected]) {
            $row = $DB->get_record('files', ['id' => $link->get_id()], 'id, contenthash, filesize, referencefileid');
            $this->assertNotFalse($row, 'A course lost its file');
            $this->assertEmpty($row->referencefileid, 'The course file is still a link');
            $this->assertSame($expected, $row->contenthash);
            $this->assertGreaterThan(0, (int) $row->filesize);
        }
        $this->assertSame($size, (int) $DB->get_field('files', 'filesize', ['id' => $formlink->get_id()]));
    }
}
