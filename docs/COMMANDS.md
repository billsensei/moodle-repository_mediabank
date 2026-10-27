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
