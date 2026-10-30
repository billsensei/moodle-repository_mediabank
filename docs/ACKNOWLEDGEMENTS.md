# Media bank: Acknowledgements

The Media bank stands on a lot of other people's work. This page thanks the projects and tools it uses, and says what each one was used for. People who helped directly are in [CONTRIBUTORS.md](CONTRIBUTORS.md).

Nothing here means the project or its authors endorse the Media bank. Names belong to their owners.

## Moodle

- **Moodle and the Moodle community.** The Content bank, the file picker, the file API, the privacy API, the backup and restore system, the web services framework, and the plugin installer are all Moodle's. The Media bank adds a content type to the Content bank and a small tools plugin, and could not exist without them. Moodle is licensed under the GNU General Public License version 3 or later, like these plugins.
- **Moodle's documentation and developer guides** at [moodledev.io](https://moodledev.io), which describe the APIs the plugins use.
- **Moodle's own tests and coding standard**, which the plugins are checked against on every change.

## Testing and checking

| Tool | Used for |
|---|---|
| [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) | Installing Moodle and running the checks in the GitHub Actions workflow: 32 jobs on every push. |
| [PHPUnit](https://phpunit.de) | The unit and integration tests for both plugins. |
| [Behat](https://behat.org) | The browser tests of what a teacher sees. |
| [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and Moodle's code checker | The coding standard and the PHPDoc checks. |
| [PHP Mess Detector](https://phpmd.org) | Checks for code that is hard to follow. |
| [Grunt](https://gruntjs.com) | Building and checking the JavaScript and Mustache templates. |
| [FFmpeg](https://ffmpeg.org) | Making a real test video in CI. It is only used in the tests: the plugins do not need it on your server. |
| [PostgreSQL](https://www.postgresql.org) and [MariaDB](https://mariadb.org) | The two databases every change is tested on. |
| [PHP](https://www.php.net) | The language, tested on 8.3 and 8.4. |

## Documents and screenshots

| Tool | Used for |
|---|---|
| [Chromium](https://www.chromium.org) and [ChromeDriver](https://chromedriver.chromium.org) | Taking the screenshots in the guides and the two walk-through PDFs from a real Moodle site. |
| [Pillow](https://python-pillow.org) | Drawing the numbered red boxes on the screenshots. |
| [ReportLab](https://www.reportlab.com) | Building the walk-through PDFs. |
| [DejaVu fonts](https://dejavu-fonts.github.io) | The typeface in the PDFs, which covers many languages' characters. |
| [Python](https://www.python.org) | The release build script that makes the zips the same way every time. |

## Community documents

- **[Contributor Covenant](https://www.contributor-covenant.org)**, version 2.1, which the [Code of Conduct](CODE-OF-CONDUCT.md) is adapted from, and **Mozilla's** code of conduct enforcement ladder, which inspired its enforcement steps.
- **[Keep a Changelog](https://keepachangelog.com)**, whose headings (Added, Changed, Fixed, Security) the [changelog](../CHANGELOG.md) borrows. See [CHANGELOG-POLICY.md](CHANGELOG-POLICY.md).
- **[Semantic Versioning](https://semver.org)**, which the release numbers draw on. The plugins are 0.x and Beta, so the numbers do not promise what a 1.0 would.
- **[GitHub](https://github.com)**, for hosting the code, the issues, the releases, private vulnerability reporting and the Actions that run the checks.

## Accessibility standards

- **The Web Content Accessibility Guidelines (WCAG) 2.1**, from the W3C, which the project uses as its guide for accessibility. The plugins do not claim to be certified against it.
- **WebVTT**, from the W3C, the format used for captions.
- The many people who have written about **tagged PDFs and text alternatives**, and who taught the project why the Media bank asks for a text alternative on every image.

## Security

- The people who review Moodle plugins for security, and who published the checklists this project's reviews follow.
- The **automated security reviews** that looked at the code before releases and found real problems, which were fixed first. They are recorded in `contenttype_mediabank/CHANGES.md`.

## An AI assistant

**Claude**, an AI assistant made by [Anthropic](https://www.anthropic.com), wrote much of the code, the tests and the documentation, working with the maintainer in Claude Code. Commits it helped with say so in a `Co-Authored-By` line. The maintainer decided what to build, reviewed it and ran it before each release, and is responsible for it.

## Everyone who tried it

Thanks to everyone who installed an early build, found something confusing, and said so. That includes the people who tried to install the wrong zip and told us. Those reports led to the "Which file do I download?" section of [INSTALL.md](INSTALL.md) and the `READ-ME-FIRST.txt` in the bundle.

## Add to this page

If a project or a person should be here and is not, open an issue ([SUPPORT.md](SUPPORT.md)) or add them in a pull request. Say what they did or what the tool was used for.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
