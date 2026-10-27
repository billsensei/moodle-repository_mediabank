# Changelog

## 0.3.0 (2026-10-27)
- Where-used panel on the item page; items with links cannot be deleted by hand.
- Bulk delete and move convert links to copies first (links are synced before converting).
- Replaced files are kept as versions for a configurable number of days; restore; daily purge task.

## 0.2.0 (2026-10-27)
- Upload rules: allowed kinds, per-kind size limits, SVG off by default, positive content check, same-extension replace.
- Metadata (kind, image size) kept in the item's configdata.
- Capabilities `contenttype/mediabank:access` and `:upload`.
- An mp3 whose ID3 header cannot be skipped cleanly is refused (fail closed); the ID3 footer flag is handled.
- mp3 files with large ID3 tags (cover art) and mp4 files with the M4V brand are accepted. QuickTime-brand (`qt  `, iPhone) mp4 files are accepted.
- Inline serving checks the file content, sends `nosniff`.

## 0.1.0 (2026-10-27)
- Initial scaffold (alpha). Not for production.
