# Media bank: Frequently asked questions

Short answers, with a pointer to the guide that has the full story. For installing, see [INSTALL.md](INSTALL.md). For settings and
limits, see the [Administrator guide](ADMIN-GUIDE.md). For day-to-day use, see the [Teacher guide](TEACHER-GUIDE.md).

## The basics

Not sure what a word means? See the [Glossary](GLOSSARY.md).

### What is it?
Two Moodle plugins that put videos, audio files, PDFs and pictures in one place, Moodle's **Content bank**, so a file is uploaded
once and used in many courses. In a course, a teacher picks the file with Moodle's own file picker and chooses to **link** to it
or to take a **copy**.

### Which Moodle versions does it work with?
Moodle **5.0, 5.1, 5.2 and 5.3**, tested on PHP 8.3 and 8.4 with PostgreSQL and MariaDB. Moodle 4.5 and earlier are not supported.
The mobile app is not supported: the plugin has no mobile support.

### Is it ready for production?
It is **Beta**: released for testing. Try it on a copy of your site first, and make a backup before installing. It has had a lot of
testing (see "What has been tested" in the Administrator guide), but it has not had long use on real sites.

### Which plugin do I install first?
`contenttype_mediabank` (the content type), then `local_mediabank` (the tools). The second needs the first. See
[INSTALL.md](INSTALL.md).

### Do I need both?
The content type is the main plugin. The tools plugin is only needed for carrying linked files in course backups and for the
command-line tools. Without it, uploading, linking and copying all work.

## Using it

### What is the difference between "Link to the file" and "Make a copy of the file"?
A **link** points at the bank item: replace the item and every course that links to it gets the new file. A **copy** is the
course's own file and never changes. If in doubt, keep **Link**. See "Link or copy?" in the Teacher guide.

### I replaced a file but a course still shows the old one. Why?
That course took a **copy**, not a link. Open its activity, pick the item again, and choose **Link to the file**.

### Can I undo a replacement?
Yes. The old file is kept as an earlier version (30 days unless your administrator changed it). On the item's **Edit** page, use
**Restore an earlier version**. This changes it for every course that links to the item, and the file it replaces is kept, so you
can undo the undo. If the administrator set the retention to 0, nothing is kept.

### Why can't I delete an item?
Because courses link to it, and deleting it would take their file away. The item's page says so. You can leave it alone, or on the
**Edit** page tick **Convert all N link(s) to copies** (this cannot be undone, and later replacements no longer reach those
courses), and then delete it.

### Can I convert just my course's link into a copy?
There is no button for that. Pick the item again in your activity and choose **Make a copy of the file**. A manager can convert
all links from the item's Edit page.

### What happens to a link if the item's course or category is deleted?
Each course that linked to it keeps its file, as an ordinary copy that no longer follows replacements.

### Can my students see the Content bank?
No. By default only managers, course creators and editing teachers can use it. Students see the media inside your course, however
you added it.

### What does "unlisted" mean?
An unlisted item (**More → Make unlisted**) is hidden from other teachers. You can still see and use it, and so can managers.
**Make public** reverses it.

### Why was my file refused?
The Media bank reads what is *inside* a file as well as its name. Common reasons: the content is not the kind its name says (for
example a document renamed to `.png`), the file is empty, the kind is not allowed on your site, or the file is bigger than the
limit. The Teacher guide has a table of the messages. A very large upload that fails with no message is usually a server limit; see
below.

### Why did my replacement file get refused?
A replacement must be the same kind of file as the one it replaces: a `.pdf` can only be replaced by a `.pdf`. That keeps the links
working.

### Which file types are allowed?
Video: mp4, webm. Audio: mp3, m4a, ogg. PDF. Images: png, jpg, gif, webp, and svg only if your administrator has allowed it.
Default size limits are 1 GB for video, 200 MB for audio, 100 MB for PDF and 20 MB for images, and administrators can change them.

## Accessibility

### Do captions, alt text and transcripts go with the file into a course?
**No.** They are kept with the bank item. A course that links to or copies the file gets the bare file. They show on the item's
page in the Content bank. So put the transcript on the course page too, and make sure a video that needs captions has them in the
video itself.

### Is alt text required?
For images, yes: you cannot save an image without a text alternative. For video and audio you can add a transcript and a WebVTT
(`.vtt`) captions file on the item's **Edit** page.

### Does it check that my PDF is accessible?
No. Screen reader users need a **tagged** PDF, and the plugin cannot check that. Check the file before you use it.

## Video

### What does "prepare MP4 videos for streaming" do?
Most cameras and phones write an MP4 with its index at the end, so a browser must download the whole file, or ask for its end,
before it can play. After you upload or replace an MP4, a background task moves the index to the front, so playback can start
sooner. Nothing is re-encoded: picture and sound are untouched and the file keeps its size. It runs when cron runs, so it is not
instant.

### Does it shrink or convert my video?
No. It does not change the size, the codec or the resolution, and it does not make several quality levels. A video a browser cannot
play, for example in an unsupported codec, still will not play. It does not touch `.webm` or `.m4a` files.

### Does the server need ffmpeg?
No. The work is done in PHP, and nothing extra needs to be installed.

### I uploaded a video and it did not get prepared. Why?
Possible reasons: cron is not running; the setting "Prepare MP4 videos for streaming" is off; the index was already at the front; or
the file could not be changed this way (for example a fragmented MP4), in which case it is left as it was. Administrators can see
the task under **Site administration → Server → Tasks → Ad hoc tasks**.

### What about videos uploaded before this feature?
An administrator can queue them with `php local/mediabank/cli/optimize_videos.php`. Add `--dry-run` to only count them.

### Can the original be recovered?
Yes, for the retention time (30 days by default): the file as uploaded is kept as an earlier version.

## SVG

### Why can't I upload an SVG?
SVG is **off by default**. An SVG can contain scripts, so an administrator has to switch on **Allow SVG images** in the plugin
settings. Use PNG where you can.

### If SVG is on, why was mine refused?
The file is read as XML and refused unless it is only a picture: no scripts, animation, embedded pages, event handlers, or links that
load anything. Files with entity definitions, files over 2 MB, and files in encodings other than UTF-8, ASCII or common single-byte
ones are refused too. About 97% of ordinary SVG files from editors pass. Export it again as a plain SVG, or as a PNG.

### Is SVG safe?
The plugin shows an SVG only as a picture and only ever sends the file as a download with a sandbox policy, and it checks the file
against a list of what is allowed. But that check has been through several rounds of security review and had problems found and
fixed in each, and it has not had a manual review. A copy of an SVG in a course is served by Moodle itself. Switch SVG on only if
you need it. The Administrator guide's "SVG files" section has the details.

## Administrators

### Where are the settings? There is no Settings link on one of the Media bank rows.
The settings are under **Site administration → Plugins → Content bank → Media bank**. On **Plugins overview** there are two **Media bank** rows. The one of type *Content bank* has a **Settings** link to that page. The one of type *Local plugin* (the tools) has no link, because it has no settings of its own and Moodle does not show that link for local plugins. Nothing is wrong.

### A large upload fails with no clear message.
Almost always a server limit. PHP's `upload_max_filesize`, `post_max_size` and `max_execution_time`, your web server's body limit,
Moodle's maximum upload size settings, and, for the Content bank upload form, the **Private files space** limit (or give uploaders
the *Ignore user quota limit* permission). The smallest one wins. See "Large files" in the Administrator guide.

### What happens to links when I back up and restore a course?
By default a backup stores only a reference, so restoring on a **different site** leaves the file out. Switch on **Include linked
files in course backups** to carry the files; on restore they become ordinary copies where a link cannot be kept. Backups get
bigger. See "Backup and restore" in the Administrator guide.

### What happens when a user is erased (GDPR)?
Their bank items, earlier versions, captions, alt text and transcripts are erased. Courses that linked to their media **keep the file
as an ordinary copy**. If a particular recording shows a person who has asked for erasure, find the courses from the item's "Where
this item is used" panel and remove the file there yourself.

### What does uninstalling do?
**It deletes every item in the media bank**, with its versions, captions, alt text and transcripts. Course files are kept, because
each link is first turned into an ordinary copy. Make a full backup first, remove `local_mediabank` before `contenttype_mediabank`,
and note that Moodle cannot downgrade a plugin. See "Removing the plugin".

### Can I use it from another system?
There are three read-only web service functions (list items, get one item, and the courses that use it), not in the mobile app
service. See "Web services" in the Administrator guide.

### How do I upgrade?
Install the new zip the same way (content type first). Moodle offers to upgrade. See [UPGRADING.md](UPGRADING.md). Do not uninstall first, because that deletes the
items. A site that already had the plugin keeps its saved settings.

### Where do I find the release notes?
[CHANGELOG.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CHANGELOG.md) lists the project's releases. Each plugin also has a more detailed `CHANGES.md`.

## Getting help

### Something does not work.
See [TROUBLESHOOTING.md](TROUBLESHOOTING.md): it is organised by what you see, with the cause and the fix.

### I found a bug, or I have an idea.
See [SUPPORT.md](SUPPORT.md) for where to ask and what to include. Open an issue on GitHub and pick the bug report or feature request form. See [CONTRIBUTING.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CONTRIBUTING.md).

### I found a security problem.
Please do **not** use a public issue. Report it privately; see [SECURITY.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/SECURITY.md).
