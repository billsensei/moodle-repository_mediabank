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

/**
 * Admin settings for the media bank content type.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use contenttype_mediabank\upload_rules;
use core_admin\local\settings\filesize;

if ($ADMIN->fulltree) {
    // Changing the list of extensions needs the Content Bank extension caches cleared.
    $purge = [upload_rules::class, 'purge_caches'];

    $kinds = [];
    foreach (array_keys(upload_rules::KINDS) as $kind) {
        $kinds[$kind] = get_string('kind_' . $kind, 'contenttype_mediabank');
    }
    $setting = new admin_setting_configmulticheckbox(
        'contenttype_mediabank/allowedkinds',
        get_string('allowedkinds', 'contenttype_mediabank'),
        get_string('allowedkinds_desc', 'contenttype_mediabank'),
        array_fill_keys(explode(',', upload_rules::DEFAULT_KINDS), 1),
        $kinds
    );
    $setting->set_updatedcallback($purge);
    $settings->add($setting);

    $setting = new admin_setting_configcheckbox(
        'contenttype_mediabank/allowsvg',
        get_string('allowsvg', 'contenttype_mediabank'),
        get_string('allowsvg_desc', 'contenttype_mediabank'),
        0
    );
    $setting->set_updatedcallback($purge);
    $settings->add($setting);

    foreach (upload_rules::DEFAULT_MAXBYTES as $kind => $default) {
        $settings->add(new filesize(
            'contenttype_mediabank/maxbytes_' . $kind,
            get_string('maxbytes_' . $kind, 'contenttype_mediabank'),
            get_string('maxbytes_desc', 'contenttype_mediabank'),
            $default
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'contenttype_mediabank/optimizevideo',
        get_string('optimizevideo', 'contenttype_mediabank'),
        get_string('optimizevideo_desc', 'contenttype_mediabank'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'contenttype_mediabank/embedinbackups',
        get_string('embedinbackups', 'contenttype_mediabank'),
        get_string('embedinbackups_desc', 'contenttype_mediabank'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'contenttype_mediabank/versionretentiondays',
        get_string('versionretentiondays', 'contenttype_mediabank'),
        get_string('versionretentiondays_desc', 'contenttype_mediabank'),
        contenttype_mediabank\versions::DEFAULT_RETENTION_DAYS,
        PARAM_INT
    ));
}
