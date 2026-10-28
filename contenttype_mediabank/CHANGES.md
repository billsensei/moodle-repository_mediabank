# Changelog

## 0.6.1 (2026-10-29)
- Security: an item's file is now served only to people who may see the item (access to the media bank in that context, and not someone else's unlisted item). Before, core only checked that the user was logged in, so any logged-in user who knew the URL could fetch any item, unlisted ones included. A refused request gets "file not found".

## 0.6.0 (2026-10-29)
- New setting "Include linked files in course backups" (off by default), used by local_mediabank. Makes manual course backups larger by the size of the linked files.

## 0.5.0 (2026-10-29)
- Privacy provider (GDPR). Replaces the "stores no personal data" declaration, which was wrong: alt text, transcripts, captions and earlier versions belong to the person who created the item. Export writes them under the plugin's own folder, so core's export of the item is not overwritten. Delete removes the plugin's files for the user's items, and files whose item row core already deleted, so the result is the same whichever provider runs first.
- Known gap (policy decision needed): core's own privacy deletion removes an item without calling the content type, so links are not converted to copies first. A link that has been opened keeps its content. A link nobody has opened is left as an empty file in the course.

## 0.4.0 (2026-10-27)
- Edit form (core's Edit button): text alternative (required for images), transcript, WebVTT captions, restore an earlier version, download earlier versions, convert all links to copies. New capability `contenttype/mediabank:useeditor`.
- Preview is a Mustache template: captions track, transcript in a collapsed details element, image alt text (falls back to the item name), PDF height in CSS.
- `lib.php` serves captions (inline) and earlier versions (download only, managers only); the item must be a media bank item in the context named by the URL.
- Captions are not served for unlisted items to people who may not see unlisted content.
- Captions move with the item, are deleted with it, and orphans are swept by the daily task.

## 0.3.0 (2026-10-27)
- Where-used panel on the item page; items with links cannot be deleted by hand.
- Bulk delete and move convert links to copies first (links are synced before converting).
- Replaced files are kept as versions for a configurable number of days (each holds its full disk size meanwhile); restore; daily purge task that also removes versions of items core deleted. Versions keep no user, author or source.

## 0.2.0 (2026-10-27)
- Upload rules: allowed kinds, per-kind size limits, SVG off by default, positive content check, same-extension replace.
- Metadata (kind, image size) kept in the item's configdata.
- Capabilities `contenttype/mediabank:access` and `:upload`.
- An mp3 whose ID3 header cannot be skipped cleanly is refused (fail closed); the ID3 footer flag is handled.
- mp3 files with large ID3 tags (cover art) and mp4 files with the M4V brand are accepted. QuickTime-brand (`qt  `, iPhone) mp4 files are accepted.
- Inline serving checks the file content, sends `nosniff`.

## 0.1.0 (2026-10-27)
- Initial scaffold (alpha). Not for production.
