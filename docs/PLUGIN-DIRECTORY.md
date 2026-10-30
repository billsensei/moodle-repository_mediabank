# Media bank: Submitting to the Moodle Plugins directory

The text and checklist for listing the two plugins in the Moodle Plugins directory (moodle.org/plugins). Copy the text into the submission form. The directory's form and rules change from time to time, so read its current submission guide at [moodledev.io](https://moodledev.io) first, and treat the field names below as a guide.

This is not the same as the Moodle Marketplace, which is a separate route for plugins that are sold. Nothing here covers it.

## What you submit

Two separate entries, one for each plugin. The directory works one plugin at a time.

| Order | Plugin | Zip | Required? |
|---|---|---|---|
| 1 | `contenttype_mediabank` | `contenttype_mediabank_<version>.zip` | Yes. This is the main plugin. |
| 2 | `local_mediabank` | `local_mediabank_<version>.zip` | Optional. It adds backup of linked files and the command-line tools. It needs the first. |

You can submit the content type alone and add the tools later. Submit the content type first either way, because the tools plugin names it as a dependency.

Upload the **plugin zips from the GitHub release**, not the bundle and not GitHub's source zip. The folder inside each is called `mediabank`, as the directory expects.

## Before you submit

- [ ] A moodle.org account, and you are content to be the listed maintainer.
- [ ] The release you are submitting is tagged and published on GitHub, and CI is green for it.
- [ ] Both zips pass `scripts/validate_plugin_zip.php` on Moodle 5.0, 5.1, 5.2 and 5.3. See [RELEASING.md](RELEASING.md).
- [ ] `version.php` has the right `release`, `maturity` (Beta), `requires` and `supported`.
- [ ] The privacy provider tests and the Moodle compliance test pass.
- [ ] The README and the guides are current, with no personal email, token or password anywhere ([STYLE-GUIDE.md](STYLE-GUIDE.md)).
- [ ] The issue tracker on GitHub is open and the issue forms work.
- [ ] You have read the directory's current guidelines and have nothing in the plugin they forbid.

## Entry 1: Media bank content type

**Plugin name (as shown):** Media bank content type

**Frankenstyle name:** `contenttype_mediabank`

**Short description** (one or two sentences):

> Keep videos, audio files, PDFs and images in Moodle's Content bank and use them in many courses, either as a link that follows the original or as an independent copy.

**Full description:**

> The Media bank adds a content type to Moodle's Content bank for videos, audio files, PDFs and images. A teacher uploads a file once, then picks it from Moodle's own file picker in any course and chooses:
>
> * **Link to the file**: the course follows the Content bank item. Replace the item and every course that links to it gets the new file.
> * **Make a copy of the file**: the course gets its own file that never changes.
>
> Features:
>
> * Upload checks that read what is inside a file, not just its name, with limits for each kind (video, audio, PDF, image).
> * A "Where this item is used" panel that lists the courses that link to it, and an item that courses link to cannot be deleted by accident.
> * Replace a file, keep the earlier one for a time, and put it back.
> * Accessibility fields: a required text alternative for images, and a transcript and WebVTT captions for video and audio.
> * MP4 videos are prepared in the background so playback can start before the whole file has downloaded. Nothing is re-encoded, and no extra software is needed on the server.
> * SVG images are supported only if an administrator turns them on. They are off by default, checked against a list of what they may contain, shown as pictures and only sent as downloads.
> * Three read-only web service functions to list items, get one item, and see which courses use it.
> * A privacy provider: when a user is erased their items, versions, captions and transcripts are erased, and courses that linked to them keep the file as a copy.
>
> **Beta.** Released for testing. Try it on a copy of your site first. It is tested on Moodle 5.0 to 5.3 with PHP 8.3 and 8.4, on PostgreSQL and MariaDB. It does not support the Moodle mobile app.
>
> Install the content type first. The optional "Media bank tools" plugin (`local_mediabank`) adds backup of linked files and command-line tools.

**Moodle versions:** 5.0, 5.1, 5.2, 5.3

**Maturity:** Beta

**Licence:** GNU GPL v3 or later

**Tags / categories** (choose from the directory's list): Content bank, Files, Media, Accessibility, Video, Audio, PDF

**URLs**

| Field | Address |
|---|---|
| Source code | https://github.com/billsensei/moodle-repository_mediabank |
| Issue tracker | https://github.com/billsensei/moodle-repository_mediabank/issues |
| Documentation | https://github.com/billsensei/moodle-repository_mediabank/blob/main/docs/GETTING-STARTED.md |
| Release notes | https://github.com/billsensei/moodle-repository_mediabank/blob/main/contenttype_mediabank/CHANGES.md |

**Release notes for this version:** copy the section for the version from [`contenttype_mediabank/CHANGES.md`](../contenttype_mediabank/CHANGES.md).

**Screenshots** (add alt text to each in the form), from `docs/images/`:

1. `teacher-1-content-bank.png`: the Content bank in list view, with three items.
2. `teacher-2-item-page.png`: an item page with a preview and the courses that link to it.
3. `teacher-4-link-or-copy.png`: the file picker, with Link to the file selected.
4. `teacher-5-more-menu.png`: the More menu, with Replace with file.
5. `teacher-3-edit-form.png`, `teacher-6-edit-video.png`, `teacher-7-edit-image.png`: the Edit page, with the accessibility fields.

## Entry 2: Media bank tools

**Plugin name (as shown):** Media bank tools

**Frankenstyle name:** `local_mediabank`

**Short description:**

> Optional companion to the Media bank content type: carries linked Media bank files in course backups, and adds command-line tools.

**Full description:**

> This plugin needs the **Media bank content type** (`contenttype_mediabank`), which must be installed first. The content type works without it.
>
> * **Backup and restore of linked files.** With the content type's setting **Include linked files in course backups** switched on (it is off by default), manual course backups carry the files behind Media bank links, so restoring on a different site does not leave them out. Where a link cannot be kept on restore, the file becomes an ordinary copy. Restore does not trust the backup file: hashes and paths are checked.
> * **Command-line tools.** `local/mediabank/cli/item.php` shows one item, the courses that link to it and its earlier versions, and can turn its links into copies or restore a version. `local/mediabank/cli/optimize_videos.php` queues videos uploaded before streaming preparation existed (with `--dry-run` to only count them).
>
> It has no database tables of its own and stores no personal data. **Beta**: try it on a copy of your site first.

**Dependency:** `contenttype_mediabank`, the version named in `local_mediabank/version.php`.

**Moodle versions, maturity, licence:** as above.

**URLs**

| Field | Address |
|---|---|
| Source code | https://github.com/billsensei/moodle-repository_mediabank |
| Issue tracker | https://github.com/billsensei/moodle-repository_mediabank/issues |
| Documentation | https://github.com/billsensei/moodle-repository_mediabank/blob/main/docs/ADMIN-GUIDE.md |
| Release notes | https://github.com/billsensei/moodle-repository_mediabank/blob/main/local_mediabank/CHANGES.md |

**Screenshots:** the plugin has no screens of its own. Either leave it without, or use `teacher-1-content-bank.png` with a note that the setting is on the content type's settings page.

## Things to say honestly in the listing

The directory's readers are administrators. Do not leave these out:

- It is **Beta** and has had testing but not long use on real sites.
- The mobile app is **not supported**.
- Captions, alt text and transcripts stay with the bank item. A course that links to or copies the file gets the bare file.
- SVG has not had a manual security review, and it is off by default.
- Uninstalling the content type **deletes every item** in the bank.
- Moodle 4.5 and earlier are not supported.

These are all in the [FAQ](FAQ.md) and the [Administrator guide](ADMIN-GUIDE.md).

## After submitting

- Expect a review by the directory's approvers. They may ask for changes. Answer on the submission, and fix in the repository.
- A fix that changes code means a **new version and a new release** ([RELEASING.md](RELEASING.md)). Upload the new zips as a new version of the entry.
- Keep the listing text and the README in step with each other.
- When the directory approves a version, add a line to the changelog and the README ("Also available from the Moodle Plugins directory").
- The directory lets users follow a plugin and ask for support. Say in the listing where you want support: the GitHub issue tracker ([SUPPORT.md](SUPPORT.md)).

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
