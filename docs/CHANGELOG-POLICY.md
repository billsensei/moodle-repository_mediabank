# Media bank: How the changelogs are kept

The project keeps three changelogs, and they have different readers. This page says what goes where, how to write an entry, and when to update them. It is for anyone changing the project; the release steps are in [RELEASING.md](RELEASING.md).

## The three files

| File | Reader | What it holds |
|---|---|---|
| [`CHANGELOG.md`](../CHANGELOG.md) (root) | An administrator deciding whether to upgrade | One section per project release: what is new, changed, fixed or secured, in plain words. Also notes on documents and the repository. |
| [`contenttype_mediabank/CHANGES.md`](../contenttype_mediabank/CHANGES.md) | A developer or careful administrator | The detail for that plugin: what changed, why, and what was found by which review. |
| [`local_mediabank/CHANGES.md`](../local_mediabank/CHANGES.md) | The same | The same, for the tools plugin. |

The two `CHANGES.md` files go inside the plugin zips, so they travel with the plugin. The root `CHANGELOG.md` does not.

## Rules

1. **Every change that ships gets an entry**, in the same pull request or commit as the change. Do not leave the changelog to the end.
2. **A code change needs a new version** and a new dated section; see rule 1 in [RELEASING.md](RELEASING.md). Never add a code change to a section that has already been released. The one exception so far, the hardening in the v0.11.0 zips, is recorded in the changelogs themselves.
3. **Use plain words.** The root changelog is read by people who do not write PHP. Say what a person sees or must do. Put the mechanism in `CHANGES.md`.
4. **Say what to do.** If an administrator must act (a new setting, a task to run, a step before upgrading), say so in the first lines of the entry. Add a row to [UPGRADING.md](UPGRADING.md) too.
5. **Be honest about security.** Say what kind of problem it was and who it affected, and what the fix does. Do not publish steps to exploit it, and do not describe an unfixed problem. A fix is released first and described after ([SECURITY.md](../SECURITY.md)).
6. **Credit people** who reported or fixed something, unless they prefer not to be named.
7. **Never rewrite history.** If an entry was wrong, add a correction in a later release; only fix a typo in an old entry.

## The root CHANGELOG.md

- Newest first, with an **Unreleased** section at the top for changes that are on `main` but not in a release.
- A release heading looks like `## [0.11.0] - 2026-10-29`, followed by one line naming both plugin versions: `` `contenttype_mediabank` 0.11.0 (2026102910), `local_mediabank` 0.3.0 (2026102904). ``
- At the bottom, a link for each version: `[0.11.0]: https://github.com/billsensei/moodle-repository_mediabank/releases/tag/v0.11.0`.
- Group entries under these headings, and leave out any that are empty:

| Heading | Use it for |
|---|---|
| **Added** | A new feature, setting, command or document |
| **Changed** | A change in behaviour, or in what a person sees |
| **Fixed** | A bug that is now fixed |
| **Security** | Hardening, or a fixed vulnerability |
| **Removed** | Something taken away, and what replaces it |
| **Tested** | What a release was tested on, if that changed |

- Changes to the repository or the documents only, with no change to the plugins, go in a short paragraph that starts **Repository and documentation only, no change to the plugins:**. They are not a release, but they are worth knowing about.

## The plugin CHANGES.md files

- Newest first, with a `## Unreleased` section while a change is waiting, and `## 0.11.0 (2026-10-29)` for a release.
- One bullet per change. Start with what happened, and add the reason after it.
- Say if a setting is on or off by default, and if a change needs the other plugin to be a certain version.
- Say if a change is hardening found by a review, in a few words.
- The file is read inside Moodle's plugin screens by some administrators, so keep each bullet self-contained.

## An example entry

In the root changelog:

    ### Fixed
    - A video uploaded with its index at the end now starts playing sooner. Run
      `php local/mediabank/cli/optimize_videos.php` once to prepare videos uploaded before this version.

In `contenttype_mediabank/CHANGES.md`:

    - Fixed: the streaming task did not queue a video whose `moov` box was after a very large `mdat`. The limit on the box size is now checked before the file is read, not after.

## Checking before a release

- [ ] "Unreleased" has been renamed to the new version and date in all three files.
- [ ] The version numbers in the changelogs match `version.php` in each plugin.
- [ ] There is a link at the bottom of the root changelog for the new version.
- [ ] Nothing in the entries would help someone attack a site that has not upgraded yet.
- [ ] Every entry says what a person must do, if anything.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
