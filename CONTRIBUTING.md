# Contributing

Thank you for helping with the Media bank. This project is two Moodle plugins in one repository:

- `contenttype_mediabank/`: the content type (the main plugin)
- `local_mediabank/`: backup and restore of links, and command-line tools. It needs the content type.

Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

Both are **Beta**, for Moodle 5.0 to 5.3. By contributing you agree that your work is released under the
[GNU GPL v3 or later](LICENSE), the same as the rest of the project.

## Reporting a problem

Open a [GitHub issue](https://github.com/billsensei/moodle-repository_mediabank/issues/new/choose). The bug report form asks for the following:

- the Moodle version (shown at the bottom of **Site administration → Notifications**), the PHP version and the database
- the plugin versions (**Site administration → Plugins → Plugins overview**)
- what you did, what you expected, and what happened, including the exact message Moodle showed

**Security problems.** Do not put details of a vulnerability in a public issue. Follow [SECURITY.md](SECURITY.md), which reports it
privately through GitHub.

## Before you change anything

Small fixes and documentation corrections can go straight to a pull request. For a new feature or a change in behaviour,
open an issue first, so the idea can be agreed before you spend time on it.

Keep a change to one purpose. A pull request that fixes a bug should not also rename things.

## Setting up

You need a Moodle 5.0 to 5.3 site with its PHPUnit and Behat environments (see Moodle's developer documentation), PHP 8.3 or 8.4,
and either MariaDB or PostgreSQL. Put the plugins in the Moodle tree. Moodle 5.1 and later use a `public/` folder:

| | Moodle 5.0 | Moodle 5.1 and later |
|---|---|---|
| Content type | `contentbank/contenttype/mediabank` | `public/contentbank/contenttype/mediabank` |
| Tools | `local/mediabank` | `public/local/mediabank` |

A symbolic link from there to this repository works for running tests. Run `php admin/cli/upgrade.php` after changing a
`version.php`, and `php public/admin/tool/phpunit/cli/init.php` (and the Behat `init.php`) again, because the test sites are
tied to the plugin versions.

[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) explains how the plugins are built and why. [docs/COMMANDS.md](docs/COMMANDS.md) is a
running record of the exact commands used to test, build and release, with their results. It is the best place to look for how
something was run.

## What every change needs

1. **Tests.** A bug fix needs a test that fails without the fix. A new feature needs tests for what it does and for what it
   must refuse. Check that a new test can fail: break the code on purpose and see it go red. Several tests in this project
   once passed for the wrong reason, for example because reading a link's hash through Moodle's file object silently
   refreshes the link, so assertions on links read the database instead.
2. **Coding standard.** The code follows [Moodle's coding style](https://moodledev.io/general/development/policies/codingstyle)
   and `phpcs --standard=moodle` must report nothing, warnings included. Language strings are in alphabetical order. Every
   class, method and test needs a PHPDoc comment.
3. **Security.** Check access in every entry point, use the Moodle APIs for input, output and database access, and never
   trust a file's name or a value inside an uploaded file. Anything that reads an uploaded file needs limits on how much it
   reads or builds in memory.
4. **Privacy.** If you store information about a person, update the privacy provider and its tests.
5. **Version and notes.** Raise `$plugin->version` and `$plugin->release` in the plugin's `version.php` for any change that
   ships, and add an entry to that plugin's `CHANGES.md`. Add an `upgrade.php` step if the database changes.
6. **Documentation.** If teachers or administrators will notice the change, update the matching guide in `docs/`. If a screen
   changed, retake its screenshot.

## Running the checks

From the Moodle folder, with `export LOCPATH=~/.local/locale` set if PHPUnit complains about a missing locale.
On 5.1 and later, the paths start with `public/`.

    # unit and integration tests
    php public/admin/tool/phpunit/cli/init.php
    vendor/bin/phpunit --testsuite contenttype_mediabank_testsuite
    vendor/bin/phpunit --testsuite local_mediabank_testsuite

    # browser tests (needs chromedriver and the Behat site; see docs/COMMANDS.md)
    php public/admin/tool/behat/cli/init.php
    vendor/bin/behat --config <behat.yml printed by init> --tags=@contenttype_mediabank

**Code style: check it inside a Moodle tree.** `phpcs` run on this repository on its own silently skips the rules that need
to know which plugin a file belongs to, and passes code that the project's CI then rejects. Copy the two plugin folders into
the Moodle tree (a copy, not a symbolic link), run
[moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/)'s `phpcs` on them with the `moodle` standard, then remove the copies.
[docs/COMMANDS.md](docs/COMMANDS.md) has the exact procedure.

Try your change on more than one Moodle version. The plugins run on 5.0, 5.1, 5.2 and 5.3, and the tests are written to pass
on all four.

## Pull requests

- Branch from `main` and keep your branch up to date with it.
- Describe what changed and why, and say which Moodle versions you tested on. The pull request template asks for this and repeats the checklist above.
- Pushing to the repository starts the GitHub Actions workflow (`.github/workflows/ci.yml`). It runs both plugins on PHP 8.3 and
  8.4, Moodle 5.0 to 5.3, and PostgreSQL and MariaDB: code style, PHPDoc, validation, PHPUnit and Behat. A pull request needs
  all of it green.
- Do not change release files in a pull request. Releases are built from a tagged commit with `python3 scripts/build_release.py`.

## Building a release

Maintainers only. Build from a commit that has passed CI:

    python3 scripts/build_release.py --out dist
    php scripts/validate_plugin_zip.php --moodle=<path to Moodle> --zip=<plugin zip> --component=<plugin name>

Then tag the commit (`v<release>`) and publish the zips on the GitHub releases page as a pre-release while the plugins are Beta.
