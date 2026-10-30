# Media bank: Development guide

How to set up, change, test and release the two plugins. [CONTRIBUTING.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CONTRIBUTING.md)
says what a pull request needs. [ARCHITECTURE.md](ARCHITECTURE.md) says how the code is built and why. This file is the practical
part: commands, procedures, and the traps that cost time. [COMMANDS.md](COMMANDS.md) is a dated record of the commands actually run,
with their results.

In the commands below, `<moodle>` is the folder with Moodle's `config.php`, and `<root>` is where Moodle's code is: `<moodle>` on
Moodle 5.0 and `<moodle>/public` on 5.1 and later.

## 1. Set up

You need PHP 8.3 or 8.4 (the versions CI uses), MariaDB or PostgreSQL, Git, and a checkout of Moodle 5.0 to 5.3 with its PHPUnit and
Behat environments set up as described in [Moodle's developer documentation](https://moodledev.io/general/development/tools/phpunit).

1. Put the plugins in the Moodle tree. A symbolic link is fine for tests:

       ln -s <repo>/contenttype_mediabank <root>/contentbank/contenttype/mediabank
       ln -s <repo>/local_mediabank       <root>/local/mediabank

2. Install them: `php <moodle>/admin/cli/upgrade.php --non-interactive`.
3. Initialise the test environments: `php <root>/admin/tool/phpunit/cli/init.php`, and for browser tests
   `php <root>/admin/tool/behat/cli/init.php`.

**Run the initialisation again whenever you change a `version.php`.** The test sites are tied to the plugin versions, and PHPUnit
stops with "environment was initialised for different version".

**To test on several Moodle versions,** keep one checkout of each (5.0, 5.1, 5.2, 5.3) with its own database prefix, and link the
same plugin folders into each. The project is tested on all four, and CI does the same.

## 2. Everyday commands

From `<moodle>`. If PHPUnit complains about a missing locale, `export LOCPATH=~/.local/locale` (or install the locale it names).

    # whole suites
    vendor/bin/phpunit --testsuite contenttype_mediabank_testsuite --fail-on-warning
    vendor/bin/phpunit --testsuite local_mediabank_testsuite

    # one file, or one test
    vendor/bin/phpunit <root>/contentbank/contenttype/mediabank/tests/faststart_test.php
    vendor/bin/phpunit <root>/contentbank/contenttype/mediabank/tests/versions_test.php --filter test_restore

    # syntax
    for f in $(git ls-files '*.php'); do php -l $f | grep -v '^No syntax'; done

`--fail-on-warning` is what CI uses. The two "PHPUnit Deprecations" in the summary come from the doc-comment `@covers` style that
Moodle's coding standard still asks for in some tests; they are not failures.

**Browser (Behat) tests** need a browser driver and a web server for the test site:

    chromedriver --port=9515 &
    php -S 127.0.0.1:8010 -t <root> &         # the address in config.php's behat_wwwroot
    php <root>/admin/tool/behat/cli/init.php  # prints the path of behat.yml
    vendor/bin/behat --config <behat.yml> --tags=@contenttype_mediabank

There are 7 scenarios. Stop the driver and the server afterwards.

## 3. Checking style the way CI does

CI fails on any `phpcs` warning, and **`phpcs` run on this repository on its own is not enough.** The Moodle sniffs that know which
plugin a file belongs to (language string order, where a test file must live, namespaces) only work when the plugin is inside a
Moodle tree, and a symbolic link does not count. CI once failed on seven warnings that a local run had passed.

Do this for each Moodle version you care about (at least 5.0 and one later one, because the standard differs a little):

    cp -r contenttype_mediabank <root>/contentbank/contenttype/mediabank   # a copy, not a link
    cp -r local_mediabank       <root>/local/mediabank
    cd <moodle-plugin-ci>
    P="--runtime-set installed_paths $PWD/vendor/moodlehq/moodle-cs,$PWD/vendor/phpcsstandards/phpcsextra,$PWD/vendor/phpcsstandards/phpcsutils"
    vendor/bin/phpcs $P --standard=moodle <root>/contentbank/contenttype/mediabank <root>/local/mediabank
    # then delete the two copies, and any symbolic links you made, from the tree

Expected: no output. `phpcbf` instead of `phpcs` fixes what it can. Typical findings in this project: a line over 132 characters,
a language string out of alphabetical order, a missing PHPDoc comment.

**PHPDoc check** (Moodle's `local_moodlecheck`): copy `local_moodlecheck` from `moodle-plugin-ci/vendor/moodlehq/` into
`<root>/local/`, run `php <moodle>/admin/cli/purge_caches.php`, then from `<root>`:

    php local/moodlecheck/cli/moodlecheck.php -p=<comma-separated plugin files> -f=text

Expected: it prints the file names and no `Line` lines. Then remove the copy and purge the caches again. If it says "Invalid
component specified in renderer request" and exits 0, it did not run: purge the caches.

**Other checks** CI runs, through `moodle-plugin-ci`: `phplint`, `mustache`, `grunt`, `validate`, `savepoints`. They are quick and
need an installed Moodle. [COMMANDS.md](COMMANDS.md) shows how they were run here.

## 4. How the tests are written

The helpers are in `contenttype_mediabank/tests/classes/`: `link_helper` (create an item, create a link the way the file picker
does), `media_samples` (a few bytes that pass a content sniff for each kind of file) and `mp4_builder` (a small hand-made MP4 whose
chunk offsets point at known bytes).

Habits that matter here:

- **Make the test able to fail.** Break the code on purpose and watch it go red, or run the same case against the previous version
  of the code. Several tests in this project passed for the wrong reason. The commonest cause: reading a link's hash through
  `stored_file::get_contenthash()` makes Moodle refresh the link, so the assertion saw the repaired value. Read the `files` table
  instead.
- **Fix a bug with a test that fails without the fix.** Every security fix in this project has one.
- **One database reset costs about ten seconds.** A PHPUnit data provider resets the database for every row. For many cases of pure
  logic, extend `\basic_testcase` (no database, as `svg_validator_test` does) or loop inside one test.
- **Do not depend on the stored defaults of a test database.** A test database that was initialised by an older version keeps the
  old default of a setting, as a live site does. Set what the test needs with `set_config()`, and call
  `upload_rules::purge_caches()` after changing a setting that changes which extensions are managed.
- **Output from tasks.** A test that runs a task prints its progress; declare it with `$this->expectOutputRegex()` or PHPUnit
  marks the test risky.
- **Things that cannot be forced.** A regular expression engine failure cannot be forced through patterns already compiled earlier in
  the same PHPUnit process; test the helper directly (see `svg_validator_test`).
- **A test that needs a program may skip itself.** The real-video test needs `ffmpeg`; CI installs it, and locally it skips if it is
  missing. Check that a skip is not hiding a failure: the test counts in the CI log should equal your local ones.

Moodle's own checks run against the plugin too: `privacy/tests/privacy/provider_test.php` (privacy compliance) and
`lib/tests/event/plugin_checks_test.php` (every event). Run both after changing the privacy provider or adding an event.

## 5. Common changes

**A language string.** Add it to `lang/en/<component>.php` **in alphabetical order**. The code checker fails otherwise. Name the
string by what it says, and use `{$a->name}` placeholders, never string concatenation.

**A setting.** Add it in `settings.php` with a language string and its description (`<name>` and `<name>_desc`), read it with
`get_config('contenttype_mediabank', '<name>')`, and write the default in one place. A setting that changes which extensions are
managed needs `set_updatedcallback` calling `upload_rules::purge_caches`. Document it in the Administrator guide's settings table.
Remember that a site that already has the plugin keeps the old stored value when a default changes.

**An upload rule.** `upload_rules::validate` is the single gate for upload and replace. Add the check there, and a language string
for its message. A rule that reads the file must have a limit on how much it reads or builds.

**An event.** Add a class under `classes/event/` with `init()`, `get_name()`, `get_description()` and, if it has an object,
`get_objectid_mapping()`; add the string; add it to `tests/events_test.php`; run Moodle's `plugin_checks_test`.

**A scheduled or ad hoc task.** Add the class under `classes/task/` with `get_name()` and a language string, register a scheduled
one in `db/tasks.php`, and test it by calling the method it runs. For an ad hoc task, queue it with
`\core\task\manager::queue_adhoc_task($task, true)` so the same one is not queued twice.

**A capability.** Add it to `db/access.php` with its archetypes and a language string, and describe it in the Administrator guide's
permissions table.

**A web service function.** Add the class under `classes/external/`, register it in `db/services.php`, and test it in
`tests/external_test.php`. Reuse `external\item::require_access` so every function checks access the same way.

**Anything that changes behaviour a person can see.** Update the matching guide in `docs/`. If a screen changed, retake its
screenshot: create a throwaway teacher and course on a dev site, drive a headless browser through chromedriver, and delete the
data afterwards.

## 6. Versions, changelog and compatibility

- `version.php` in each plugin holds `$plugin->version` (`YYYYMMDDnn`), `$plugin->release`, `$plugin->maturity` (Beta), `$plugin->requires`
  (5.0) and `$plugin->supported` ([500, 503]). Raise the version for any change that ships; add an `upgrade.php` step if the
  database changes (so far none has, because the plugin has no tables).
- `local_mediabank` lists the content type version it needs under `$plugin->dependencies`. Raise that only when the tools plugin
  starts to need something newer.
- Each plugin has a `CHANGES.md` with the detail, and the root `CHANGELOG.md` lists releases. Update both.
- Moodle 5.1 moved code into `public/`. Use `$CFG->dirroot` and Moodle's APIs, never hard-coded paths. Run the tests on 5.0 and a
  later version before saying a change works on all.

## 7. Building and checking the release files

    python3 scripts/build_release.py --out dist
    php scripts/validate_plugin_zip.php --moodle=<moodle> --zip=dist/<zip> --component=contenttype_mediabank

The build writes `contenttype_mediabank_<version>.zip`, `local_mediabank_<version>.zip` and `mediabank-<release>-bundle.zip` (both
plugin zips and the guides, with their images). It is **reproducible**: file times come from the commit time, so the same commit
gives the same bytes. A later commit gives zips that differ in time only; compare the checksum of the files inside (`unzip -v`) to
see whether the contents changed. The validator runs Moodle's own installer check, so it is what Moodle will say when an
administrator installs the zip. Run it against each Moodle version. The only warning expected is the Beta one.

The plugin folder inside each zip is called `mediabank`, and the bundle zip itself cannot be installed: it holds the two zips.

## 8. The CI workflow

`.github/workflows/ci.yml` runs on every push and pull request: 32 jobs, from the plugin (2) times PHP (8.3, 8.4) times Moodle
(`MOODLE_500_STABLE` to `MOODLE_503_STABLE`) times database (PostgreSQL, MariaDB). Each job installs Moodle with
`moodle-plugin-ci`, then runs PHP lint, PHP mess detector, the code checker, the PHPDoc checker, validate, upgrade savepoints,
mustache lint, grunt, PHPUnit with `--fail-on-warning`, and Behat. `local_mediabank` jobs install the content type as an extra
plugin. An extra step installs `ffmpeg` so the real-video test runs.

To run it on a change, push the branch; there is nothing to start. Read a failure with `gh run view <id> --log-failed` once the
whole run has finished (the logs are not available while it runs).

## 9. Releasing

1. Everything on a branch, with the version, changelogs and guides updated.
2. Run the checks above on 5.0, 5.1, 5.2 and 5.3, and build and validate the zips from the commit.
3. Push the branch. **Merge only when all 32 jobs are green.**
4. Fast-forward `main`, tag `v<content type release>` (annotated), push both.
5. Create the GitHub release as a **pre-release** while the plugins are Beta, and attach the three zips (from the commit that
   passed CI). Say in the notes anything an administrator must decide.
6. Delete the branch.

Never change a release's tag after publishing. A documentation-only change after a release can be pushed to `main`; the bundle zip on
the release may be replaced with a rebuilt one (the plugin zips stay as they are). A change to the plugins' code gets a new version
number and a new release, because an installed copy only upgrades when the number goes up. v0.11.0 is the one exception: its zips were
replaced on 2026-10-30 with the hardening, without a new number, and the changelog says so. Do not repeat that.

## 10. Security review habits

This project takes the plugin's file handling seriously, because it reads files from users and serves them.

- Anything that reads an uploaded file has a limit on how much it reads or builds, and refuses on anything it does not understand.
- A check that **refuses** on a match must also refuse when the check itself fails. `preg_match()` returns `false` when the regular
  expression engine gives up, and `if (false)` reads as "no match"; use the `found()` helper in `svg_validator`, or the same pattern.
- Prefer a list of what is allowed to a list of what is forbidden. Four rounds of review found gaps in a list of forbidden CSS before
  the check became "every `url()` must be exactly one of two forms".
- Check what the parser sees, not what the raw bytes say: decode character references and encodings before looking for a word.
- Access to a file is checked by the plugin (`contenttype::can_serve`), because core only checks that the user is logged in. A refusal
  uses `send_file_not_found()`, because returning `false` makes core serve the file as a download.
- A backup or an upload is not trusted: check hashes and paths, and resolve the real path before using it.
- Never put a personal email address or a secret in the repository. Security reports go through
  [SECURITY.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/SECURITY.md).

## 11. When something goes wrong

| What you see | Usual cause |
|---|---|
| PHPUnit: "environment was initialised for different version" | A `version.php` changed. Run `phpunit/cli/init.php` again |
| PHPUnit: "Required locale ... is not installed" | Set `LOCPATH` to where the locale is, or install it |
| `phpcs` passes locally but CI fails on language order or test location | You ran it outside a Moodle tree. See section 3 |
| A test passes but the assertion cannot fail | See section 4: break the code and watch it fail |
| A test is slow | It uses a data provider or resets the database; see section 4 |
| PHPDoc check prints "Invalid component specified in renderer request" | Purge the caches after copying `local_moodlecheck` in |
| The validator says the zip name or type is wrong | The zip was re-made by another tool. Use the build script |
| An upload that works locally fails on a server with no message | A PHP, web server or Moodle size limit; see "Large files" in the Administrator guide |
