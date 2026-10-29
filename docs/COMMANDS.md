# Commands used in Phase 1

Run from `/home/bill/test2/moodle-plugin-ci`. All were run on 2026-10-27 with PHP 8.4.26.

## Syntax check
    for f in $(find /home/bill/test2/mediabank -name '*.php'); do php -l $f; done
Expected: `No syntax errors detected in <file>` for each file. Any other output is a parse error with a line number.

## Moodle coding standard (phpcs)
    P="--runtime-set installed_paths $PWD/vendor/moodlehq/moodle-cs,$PWD/vendor/phpcsstandards/phpcsextra,$PWD/vendor/phpcsstandards/phpcsutils"
    vendor/bin/phpcs $P --standard=moodle /home/bill/test2/mediabank
Expected when clean: no output, exit code 0.
Common error: `Cannot redeclare class MoodleHQ\...\AbstractTestCaseSniff`. phpcs is configured with an `installed_paths`
entry pointing at another copy (`/home/bill/test/moodle-plugin-ci`). The `--runtime-set` above overrides it for one run.
Auto-fix: replace `phpcs` with `phpcbf`.

## Full moodle-plugin-ci checks (NOT yet run)
`moodle-plugin-ci install` fetches Moodle and a database, so it needs your approval (download + size). Planned:
phplint, phpcs, phpdoc, validate, savepoints, mustache, grunt, phpunit, behat, using the matrix in `.github/workflows/ci.yml`.

## Large-file limits to check on a site (Phase 0 finding)
- Moodle: Site administration > Security > Site security settings > "User quota" (`userquota`, default 100 MB), or give uploaders `moodle/user:ignoreuserquota`.
- PHP: `upload_max_filesize`, `post_max_size`, `max_execution_time` above 500 MB / upload time.
- nginx: `client_max_body_size`.

## Installing into moodle53 and testing (2026-10-27)
Start the user-space MariaDB (the `db.sh` script has the aarch64 library path; this machine is x86_64):

    R=$HOME/.local/root; D=$HOME/.local/var/mariadb; export LD_LIBRARY_PATH="$R/usr/lib/x86_64-linux-gnu"
    setsid nohup "$R/usr/sbin/mariadbd" --no-defaults --basedir="$R/usr" --datadir="$D/data" --socket="$D/mysqld.sock" \
      --port=3307 --bind-address=127.0.0.1 --lc-messages-dir="$R/usr/share/mariadb" --character-set-server=utf8mb4 \
      --collation-server=utf8mb4_unicode_ci > $D/err.log 2>&1 &
Expected: `err.log` ends with `ready for connections`.

Symlink the plugins (plugin code only, no core changes) and upgrade:

    cd /home/bill/test2/moodle53
    ln -s /home/bill/test2/mediabank/contenttype_mediabank public/contentbank/contenttype/mediabank
    ln -s /home/bill/test2/mediabank/local_mediabank public/local/mediabank
    php admin/cli/upgrade.php --non-interactive
Expected: `-->contenttype_mediabank` / `++ Success` for both plugins, then `... completed successfully`.

PHPUnit (5.3 path is `public/admin/...`; a fresh shell needs the locale):

    export LOCPATH=~/.local/locale
    php public/admin/tool/phpunit/cli/init.php
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/contenttype_test.php
Expected: `OK, but there were issues! Tests: 4, Assertions: 20, PHPUnit Deprecations: 1`
(the deprecation is the `@covers` doc-comment style, which the Moodle coding standard still requires).
Common errors: `Required locale 'en_AU.UTF-8' is not installed` (set LOCPATH); `Could not open input file: admin/tool/...` (use `public/`).

## Behat acceptance test (2026-10-27)
Feature: `contenttype_mediabank/tests/behat/upload_and_link.feature` (tag `@contenttype_mediabank`). Fixtures: `tests/fixtures/sample.png`, `sample.pdf`.
Fixture paths in the feature are relative to `$CFG->dirroot`, which is `moodle53/public`.

    export LOCPATH=~/.local/locale
    cd /home/bill/test2/moodle53
    ~/.local/root/usr/bin/chromedriver --port=9515 &                      # headless Chromium driver
    PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8010 -t public &            # Behat site (config.php behat_wwwroot)
    php public/admin/tool/behat/cli/init.php                               # ~1 min; re-run after adding plugins/features
    vendor/bin/behat --config /home/bill/test/m53behat/behatrun/behat/behat.yml --tags=@contenttype_mediabank
Expected: `4 scenarios (4 passed)`, `76 steps (76 passed)`.
Negative control done: changing `matches value "1"` to `"0"` makes the link scenario fail (exit 1).
Stop the helpers by PID (`pgrep -x chromedriver`, `pgrep -x php`), not `pkill -f`.

What the scenarios prove: a teacher can upload png and pdf to the course content bank; the file picker offers the item with
"Link to the file" selected by default; a copy can be chosen instead; both save into a File resource.
What they do NOT prove: that the saved file is an alias, or that replacing the bank item updates it (covered by the PHPUnit
test `test_link_follows_replace`), and nothing about system-level items or video.

## Phase 2 test run (2026-10-27)
After changing plugin code that adds settings, run `php admin/cli/upgrade.php --non-interactive` (installs setting defaults;
bump `version.php`). PHPUnit needs `php public/admin/tool/phpunit/cli/init.php` after a version bump or new tests.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit --testsuite contenttype_mediabank_testsuite
Expected: `OK, but there were issues! Tests: 44, Assertions: 109, PHPUnit Deprecations: 2` (the deprecations are the
`@covers`/`@dataProvider` doc-comment style).
Behat: expected `5 scenarios (5 passed), 111 steps (111 passed)` using the commands in the Behat section above.
Common errors: `Class "...\media_samples" not found` (test helpers live in `tests/classes` and use namespace
`<component>\tests`); `expectExceptionMessage('errorX')` fails because exceptions carry the translated text (assert `->errorcode`).
Note: setting a value with `set_config()` in a test does not run the admin-setting callback, so call `upload_rules::purge_caches()`.

## Phase 3 (2026-10-27)
Admin CLI for one item (run from the Moodle root of a site with the plugins installed):

    php local/mediabank/cli/item.php --id=3 --usage --versions
Expected: `Item 3: doc.pdf`, then `Not linked from anywhere.` and `No kept versions.` (or the course and version lists).
`--detach` converts every link to a copy; `--restore=N` puts version N back; an unknown version prints `Version N of this item does not exist.`
Dev-only quirk: the plugins are symlinked into moodle53, so PHP resolves `__DIR__` outside Moodle and the script cannot find
`config.php`. Run a copy with the config path edited, or install the plugin by copying it, as a real site would.
The scheduled task `\contenttype_mediabank\task\purge_versions` runs daily at 03:17; run it by hand with
`php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\purge_versions'`.
After adding a scheduled task or setting: bump `version.php` and run `php admin/cli/upgrade.php --non-interactive`.

## Phase 4 (2026-10-27)
After adding a capability or template: bump `version.php`, `php admin/cli/upgrade.php --non-interactive`, then `php public/admin/tool/phpunit/cli/init.php`.
PHPUnit: `vendor/bin/phpunit --testsuite contenttype_mediabank_testsuite` -> `OK, but there were issues! Tests: 53, Assertions: 143, PHPUnit Deprecations: 2` (the deprecations are the `@covers`-style notes noted above).
Behat: `vendor/bin/behat --config /home/bill/test/m53behat/behatrun/behat/behat.yml --tags=@contenttype_mediabank` -> `7 scenarios (7 passed)`, `151 steps (151 passed)`, about 6 minutes (one clean run, 2026-10-27).
Run one scenario with `--name="<scenario title>"` (a `file:line` path does not work with this config).
Problem hit: `This is not a behat test site!` after an init was killed half-way. `util.php --drop` fails the same check. The fix was to drop the `bht_` tables
by hand (test-only prefix; the dev site uses `mdl_`) and empty `behatrun/` including the hidden `.htaccess`, then run `init.php` again.
Run `init.php` in the background (it takes about 3 minutes with the theme build); a foreground timeout kills it half-way.
phpcs: no output, exit 0. Mustache lint (`moodle-plugin-ci mustache -m <moodle> <plugin>`) needs the plugin inside the Moodle tree without a symlink; run on a temporary copy: `Mustache rendered html succesfully`.
HTTP check of the new URLs on the dev site (login with temporary users, deleted afterwards): captions as manager -> 200, `text/vtt`, `inline`, `nosniff`;
earlier version as manager -> 200, `attachment`; both as a user without the right, and a wrong caption file name -> 404.

## Phase 5: privacy provider (2026-10-29)
After changing the provider or its strings: bump `version.php`, `php admin/cli/upgrade.php --non-interactive`, `php public/admin/tool/phpunit/cli/init.php`.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/privacy_provider_test.php
Expected: `OK (8 tests, 26 assertions)`.
Whole suite: `OK, but there were issues! Tests: 61, Assertions: 169, PHPUnit Deprecations: 2`.
Core's compliance test checks every plugin's metadata strings and interfaces: `vendor/bin/phpunit public/privacy/tests/privacy/provider_test.php` -> `OK (1525 tests, 7591 assertions)`.
Common error: `Test file "public/privacy/tests/provider_test.php" not found` (the file is in `public/privacy/tests/privacy/`).

### moodle-plugin-ci checks on the dev site (symlinked plugins)
moodle-plugin-ci cannot resolve a symlinked plugin. Swap the symlinks in `public/local/mediabank` and
`public/contentbank/contenttype/mediabank` for copies, run the checks from the Moodle root, then restore the symlinks:

    PATH=<dir with an npx shim that runs moodle53/node_modules/.bin/$1>:$PATH
    cd /home/bill/test2/moodle53
    /home/bill/test2/moodle-plugin-ci/bin/moodle-plugin-ci <phplint|validate|savepoints|mustache|grunt> public/local/mediabank
Expected: `No syntax error found`, validate exit 0, `Mustache rendered html succesfully`, grunt `Done.`.
phpdoc: `Invalid component specified in renderer request` (and exit 0, which looks like a pass) means Moodle's component cache does not
know `local_moodlecheck`. Copy `moodle-plugin-ci/vendor/moodlehq/moodle-local_moodlecheck` to `public/local/moodlecheck`, run
`php admin/cli/purge_caches.php`, then from `public/` run
`php local/moodlecheck/cli/moodlecheck.php -p=<comma-separated files> -f=text`. Expected: no `Line` lines. Remove the copy and purge caches again.
validate also needs `-m /home/bill/test2/moodle53` when run on a copy outside Moodle.

## Phase 5: backup and restore of links (2026-10-29)
After adding the backup/restore classes or the setting: bump both `version.php` files (the local plugin depends on the content type's version),
`php admin/cli/upgrade.php --non-interactive` (expected: `New setting: contenttype_mediabank/embedinbackups`), `php public/admin/tool/phpunit/cli/init.php`.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit public/local/mediabank/tests/backup_restore_test.php
Expected: `OK (6 tests, 21 assertions)`, about 1.5 minutes (each test makes and restores a real backup).
How "another site" is simulated: the test changes `$CFG->siteidentifier` for the restore, which makes core treat the backup as foreign.
What core does without the setting (measured 2026-10-29): same site, bank file present -> link restored; same site, bank file gone -> file dropped with a
warning; another site -> the course is restored with no file at all. The first test records the last case.
Not covered: links in block and section-summary files; a restore into a site that has no Content Bank repository instance.

## Phase 5: web services and file serving (2026-10-29)
Functions are registered from `db/services.php` on upgrade: bump `version.php`, `php admin/cli/upgrade.php --non-interactive`, then `php public/admin/tool/phpunit/cli/init.php`.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/external_test.php   # OK (8 tests, 41 assertions)
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/serving_test.php    # OK (3 tests, 12 assertions)
Whole suite: `OK, but there were issues! Tests: 72, Assertions: 222, PHPUnit Deprecations: 2`.
Common error: `Class "core_external\tests\externallib_advanced_testcase" not found` (the base class is `\core_external\tests\externallib_testcase`).

Using the functions: add the three functions to an external service (Site administration > Server > Web services > External services), enable
the REST protocol, give the user `webservice/rest:use`, create a token. Then
`GET <site>/webservice/rest/server.php?wstoken=TOKEN&wsfunction=contenttype_mediabank_get_items&moodlewsrestformat=json&contextid=N`.
`fileurl` and `captionsurl` in the result are `webservice/pluginfile.php` addresses; add `?token=TOKEN` to download. Without a token core answers with a JSON
error (HTTP 200) and does not send the file.
A course tool lists the course context and the system context separately. The caller needs `moodle/contentbank:access` and
`contenttype/mediabank:access` in the context asked for; students and teachers of other courses get `errornosuchitem`.

HTTP check done on the dev site with temporary users, a temporary service and tokens (all removed afterwards, `enablewebservices` and
`webserviceprotocols` put back to `0` and empty): teacher get_items/get_item/get_usage worked, student got `errornosuchitem`, file and captions
downloaded with the teacher token (200, `application/pdf` inline, `text/vtt`), 404 with the student token.

File serving fix, checked over HTTP with temporary users (`/pluginfile.php/<ctx>/contentbank/public/<id>/<name>`): a public item gives 200 to the
creator, another teacher and a manager; an unlisted item gives 200 to the creator and a manager and 404 to another teacher. Before the fix another teacher got 200
for the unlisted item.

## Phase 5: events (2026-10-29)
After adding events or the hook: bump `version.php`, `php admin/cli/upgrade.php --non-interactive`, `php admin/cli/purge_caches.php` (hook callbacks are cached), `php public/admin/tool/phpunit/cli/init.php`.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/events_test.php   # OK (7 tests, 35 assertions)
    vendor/bin/phpunit public/lib/tests/event/plugin_checks_test.php                    # OK (414 tests): core checks the structure of every event class
Whole suite: `OK, but there were issues! Tests: 79, Assertions: 257, PHPUnit Deprecations: 2`.
Which events fire (measured 2026-10-29, `$this->redirectEvents()`): upload -> `contentbank_content_created` + `_updated`; replace -> `_updated` (twice);
rename, visibility, alt text, caption change, move -> `_updated`; view -> `_viewed`; delete -> `_deleted`. Ours: `item_linked`, `links_converted`, `version_restored`.
Why the link event uses a hook: `file_storage::create_file_from_reference()` inserts the file row itself and never calls `create_file()`, so neither
the file hook nor any event fires when a link is picked. Saving the form copies the draft link with `create_file_from_storedfile()`, which keeps the
reference and does call `create_file()`; that is the one `item_linked` reports. The test helper `link_helper::create_link_by_saving_form()` does it
the way a teacher does; `link_helper::create_link()` makes links the way a restore does, so it fires nothing.
The purge task's counts go to the task log: `php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\purge_versions'`
prints `Deleted N expired media bank version(s).` and `Deleted N orphaned caption file(s).`

## Phase 5: erasure policy and link refresh (2026-10-29)
Policy: erasing a person keeps courses' files as ordinary copies (Bill, 2026-10-29). After upgrading to 0.9.0 run the task once, so links made earlier are fixed now and not at 03:47:

    php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'
Expected: `Refreshed N media bank link(s) that were out of date.` then `Scheduled task complete: Refresh the content of media bank links`. A second run says 0.

    export LOCPATH=~/.local/locale; cd /home/bill/test2/moodle53
    vendor/bin/phpunit public/contentbank/contenttype/mediabank/tests/erasure_test.php   # OK (9 tests, 58 assertions)
Whole suite: `OK, but there were issues! Tests: 88, Assertions: 315, PHPUnit Deprecations: 2`.
Testing trap found on the way: reading a link's hash or size through the file object (`$link->get_contenthash()`, `get_filesize()`) syncs it, which is
what opening a link does. A test that does so cannot tell a synced link from an empty one. Read `files.contenthash` with `$DB->get_field()` instead.
How a link looks right after it is made: `file_storage::create_file_from_reference()` stores the empty-file hash (`sha1('')`, `da39a3ee...`) unless another link
to the same file exists, and `files_reference.lastsync` is NULL. `usage::sync_links_to_file()` calls core's `update_references_to_storedfile()`, which updates every link to the file.

## Compatibility matrix, Moodle 5.0 to 5.3 (run 2026-10-29, PHP 8.4.26, MariaDB 11.8)
Trees: 5.0.11 `moodle/` (no `public/` folder), 5.1.8 `moodle51/`, 5.2.4 `moodle52/`, 5.3 `moodle53/`. Plugins were symlinked in for the run (5.0 into `contentbank/contenttype/mediabank` and `local/mediabank`;
5.1+ into `public/...`) and the links removed afterwards from 5.0 to 5.2. Nothing was upgraded in the dev databases: PHPUnit and Behat use their own `phpu_` and `bht_` tables.

    cd <tree>; php [public/]admin/tool/phpunit/cli/init.php        # a fresh install of both plugins; about 3 minutes
    vendor/bin/phpunit --testsuite contenttype_mediabank_testsuite
    vendor/bin/phpunit [public/]local/mediabank/tests/backup_restore_test.php
    vendor/bin/phpunit [public/]privacy/tests/privacy/provider_test.php      # core's check of every plugin's privacy provider
    vendor/bin/phpunit [public/]lib/tests/event/plugin_checks_test.php       # core's check of every event class
    PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:<behat port> -t <tree>[/public] &   # 5.0 8004, 5.1 8006, 5.2 8008, 5.3 8010
    php [public/]admin/tool/behat/cli/init.php; vendor/bin/behat --config <behat dataroot>/behatrun/behat/behat.yml --tags=@contenttype_mediabank

| Check | 5.0.11 | 5.1.8 | 5.2.4 | 5.3 |
|---|---|---|---|---|
| Plugins install | pass | pass | pass | pass |
| contenttype suite | 88 tests, 315 assertions | same | same | same |
| Backup and restore tests | 7 tests, 27 assertions | same | same | same |
| Core privacy compliance | 1529 tests | 1522 | 1521 | 1525 |
| Core event structure check | 416 | 414 | 413 | 414 |
| Behat `@contenttype_mediabank` | 7 scenarios, 151 steps | same | same | same |
Behat took about 5 minutes per run plus about 3 for the install. Core's totals differ between versions because each version ships a different set of plugins.
Not covered: PHP 8.3 (not installed here), PostgreSQL (only MariaDB here), the GitHub Actions workflow itself, and the linters (phpcs, phpdoc, mustache, grunt) on each version: they were run on 5.3 only, and 5.2 has no `node_modules`.

## Release packaging (2026-10-29)
Build the installable zips and the client bundle (all three files are written to the folder you give; the default is `./dist`):

    python3 scripts/build_release.py --out /home/bill/test2/dist
    contenttype_mediabank_<version>.zip   local_mediabank_<version>.zip   mediabank-<release>-bundle.zip
Each plugin zip has one top folder, `mediabank`. Hidden files are left out. (A stray `phpunit.xml` used to be left out too; it was removed from the repository on 2026-10-29. Its bootstrap path only worked inside a Moodle tree, and the tests run without it.) The bundle holds both zips, the three guides and both change logs; it is **not** installable itself.
The zips are reproducible: the same commit gives the same bytes.

Check a zip the way Moodle's installer does, without installing it. Run it against a site where the plugin is not installed (otherwise the validator also reports that the target folder exists):

    php scripts/validate_plugin_zip.php --moodle=/home/bill/test2/moodle52 --zip=/home/bill/test2/dist/contenttype_mediabank_<version>.zip --component=contenttype_mediabank
Expected: `[OK]` lines for plugin name, version, required Moodle version, component and release; one `[Warning] Declared maturity level MATURITY_BETA`; then `PASS: contenttype_mediabank against Moodle 5.2.4+ ...`. Checked on 5.0, 5.1 and 5.2 for both zips.

Prove a zip is complete (done 2026-10-29): unzip both into a Moodle tree as real folders (not symlinks), run `php public/admin/tool/phpunit/cli/init.php`, then the contenttype suite and `public/local/mediabank/tests/backup_restore_test.php` from the extracted copies, then delete the folders. Result on 5.2.4: 88 tests, 315 assertions and 7 tests, 27 assertions, the same as from the source tree.
Rollback: `contenttype_mediabank/tests/uninstall_test.php` calls the content type's `uninstall_cleanup()` (what the uninstall runs) and checks that every item is gone and each course kept its file as an ordinary copy, for a form-saved link and a link nobody had opened (`OK (1 test, 17 assertions)`). With link conversion switched off the test fails.
