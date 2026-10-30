# Media bank: Maintainers

This page is for the person (or people) who look after the project: what the job is, the routine, and the rules that keep releases safe. It does not repeat the how-to; the [Development guide](DEVELOPMENT.md) has the commands.

If you only want to use the plugins, you do not need this page. See the [Administrator guide](ADMIN-GUIDE.md) or the [Teacher guide](TEACHER-GUIDE.md). If you want to help, see [CONTRIBUTING.md](../CONTRIBUTING.md).

## Who maintains it

| Role | Person |
|---|---|
| Owner and maintainer | **Bill White** ([SenseiMoodle](https://github.com/billsensei)) |

Contact the maintainer through GitHub: an issue for anything public, and a private security report for anything that could harm a site. See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](../SECURITY.md). No personal email address is published here on purpose.

## What the maintainer is responsible for

- Answering issues and pull requests on a best-effort basis. There is no promised reply time ([SUPPORT.md](SUPPORT.md)).
- Handling security reports privately, fixing first and describing after ([SECURITY.md](../SECURITY.md)).
- Handling conduct reports privately ([CODE-OF-CONDUCT.md](CODE-OF-CONDUCT.md)).
- Deciding what goes in and what does not, and keeping the [Roadmap](ROADMAP.md) honest.
- Releasing: version numbers, changelogs, tags, release files.
- Keeping the plugins working on every supported Moodle version (5.0 to 5.3) as Moodle releases new ones.
- Crediting people in [CONTRIBUTORS.md](CONTRIBUTORS.md).

## Standing rules

These are decisions already made. Change them only on purpose, and write the change in the changelog.

1. **SVG stays off by default.** It is switched on by an administrator, shown only as a picture, and checked against a list of what is allowed.
2. **Erasing a person keeps courses' files** as ordinary copies. Their bank items, earlier versions, captions, alt text and transcripts are erased.
3. **The content type is the main plugin.** The tools plugin (`local_mediabank`) must keep working as an optional extra, and it names the content type version it needs in `$plugin->dependencies`.
4. **No tables of the plugins' own.** Item data lives in the Content bank's tables. If that ever changes, it needs an `upgrade.php` step, a privacy review and a note in [UPGRADING.md](UPGRADING.md).
5. **Nothing is merged unless all 32 CI jobs are green** (see "The CI workflow" in the Development guide).
6. **Check access in the plugin.** Serving a file is decided by `contenttype::can_serve`, not left to core.
7. **No secrets and no personal email addresses** in the repository, the issues or the release notes.

## Looking after issues

| Kind | What to do |
|---|---|
| Question | Point to the guide that answers it ([FAQ](FAQ.md), [Troubleshooting](TROUBLESHOOTING.md)). If the guide was unclear, fix the guide. |
| Bug report | Ask for the plugin version, Moodle version, PHP and database, and the steps. Reproduce it, then add a test that fails before the fix. |
| Feature request | Put it in the [Roadmap](ROADMAP.md) if it fits. It is fine to say no. |
| Install failure | Check first that the person uploaded the right file (the two plugin zips from the release's **Assets**, not the GitHub source zip or the bundle). See "Which file do I download?" in [INSTALL.md](INSTALL.md). |
| Security | Move it out of public view at once and follow [SECURITY.md](../SECURITY.md). Never discuss details in a public issue. |
| Conduct | Follow [CODE-OF-CONDUCT.md](CODE-OF-CONDUCT.md). |

If a bug report contains a private name, email address, token or student detail, ask for it to be edited out of the issue.

## Reviewing a pull request

A change is ready to merge when:

- it does one thing, and says why;
- it has a test, and all 32 CI jobs pass;
- user-visible text is a language string, kept in alphabetical order;
- the plugin's `version.php`, its `CHANGES.md` and the root `CHANGELOG.md` are updated when it ships;
- the guides change if what a person sees changes;
- anything that reads an uploaded file has a limit and refuses what it does not understand (see "Security review habits" in the Development guide);
- the contributor is added to [CONTRIBUTORS.md](CONTRIBUTORS.md), if they want to be.

## Making a release

The steps are in "Releasing" in the [Development guide](DEVELOPMENT.md). In short:

1. Update the version, the release name and the changelogs on a branch.
2. Run the checks on Moodle 5.0, 5.1, 5.2 and 5.3, then build the zips with `scripts/build_release.py` and validate each one with `scripts/validate_plugin_zip.php`.
3. Merge only when CI is green. Tag `v<release>` (annotated).
4. Publish a **pre-release** on GitHub while the plugins are Beta, attaching the two plugin zips and the bundle zip.
5. Update the supported version in [SECURITY.md](../SECURITY.md) and the "Current numbers" in [COMMANDS.md](COMMANDS.md).

Two rules about a release that has already been published:

- **Never move its tag.**
- **A code change gets a new version number and a new release.** A change to the guides alone may be pushed to `main`, and the bundle on the release may be replaced with a rebuilt one. If the plugin zips are ever replaced, say so in the changelog, because people who already installed that version will not see the difference.

## When Moodle releases a new version

1. Add the new `MOODLE_<n>_STABLE` branch to the CI matrix and run it.
2. Read Moodle's `UPGRADING.md` for removed or deprecated functions the plugins use.
3. If it passes, raise `$plugin->supported` in both `version.php` files, then update the README, [INSTALL.md](INSTALL.md), the [FAQ](FAQ.md) and [SECURITY.md](../SECURITY.md).
4. If it does not pass, say so in the README rather than guessing.
5. When a Moodle version reaches end of life, stop testing on it only after announcing it in the changelog.

## Looking after the documents

- The guides are in `docs/`. The two PDF walk-throughs ([Install](Install-Walk-Thru.pdf) and [Use](Use-Walk-Thru.pdf)) are made from real screenshots, so they go out of date when screens change. Redo the affected pages when Moodle's Install plugins or Content bank screens change.
- The release bundle contains the guides listed in `GUIDES` in `scripts/build_release.py`. When a guide is added there, it is in the next bundle.
- Keep the [Glossary](GLOSSARY.md) in step with the words the screens use.

## If the maintainer cannot continue

Anyone may fork the project: it is licensed under the GNU General Public License version 3 or later ([LICENSE](../LICENSE)). If you want to take over, open an issue to say so. A new maintainer is added to the table above and to [CONTRIBUTORS.md](CONTRIBUTORS.md), and is given the repository's access by its owner.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
