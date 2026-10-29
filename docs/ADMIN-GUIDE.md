# Media bank: Administrator guide

This guide is for the people who look after the Moodle site. Teachers should read the Teacher guide. To install, see `INSTALL.md`.

**Contents**
1. What it does
2. Settings
3. Who can do what
4. Large files
5. Scheduled tasks
6. Backup and restore
7. Privacy and erasure (GDPR)
8. Web services
9. Logs and events
10. The command-line tool
11. Removing the plugin
12. What has been tested, and what has not

---

## 1. What it does

The Media bank adds a **content type** to Moodle's Content bank for videos, audio, PDFs and images. A teacher uploads a file once. Any course can then use it in two ways:

| | **Link** (the default) | **Copy** |
|---|---|---|
| What the course holds | A pointer to the bank item | Its own independent file |
| When the item is replaced | Every course gets the new file | Nothing changes |
| Can the item be deleted? | **No**, while any course links to it | Yes. Copies are not affected |

Moodle's own file picker offers the choice: **Link to the file** or **Make a copy of the file**. Nothing in Moodle's core is changed.

Behind it:
- **Media bank content type** (`contenttype_mediabank`): upload, checks, preview, "where used" panel, edit form, versions, web services, privacy, events.
- **Media bank tools** (`local_mediabank`): backup and restore of links, and a command-line tool.

## 2. Settings

**Site administration → Plugins → Content bank → Media bank**

| Setting | Default | What it does |
|---|---|---|
| **Allowed file kinds** | All four | Which kinds can be uploaded: Video (mp4, webm), Audio (mp3, m4a, ogg), PDF documents, Images (png, jpg, gif, webp). Items already uploaded stay available if you untick a kind. |
| **Allow SVG images** | Off | SVG files can contain scripts. If you allow them they are stored, but always downloaded and never shown in the page. |
| **Maximum video size** | 1 GB | Largest video accepted. |
| **Maximum audio size** | 200 MB | |
| **Maximum PDF size** | 100 MB | |
| **Maximum image size** | 20 MB | |
| **Keep replaced files for (days)** | 30 | When an item is replaced, the old file is kept this long so the replacement can be undone. **0 keeps nothing.** |
| **Prepare MP4 videos for streaming** | On | After an MP4 is uploaded or replaced, cron moves the file's index to the front so playback can start before the whole file has downloaded. See "Streaming" below. |
| **Include linked files in course backups** | Off | See section 6. |

**Streaming.** Most cameras, phones and editors write an MP4 with its index (the "moov" box) at the *end*. A browser then has to download the whole file, or ask for its end first, before it can start playing; on a slow connection the student sees a long wait. With this setting on, each new or replaced MP4 gets an ad hoc task ("Prepare a media bank video for streaming") that moves the index to the front.

- It does not re-encode anything. Picture and sound are untouched, the file stays exactly the same size, and **nothing needs to be installed on the server** (no ffmpeg). What it does *not* do: shrink files, change codecs or resolution, or make several quality levels. A video that a browser cannot play (for example HEVC in some browsers) is still one it cannot play.
- It runs when cron runs, so a new video is prepared within a few minutes, not instantly. Until then it is served as uploaded.
- The file as uploaded is kept as an earlier version for the retention time (see below), so it can be put back. **If you set the retention to 0, the original is not kept.** Links from courses follow the new file, the same as for any replacement.
- Only MP4 files whose index is last are changed. A file that is already streamable, is fragmented, has a compressed index, or does not look like a normal MP4, is left as it is. A failure is written to the task log and never loses or damages the video: the new file is checked before it replaces anything.
- The task needs temporary disk space for one extra copy of the video, in Moodle's temp folder.
- To prepare videos uploaded *before* this version, run this once. It queues the ones that need it, and cron does the rest:

      php local/mediabank/cli/optimize_videos.php --dry-run    # only count them
      php local/mediabank/cli/optimize_videos.php

**Disk space and versions.** Every kept version takes its full size on disk until it expires. Replacing a 500 MB video holds 500 MB for 30 days. Lower the number of days, or set 0, if disk space is tight.

**What the upload checks do.** Moodle works out a file's type from its *name*, which anyone can fake. The Media bank also reads the start of the file and refuses it if the contents do not match the name. A renamed file is refused with: *"The file … was not accepted because its content is not the kind of file its name says."* Other messages:

| Message | Meaning |
|---|---|
| "…was not accepted because it is empty." | Zero bytes. |
| "…was not accepted because this kind of file is not allowed in the media bank." | The kind is switched off, or the extension is not supported. |
| "…was not accepted because it is larger than the limit of …" | Over the maximum for that kind. |
| "…cannot replace this item. A replacement must be a .xxx file, so that existing links keep working." | A replacement must keep the same file extension. |

iPhone video (QuickTime brand mp4) and mp3 files with large cover-art tags are accepted.

## 3. Who can do what

Two sets of permissions both have to allow an action: Moodle's Content bank permissions and the plugin's own. By default:

| Permission | Manager | Course creator | Editing teacher | Student |
|---|---|---|---|---|
| Use the Content bank (`moodle/contentbank:access`, `contenttype/mediabank:access`) | Yes | Yes | Yes | No |
| Upload (`…:upload`) | Yes | Yes | Yes | No |
| Edit alt text, transcript, captions (`…:useeditor`) | Yes | Yes | Yes | No |
| Manage **any** item (`moodle/contentbank:manageanycontent`) | Yes | Yes | No | No |
| Manage **own** items (`moodle/contentbank:manageowncontent`) | Yes | Yes | Yes | No |
| See other people's **unlisted** items (`moodle/contentbank:viewunlistedcontent`) | Yes | Yes | No | No |

- Students never need these. They see media through the course, whichever way it was added.
- An item's file is only given to people who may see the item. Another teacher cannot fetch your unlisted item by guessing its address.
- Items can live in the site, a category or a course.

## 4. Large files

The plugin allows large files, but **Moodle and your web server each have their own limits, and the plugin cannot raise them.** For a 500 MB video, check all of these:

| Where | Setting | Note |
|---|---|---|
| PHP | `upload_max_filesize` and `post_max_size` | Both must be above the file size. |
| PHP | `max_execution_time` (and `max_input_time`) | Long enough for the upload to finish. |
| Web server | nginx `client_max_body_size`, or Apache `LimitRequestBody` | |
| Moodle | **Security → Site security settings → Maximum uploaded file size** | |
| Moodle | **Courses → Default settings → Course default settings → Maximum upload size** | Each course can override it. |
| Moodle | **Security → Site security settings → Private files space** (default 100 MB) | Moodle's Content bank upload form limits each upload to this space, unless the uploader has the **Ignore user quota limit** permission (`moodle/user:ignoreuserquota`). Raise it, or give that permission to the people who upload videos. |
| Media bank | The per-kind maximums in section 2 | |

The smallest of these wins. A large upload that fails with no clear message almost always means one of the server limits.

**Antivirus.** Moodle's configured antivirus scanners run on the normal upload path. Scanning a 500 MB file has **not been measured**, so test it before you promise teachers anything.

## 5. Scheduled tasks

**Site administration → Server → Tasks → Scheduled tasks**

| Task | When | What it does |
|---|---|---|
| **Delete expired media bank versions** | Daily, 03:17 | Deletes replaced files older than the retention time, and the leftovers of items that were erased. |
| **Refresh the content of media bank links** | Daily, 03:47 | Makes sure every link carries the item's current file (see section 7). |

There is also an ad hoc task, **Prepare a media bank video for streaming**, which is queued for each uploaded or replaced MP4 (see "Streaming" in section 2). Watch it at **Site administration → Server → Tasks → Ad hoc tasks**, or run the queue by hand with `php admin/cli/adhoc_task.php --execute`. The output says `Media bank item N: optimized`, `nothing` (already streamable or not an MP4), `gone` (the item was replaced or deleted meanwhile) or `failed`.

**After upgrading from an earlier version, run the second task once by hand**, so older links are fixed now and not at 03:47:

    php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'

It prints `Refreshed N media bank link(s) that were out of date.` and then `Scheduled task complete: Refresh the content of media bank links`. Running it again says 0. The other task is run the same way with `\contenttype_mediabank\task\purge_versions`; it prints how many versions and caption files it deleted.

## 6. Backup and restore

By default, a course backup stores a link to a bank file as a **reference only**, without the file. This is how Moodle works, and it matters when you move a course:

| Restore | What happens |
|---|---|
| Same site, the bank item still exists | The link comes back as a link. |
| Same site, the bank item has been deleted | The file is dropped and a warning is logged. |
| **A different site** | The course is restored with **no file at all**. |

(These three results were measured with real backups.)

To avoid the last case, switch on **Include linked files in course backups** (section 2). Then:

- Manual course backups also contain the files behind each link, in the course's own files and in its activities.
- On restore, a link that can still be a link stays a link. Any other becomes an **ordinary copy** with the right content, including links nobody had opened.
- **Import, course copy and automated backups are not changed.**
- **Backups get bigger.** A course that links three 500 MB videos adds about 1.5 GB.
- The setting is **site-wide**. Moodle gives plugins no way to add a tick box to the backup screen, so it cannot be chosen per backup.

What is not carried: links inside blocks and section summaries; and a copy loses the item's alt text, transcript and captions (they belong to the bank item).

## 7. Privacy and erasure (GDPR)

**Policy (decided 2026-10-29): when a person's data is erased, courses that link to their media keep the file as an ordinary copy.**

| Erased with the person | Kept |
|---|---|
| Their bank items, earlier versions, captions, alt text and transcripts | The file inside each course that used the media |

- Data export includes the person's alt text, transcripts, captions and earlier versions, under the Media bank's own folder.
- The courses' copies are treated as course content. If a particular recording shows a person who has asked for erasure, find the courses from the item's **Where this item is used** panel (or the command-line tool) and remove the file there yourself.
- To keep courses safe, every link is given the item's content when it is created, again just before an erasure, and by the daily task above.
- **One narrow case can still leave a course with an empty file.** It needs three things together: Moodle runs its own erasure before the plugin's (it does not today), the link was restored on the same site, and the erasure happens before the next daily task. Running the task after any restore closes it.

## 8. Web services

Three read-only functions, for other systems that need to list or fetch media. They are **not** in the Moodle mobile app service.

| Function | Use |
|---|---|
| `contenttype_mediabank_get_items` | List items in a site, category or course context. Filter by kind and name, paged. |
| `contenttype_mediabank_get_item` | One item, with the web-service addresses of its file and captions, alt text and transcript. |
| `contenttype_mediabank_get_usage` | Courses that link to an item. Only for people who can manage it. |

To use them:
1. **Site administration → Advanced features → Enable web services**, then **Server → Web services → Manage protocols** and enable REST.
2. **Server → Web services → External services**: create a service, tick *Can download files*, and add the three functions.
3. Give the user a role with `webservice/rest:use`, and create a token for them.

Call, for example:

    GET <site>/webservice/rest/server.php?wstoken=TOKEN&wsfunction=contenttype_mediabank_get_items&moodlewsrestformat=json&contextid=NN

The `fileurl` and `captionsurl` in the result are addresses on `webservice/pluginfile.php`. Add `?token=TOKEN` to download. A user must have the Media bank permissions in that context; anyone else gets *"There is no such media bank item that you can use."*, which is also what a missing item gives. To upload, rename, copy, hide or delete, use Moodle's own `core_contentbank_*` functions: deleting a linked item is refused there too.

## 9. Logs and events

**Site administration → Reports → Logs.** Moodle already logs content bank items being created, updated, viewed and deleted, and that includes media bank items. The plugin adds three:

| Event | When |
|---|---|
| **Media bank item linked** | A link to an item is saved, for example a teacher saves a File resource. It records where the link is. |
| **Media bank links converted to copies** | Links were turned into copies: from the edit form, or automatically when an item is moved or deleted. Records how many. |
| **Media bank version restored** | An earlier file was put back. Records the version number. |

Links made by a restore or a course copy are not reported (Moodle gives no way to see them). The scheduled tasks write their counts to the task log.

## 10. The command-line tool

For one item, from the Moodle folder:

    php local/mediabank/cli/item.php --id=12 [--usage] [--versions] [--detach] [--restore=N]

Find the id in the address of the item's page in the Content bank (`…/contentbank/view.php?id=12`). Real output from a test site:

    $ php local/mediabank/cli/item.php --id=12 --usage --versions
    Item 12: Welcome handout.pdf
      course 26 (docsample): 1 link(s)
      version 1: Thursday, 29 October 2026, 2:02 AM, 193 bytes

    $ php local/mediabank/cli/item.php --id=12 --detach
    Item 12: Welcome handout.pdf
    Converted 1 link(s) to copies.

    $ php local/mediabank/cli/item.php --id=12 --restore=1
    Item 12: Welcome handout.pdf
    Restored version 1.

    $ php local/mediabank/cli/item.php --id=999999
    There is no content bank item with id 999999.

To queue the videos uploaded before streaming preparation existed (section 2):

    $ php local/mediabank/cli/optimize_videos.php --dry-run
    3 video(s) would be queued.

Run with no arguments to see the options. `--detach` makes every link an independent copy; the item can then be deleted. `--restore` puts a version back, and the file it replaces is kept as a new version.

## 11. Removing the plugin

**Read this first. Uninstalling deletes every item in the media bank**, with its versions, captions, alt text and transcripts. Course files are kept: Moodle asks the plugin to delete each item, and the plugin first turns every link into an ordinary copy. (Tested.) Uninstall does not offer a way to bring items back. **Make a full site backup (database and `moodledata`) first.**

Order matters: the tools plugin depends on the content type, so remove the tools plugin first.

**From the screen:** **Site administration → Plugins → Plugins overview**, find **Media bank** (type *Local plugin*) and click **Uninstall**; then do the same for **Media bank** (type *Content bank*). Moodle warns how many items will be deleted.

**From the command line**, as a dry run first (nothing is changed without `--run`):

    $ php admin/cli/uninstall_plugins.php --plugins=local_mediabank,contenttype_mediabank
    Will be uninstalled: local_mediabank	Media bank
    Can not be uninstalled: contenttype_mediabank	Media bank

The second line is Moodle saying the content type cannot go while the tools plugin still needs it. So:

    php admin/cli/uninstall_plugins.php --plugins=local_mediabank --run
    php admin/cli/uninstall_plugins.php --plugins=contenttype_mediabank --run

Run the dry run again between the two; it should now list the content type as able to be uninstalled. If it still says it cannot, something else on your site depends on it.

Finally delete the plugin folders from the server (Moodle tells you which). Moodle cannot downgrade a plugin: to go back to an *earlier version* of the Media bank, restore your site backup.

## 12. What has been tested, and what has not

**Tested** (PHP 8.4, MariaDB), on **Moodle 5.0.11, 5.1.8, 5.2.4 and 5.3**: a fresh install; 104 automated tests of the content type and 7 of backup and restore; Moodle's own privacy and event checks; and 7 browser scenarios (upload, refused files, link, copy, alt text, transcript). All pass on all four versions. The 7 browser scenarios were last run before streaming preparation was added; the automated tests were re-run on all four after it. The plugin zips pass Moodle's own installer validation.

**Not tested:**
- PHP 8.3 (not available on the test machine). PostgreSQL (only MariaDB was available).
- The Moodle mobile app. The plugin has no mobile support.
- Antivirus scanning of very large files; files near the 1 GB limit through a real web server.
- Site-level items in the browser: the browser scenarios used items in a course's content bank; items in the site context were covered by the automated tests only.
- Activities other than the **File** resource for the Link and Copy choice. Other activities that use Moodle's standard file picker offer the same choice, but only the File resource was exercised.
- Linting (code style) on Moodle 5.0 to 5.2.

**Known limits:**
- Both plugins are marked **Beta**: released for testing. Try them on a copy of your site first.
- Alt text, transcripts and captions live with the bank item. A course that **links** or **copies** the file gets the bare file. They show on the item's page in the Content bank.
- There is no button for a teacher to convert just *their* course's link into a copy. They can pick the item again and choose **Make a copy of the file**. A manager can convert all links from the item's Edit page.
- A PDF cannot be checked for accessibility tags by the plugin.

Release notes for every version are in `CHANGES-contenttype_mediabank.md` and `CHANGES-local_mediabank.md`.
