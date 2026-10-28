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
