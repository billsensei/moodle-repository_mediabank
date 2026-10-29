# Media bank content type (contenttype_mediabank)

Adds video, audio, PDF and image files to Moodle's Content bank, so a file can be uploaded once and used in any
course, either as a **link** (the course follows the bank item when it is replaced) or as an independent **copy**.
It works with Moodle's own file picker. For Moodle 5.0 to 5.3. Status: **Beta**.

What it provides: upload checks (the file's content must match its name), preview, a "where this item is used" panel,
protection against deleting an item that courses link to, an edit form (text alternative, transcript, captions), kept
earlier versions with restore, background preparation of MP4 videos for streaming (no ffmpeg needed), read-only web services, privacy (GDPR) support, and events.

Install the plugin zip from Site administration > Plugins > Install plugins, **before** `local_mediabank`.
Settings: Site administration > Plugins > Content bank > Media bank.

Guides (install, administrator, teacher) come with the release bundle, `mediabank-<release>-bundle.zip`.
Release notes: `CHANGES.md`.
