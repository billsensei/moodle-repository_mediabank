# Media bank content type (contenttype_mediabank)

Adds video, audio, PDF and image files to the core Moodle Content Bank, so they can be
uploaded once at site level and used in any course, as a link (updates follow the bank item)
or as an independent copy. Works with the core file picker. Moodle 5.0 to 5.3.

Status: alpha scaffold. Install by copying this folder to `contentbank/contenttype/mediabank`.

Large files: the Content Bank upload limit is `userquota` (default 100 MB). See `docs/COMMANDS.md`.
SVG files are stored but always served as a download (they can carry scripts).

## Settings
Site administration > Plugins > Content bank > Media bank: allowed kinds, SVG on/off, maximum size per kind.
Uploads and replacements are refused when the content does not match the file name, when the kind is switched off,
when the file is too big, or (replace only) when the extension differs from the item being replaced.
