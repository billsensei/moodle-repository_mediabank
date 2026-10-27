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
