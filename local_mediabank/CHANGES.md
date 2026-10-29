# Changelog

## 0.3.0 (2026-10-29)
- New command `php local/mediabank/cli/optimize_videos.php [--dry-run]`: queue the MP4 videos already in the media bank that are not ready for streaming. Needs contenttype_mediabank 0.10.0.

## 0.2.2 (2026-10-29)
- Maturity changed from Alpha to **Beta**: released for testing on a copy of your site first.
- The README now describes the plugin as it is.

## 0.2.1 (2026-10-29)
- The command-line tool says "There is no content bank item with id N." for an id that does not exist, instead of Moodle's generic database message.
- Install zips and guides: see the main repository (`scripts/build_release.py`, `docs/`).

## 0.2.0 (2026-10-29)
- Backup and restore of linked files. With the new site setting (contenttype_mediabank "Include linked files in course backups", off by default), manual course backups carry the files behind media bank links in the course's own files and in activities. On restore, a link that core cannot restore (a different site, or the bank file is gone) is created as an ordinary copy; on the same site with the bank file present the link is kept. Import, course copy and automated backups are unchanged.
- Restore does not trust the backup file: a content hash must be 40 hex characters, the file must sit inside the backup's file pool and must hash to its own name.
- Limits: links in block and section-summary files are not carried; copies do not keep the item's alt text, transcript or captions; the setting is site-wide because Moodle gives plugins no per-backup option.

## 0.1.1 (2026-10-27)
- CLI `cli/item.php`: usage, detach links, list and restore versions.

## 0.1.0 (2026-10-27)
- Initial scaffold (alpha). Not for production.
