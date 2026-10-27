# Changelog

## 0.4.0 (2026-10-27)
- Edit form (core's Edit button): text alternative (required for images), transcript, WebVTT captions, restore an earlier version, download earlier versions, convert all links to copies. New capability `contenttype/mediabank:useeditor`.
- Preview is a Mustache template: captions track, transcript in a collapsed details element, image alt text (falls back to the item name), PDF height in CSS.
- `lib.php` serves captions (inline) and earlier versions (download only, managers only); the item must be a media bank item in the context named by the URL.
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
