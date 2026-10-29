# Changelog

## 0.11.0 (2026-10-29)
- SVG images can be allowed with the setting "Allow SVG images", which stays **off by default**. Turn it on only if you want them; see the Admin guide, "SVG files".
- An SVG is shown on the item page as a picture. The file is sent only as a download, with `Content-Security-Policy: sandbox; default-src 'none'` and `nosniff`, so opening its address never runs it as a page.
- An SVG is read as XML on upload and replace and refused unless it is only a picture: every element must be on a list of drawing elements (no script, animation, foreignObject or foreign namespaces, however they are prefixed), no `on...` attributes, links only to a `#fragment`, a web or mail address on an anchor or a small embedded png, jpeg, gif or webp on an image, no style rule that loads anything, no processing instructions, no entity definitions. Values are checked after character references are decoded. Files over 2 MB are refused. Class `svg_validator`. This replaces a keyword search that a prefixed `<svg:script>` got past (found by an automated review); the way the file is shown (as an image) and sent (download with a sandbox policy) is still the main protection. A second review found that style rules were checked after stripping comments (a comment marker inside a quoted string could hide a `url(...)`), that CSS escapes (`ur\6c(`), `image-set()` and `src()` were not covered, and that other encodings were not refused. A third review found that a `url(` with no closing bracket (which CSS closes at the end of the text) was not noticed. A fourth review found that when the regular expression engine gave up (a very long unclosed `data:` address makes it back up too far), the check read the failure as "nothing found" and let the file through; every search that refuses on a match now counts a failure as a match, the patterns that could back up use possessive quantifiers, and a failure of a replace refuses the file. Every `url()` in a style or attribute must now be exactly `#id` or a base64 png, jpeg, gif or webp, with optional matching quotes and spaces; anything else with `url(` in it is refused. Style rules are now read as they are, with no comment stripping; a backslash in a style, and `image-set`, `src()`, `element()`, `@font-face`, `@namespace` are refused; and only UTF-8, ASCII and common single-byte encodings are accepted.
- The image kind in the settings is now listed as png, jpg, gif, webp and svg.

## 0.10.0 (2026-10-29)
- New: MP4 videos are prepared for streaming. When an MP4 is uploaded or replaced, an ad hoc task moves the file's index (the `moov` box) in front of the picture and sound data, so a browser can start playing before the whole file has downloaded. Nothing is re-encoded: picture and sound are untouched and the file keeps its size. No program such as ffmpeg is needed on the server.
- The file as uploaded is kept as an earlier version for the retention time (not kept if the retention is 0). Links from courses follow the new file. The item's last editor is not changed.
- Only plain MP4 files with the index last are changed. Already streamable, fragmented, compressed-index and malformed files are left alone, and a failure leaves the video as it was.
- Limits on what is read from a file: an index over 32 MB, more than 4 million chunk offsets, or boxes nested deeper than a real index are refused, and nothing large is built in memory. A file that fails is remembered (by its content hash) and not queued again until it is replaced. Disk and read errors are not remembered. Remembering a failure adds one key to the item's current metadata, so edits made meanwhile are kept.
- New setting "Prepare MP4 videos for streaming" (on by default), and a task "Prepare a media bank video for streaming".
- Videos uploaded before this version are queued with `local/mediabank/cli/optimize_videos.php` (local_mediabank 0.3.0).

## 0.9.2 (2026-10-29)
- No change in behaviour. The language strings are now in alphabetical order and the privacy test is in `tests/privacy/provider_test.php`, as Moodle's current coding standard requires (found by the first GitHub Actions run).

## 0.9.1 (2026-10-29)
- Maturity changed from Alpha to **Beta**: released for testing on a copy of your site first.
- The README now describes the plugin as it is.

## 0.9.0 (2026-10-29)
- Policy decided by Bill, 2026-10-29: when a person's data is erased, courses that link to media they uploaded keep their file as an ordinary copy. The person's item, its earlier versions, captions, alt text and transcript are erased. This closes the "known gap" in 0.5.0, where a link nobody had opened was left as an empty file.
- A new link is given the item's content as soon as it is saved. Reading a link through its file object does the same, so a link was empty only until it was first shown; the gap was real but short for most links.
- The privacy provider refreshes the links to the items it is about to erase, before they go.
- New daily task "Refresh the content of media bank links" (03:47): brings links that are empty or hold an older version up to date, for links made by a restore and links made before this version. One update per linked item.
- Still possible, narrowly: a link restored on the same site is empty until something reads it or the daily task runs. If Moodle ever runs core's provider before ours and the erasure comes in that window, that course gets an empty file. Today Moodle runs ours first.

## 0.8.0 (2026-10-29)
- Events for what core does not report: `item_linked` (a link to an item is saved in a course or elsewhere; the context is where the link is), `links_converted` (links turned into copies; carries the count; used by the edit form, move and delete) and `version_restored` (carries the version number).
- `item_linked` uses core's `after_file_created` hook. The link a teacher picks first exists in a draft area and is not reported; the saved link is. Links made by a restore or a course copy are not reported, because core creates those without a hook.
- Not added: an event for a copy (a copy is an ordinary file), and one for refused uploads.
- Upload, replace, rename, visibility, alt text, transcript and caption changes, move, view and delete are already reported by core's `contentbank_content_*` events, which fire for media bank items.

## 0.7.0 (2026-10-29)
- Web service functions (read only): `contenttype_mediabank_get_items` (list a context, filter by kind and name, paged), `contenttype_mediabank_get_item` (one item with file and caption addresses, alt text, transcript) and `contenttype_mediabank_get_usage` (courses that link to an item; managers only; courses the caller cannot see are counted, not named). They are not added to any service; an admin adds them to an external service. They are not in the Moodle mobile app service.
- Every function checks access to the media bank in the item's context, which core's search does not. A missing item, an item of another type and an item the caller may not see all give the same error. The creator is not returned.
- Uploading, renaming, copying, visibility and deleting are done with core's own functions (`core_contentbank_*`); deleting a linked item over web services is refused like it is in the screen.

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
