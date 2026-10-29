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
 * Check a plugin zip the way Moodle's installer does, without installing it.
 *
 * Usage: php validate_plugin_zip.php --moodle=/path/to/moodle --zip=/path/to/plugin.zip --component=contenttype_mediabank
 *
 * It follows core_plugin_manager::install_plugins(): unzip into a request folder, then run core\update\validator
 * with the plugin type and the site's Moodle version. Run it against a site where the plugin is not installed,
 * or the validator also reports that the target folder already exists.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This is a developer tool that loads the config.php of whichever Moodle it is pointed at, so it cannot use a fixed path.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('CLI_SCRIPT', true);

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)=(.+)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    }
}
if (empty($options['moodle']) || empty($options['zip']) || empty($options['component'])) {
    fwrite(STDERR, "Usage: php validate_plugin_zip.php --moodle=DIR --zip=FILE --component=TYPE_NAME\n");
    exit(2);
}

require(rtrim($options['moodle'], '/') . '/config.php');

[$plugintype, $pluginname] = core_component::normalize_component($options['component']);
$manager = core_plugin_manager::instance();
$tmp = make_request_directory();
$contents = $manager->unzip_plugin_file($options['zip'], $tmp, $pluginname);
if (empty($contents)) {
    echo "FAIL: Moodle could not unzip {$options['zip']} as plugin '$pluginname'\n";
    exit(1);
}

$validator = \core\update\validator::instance($tmp, $contents);
$validator->assert_plugin_type($plugintype);
$validator->assert_moodle_version($CFG->version);
$ok = $validator->execute();
foreach ($validator->get_messages() as $message) {
    $info = $validator->message_code_info($message->msgcode, $message->addinfo);
    printf(
        "  [%s] %s %s\n",
        $validator->message_level_name($message->level),
        $validator->message_code_name($message->msgcode),
        $info ?: (is_string($message->addinfo) ? $message->addinfo : json_encode($message->addinfo))
    );
}
echo ($ok ? 'PASS' : 'FAIL') . ": {$options['component']} against Moodle " . $CFG->release . "\n";
exit($ok ? 0 : 1);
