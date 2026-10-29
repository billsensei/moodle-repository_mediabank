# Changelog

All notable changes to the project. Each plugin also keeps its own, more detailed list:
[`contenttype_mediabank/CHANGES.md`](contenttype_mediabank/CHANGES.md) and [`local_mediabank/CHANGES.md`](local_mediabank/CHANGES.md).

Both plugins are **Beta**. A release of the project is a tag on GitHub, `v<content type version>`, with the two plugin zips
and the bundle zip attached.

## Unreleased

Repository and documentation only, no change to the plugins:

- Added `README.md` (it was only a title), `LICENSE` (GNU GPL v3), `composer.json`, `CONTRIBUTING.md`, `SECURITY.md`,
  `CODE_OF_CONDUCT.md`, a pull request template and issue forms.
- Teacher guide: screenshots retaken with real sample content and a neutral site name, and three new ones (the More menu, the
  Edit page of a video and of a picture). The bundle zip on the v0.10.0 release was replaced with a version that has them; the two
  plugin zips are the same files.

## [0.10.0] - 2026-10-29

`contenttype_mediabank` 0.10.0 (2026102909), `local_mediabank` 0.3.0 (2026102904).

### Added
- **MP4 videos are prepared for streaming.** After an MP4 is uploaded or replaced, a background task moves the file's index in
  front of the picture and sound, so playback can start before the whole file has downloaded. Nothing is re-encoded and the file
  keeps its size. It is done in PHP: no ffmpeg or other program is needed on the server.
- The file as uploaded is kept as an earlier version for the usual retention time. Links from courses follow the new file.
- Setting "Prepare MP4 videos for streaming" (on by default) and the task "Prepare a media bank video for streaming".
- `php local/mediabank/cli/optimize_videos.php [--dry-run]` queues videos that were uploaded before this version.

### Security
- Limits on what is read from a video file: an index over 32 MB, more than 4 million chunk offsets, or boxes nested deeper than a
  real index are refused, and nothing large is built in memory. Found by an automated review before release.
- A file that cannot be prepared is remembered and not tried again until it is replaced. Doing so adds one key to the item's
  current metadata, so edits made meanwhile are kept. Disk and read errors are retried.

### Tested
- 109 plugin tests and 7 backup tests on Moodle 5.0 to 5.3, and the 7 browser scenarios on 5.3. GitHub Actions: all 32 jobs
  (PHP 8.3 and 8.4, Moodle 5.0 to 5.3, PostgreSQL and MariaDB), including a test that decodes a real video before and after.

## [0.9.2] - 2026-10-29

`contenttype_mediabank` 0.9.2 (2026102908), `local_mediabank` 0.2.2 (2026102903). The first published release.

### Added
- **Content type** `contenttype_mediabank`: video, audio, PDF and image files in Moodle's Content bank, used in courses as a link
  or as a copy through the standard file picker.
- Upload rules: allowed kinds, size limits per kind, SVG off by default, and a check that a file's content matches its name.
- A "where this item is used" panel, and protection against deleting an item that courses link to.
- Replace with undo: replaced files are kept as versions for a set number of days and can be restored.
- Edit form: text alternative (required for images), transcript, WebVTT captions, and converting all links to copies.
- Read-only web services, events for links, conversions and restores, and a privacy provider for GDPR export and erasure.
  When a person is erased, courses that linked to their media keep the file as an ordinary copy.
- **Tools** `local_mediabank`: optional carrying of linked files in course backups (restored as copies where a link cannot be kept),
  and the command-line tool `cli/item.php`.
- Install, administrator and teacher guides, and scripts to build and validate the release zips.

### Security
- An item's file is served only to people who may see the item. Before, any logged-in user who knew the address could fetch any
  item, unlisted ones included.
- Restore does not trust a backup file: a content hash must be 40 hex characters, the file must sit inside the backup's file
  pool, and it must hash to its own name.

### Changed
- Both plugins are Beta. Language strings are in alphabetical order and the privacy test is in `tests/privacy/`, as Moodle's
  current coding standard requires (found by the first GitHub Actions run).

[0.10.0]: https://github.com/billsensei/moodle-repository_mediabank/releases/tag/v0.10.0
[0.9.2]: https://github.com/billsensei/moodle-repository_mediabank/releases/tag/v0.9.2
