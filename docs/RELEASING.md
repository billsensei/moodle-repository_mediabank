# Media bank: Releasing

A step-by-step checklist for putting out a new version. It is for the maintainer. The reasons behind the steps are in [MAINTAINERS.md](MAINTAINERS.md), and the commands for tests and style are in the [Development guide](DEVELOPMENT.md).

Work through it in order and tick each box. Do not skip the checks because the change is small.

## The rules that matter most

1. **A change to the plugins' code needs a new version number and a new release.** Moodle only offers an upgrade when the number goes up, so a fix released under the old number never reaches the sites that already installed it.
2. **Never move or re-create a tag** after it is published.
3. **Merge to `main` only when all 32 CI jobs are green.**
4. **Publish as a pre-release** while the plugins are Beta.
5. **Attach the zips built from the commit that passed CI.**

A change to the guides alone is not a release. Push it to `main`, and, if the guides are in the bundle, replace the bundle on the latest release (see the end of this page).

## 1. Decide the version

| Change | `$plugin->release` | `$plugin->version` |
|---|---|---|
| Fix, no new feature | patch, for example 0.11.0 → 0.11.1 | `YYYYMMDDnn` of the day, `nn` counting up from 00 |
| New feature or setting | minor, for example 0.11.0 → 0.12.0 | same |
| Breaks something or changes what an administrator must do | minor while the plugin is 0.x, and say so at the top of the notes | same |

- `version` must be higher than the last release's. Two releases on the same day use `01`, `02` and so on.
- The project tag is `v<content type release>` (for example `v0.11.1`). If only `local_mediabank` changed, also raise the content type's release number by a patch so there is a new tag, and say in the notes that it has no code change.
- `local_mediabank` lists the content type version it needs under `$plugin->dependencies`. Raise that only if the tools plugin now needs something newer.
- `requires` stays at Moodle 5.0 (`2025041400`) and `supported` at `[500, 503]` unless you are adding or dropping a Moodle version (see "When Moodle releases a new version" in [MAINTAINERS.md](MAINTAINERS.md)).
- If the database changes, add an `upgrade.php` step. So far no release has needed one, because the plugins have no tables of their own.

## 2. Prepare a branch

    git switch -c release-<version>

Update, on that branch:

- [ ] `version.php` in each plugin that changed (`version` and `release`).
- [ ] `contenttype_mediabank/CHANGES.md` and `local_mediabank/CHANGES.md`: rename "Unreleased" to the new version and date.
- [ ] Root `CHANGELOG.md`: a new dated section with both plugin versions, and a `[x.y.z]: https://github.com/billsensei/moodle-repository_mediabank/releases/tag/vx.y.z` link at the bottom.
- [ ] [UPGRADING.md](UPGRADING.md): a row in the per-version table if an administrator sees any difference.
- [ ] [SECURITY.md](../SECURITY.md): the supported-version table (for example `0.11.x`).
- [ ] [ROADMAP.md](ROADMAP.md): the version numbers at the top, and move finished items out of "might come next".
- [ ] [COMMANDS.md](COMMANDS.md): the "Current numbers" table (tests, assertions, Behat).
- [ ] The guides, if what a person sees has changed. Redo the affected pages of the two PDF walk-throughs if a screen changed.
- [ ] Any user-visible words are language strings, kept in alphabetical order.

## 3. Check it locally

Run these on Moodle 5.0, 5.1, 5.2 and 5.3. The commands are in [DEVELOPMENT.md](DEVELOPMENT.md).

- [ ] PHPUnit for both plugins, with no warnings.
- [ ] Behat for both plugins.
- [ ] `phpcs` with the Moodle standard, run inside a Moodle tree, as CI does it (section 3 of the Development guide).
- [ ] If the change touched uploading, serving files, SVG, video preparation, backup or privacy, run a security review of the changed files, and the privacy provider tests.

## 4. Build and validate the zips

    python3 scripts/build_release.py --out dist

This writes `contenttype_mediabank_<version>.zip`, `local_mediabank_<version>.zip` and `mediabank-<release>-bundle.zip`. Then, for each zip, on each Moodle version, on a site where the plugin is not installed:

    php scripts/validate_plugin_zip.php --moodle=<moodle> --zip=dist/<zip> --component=contenttype_mediabank
    php scripts/validate_plugin_zip.php --moodle=<moodle> --zip=dist/<zip> --component=local_mediabank

- [ ] Every check passes. The only warning expected is the Beta one.
- [ ] The bundle is **not** a plugin: do not run the validator on it, and do not upload it to Moodle.
- [ ] Spot-check the bundle: it holds both plugin zips, the guides in `GUIDES` in `scripts/build_release.py`, the two PDFs and the `images/` folder.

Do a real install once on a clean site (content type first, then the tools plugin), or, for an upgrade, over the previous release. Follow [INSTALL.md](INSTALL.md) exactly as a user would.

## 5. Get CI green

    git push origin release-<version>
    gh run list --branch release-<version> --limit 1
    gh run view <id> --log-failed

- [ ] All 32 jobs are green. Read a failure with `--log-failed` once the whole run has finished; logs are not available while it runs.
- [ ] If a fix is needed, make it on the branch, rebuild the zips and start from section 4 again.

## 6. Merge and tag

    git switch main
    git merge --ff-only release-<version>
    git tag -a v<release> -m "Media bank <release> (Beta)"
    git push origin main v<release>

- [ ] `main` fast-forwarded, so the tag is on the commit CI tested.
- [ ] Rebuild the zips from `main` if the branch commit was not the same one, and check they match what you validated.

## 7. Publish the release

    gh release create v<release> --prerelease --title "Media bank <release> (Beta)" \
        --notes-file <notes.md> \
        dist/contenttype_mediabank_<version>.zip \
        dist/local_mediabank_<version>.zip \
        dist/mediabank-<release>-bundle.zip

The notes should say, in plain words:

- what is new or fixed, and for whom;
- anything an administrator must decide or do, for example a new setting;
- **which file to download**: the two plugin zips, not the GitHub "Source code" files and not the bundle (see "Which file do I download?" in [INSTALL.md](INSTALL.md));
- that it is Beta, so try it on a copy of the site first.

Then check:

- [ ] `gh release view v<release>` shows the three assets, with the sizes you built.
- [ ] Download the two plugin zips from the release and run them through the validator once more.
- [ ] The release is marked **Pre-release**.

## 8. After the release

- [ ] Delete the branch: `git push origin --delete release-<version>` and `git branch -d release-<version>`.
- [ ] Open the README and check the guide links work.
- [ ] Add anyone who helped to [CONTRIBUTORS.md](CONTRIBUTORS.md).
- [ ] Answer any issues the release closes.

## Guides only, after a release

If only the guides changed:

1. Push to `main`.
2. If any changed guide is in the bundle, rebuild and replace only the bundle:

       python3 scripts/build_release.py --out <dir>
       gh release upload v<release> <dir>/mediabank-<release>-bundle.zip --clobber

3. Leave the two plugin zips alone. Check that the sizes of those two in `gh release view` did not change.

The guides in the bundle are the ones in `GUIDES` in `scripts/build_release.py`. Documents such as this one, [ROADMAP.md](ROADMAP.md), [SUPPORT.md](SUPPORT.md) and [MAINTAINERS.md](MAINTAINERS.md) are not in it, so changing only those needs no bundle swap.

## If a release is wrong

- **A mistake in the notes:** edit them with `gh release edit v<release> --notes-file <notes.md>`.
- **A mistake in a guide:** see "Guides only" above.
- **A mistake in the code:** do not replace the zips. Fix it on a branch and release the next version, and say in the notes that it replaces the one before. If a release is dangerous, mark it a draft or delete the release (not the tag) so it can no longer be downloaded, and say so in an issue and the changelog.
- **A security problem:** follow [SECURITY.md](../SECURITY.md). Release the fix first and describe it after.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
