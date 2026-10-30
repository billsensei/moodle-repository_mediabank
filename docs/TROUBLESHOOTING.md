# Media bank: Troubleshooting

Find what you see, then what usually causes it and what to do. Teachers: sections 2 to 6 and 8. Administrators: all of them. For
settings and limits see the [Administrator guide](ADMIN-GUIDE.md); for short answers see the [FAQ](FAQ.md).

**Before anything else,** note your Moodle version (bottom of **Site administration → Notifications**), the plugin versions
(**Plugins → Plugins overview**), and the exact message on the screen. If you report a problem, those three are what is asked for first.

Commands below are run from the Moodle folder on the server.

## 1. Installing and upgrading

| What you see | Cause and fix |
|---|---|
| Moodle says it is **not a valid plugin package**, or asks you to pick a **Plugin type** that you cannot choose | You uploaded the wrong file: the GitHub "Download ZIP" or "Source code" file, or the `-bundle.zip`. Neither is a plugin. From the release's **Assets**, upload `contenttype_mediabank_<version>.zip` first and `local_mediabank_<version>.zip` second. See "Which file do I download?" in [INSTALL.md](INSTALL.md). |
| "The ZIP package must contain just one directory" | The zip was unpacked and packed again. Use the original zip from the release. |
| "Plugin name mismatch" or "Plugin type mismatch" | The zip was re-made by another tool. Use the original zip. |
| A missing **dependency** when installing the tools plugin | Install `contenttype_mediabank` first, then `local_mediabank`. |
| The Install plugins screen says it cannot write to the plugin folder | Your host does not allow installing from the screen. Install by hand; see [INSTALL.md](INSTALL.md). |
| A **Beta** warning | Expected. It does not stop the installation. |
| The upgrade screen asks to upgrade after you copy files by hand | Expected. Open **Site administration → Notifications**, or run `php admin/cli/upgrade.php`. |
| After upgrading, old links hold an empty file or an older version | Run the refresh task once: `php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'`. It prints how many links it refreshed. |

## 2. I cannot see the Media bank or use the Content bank

| What you see | Cause and fix |
|---|---|
| No **Content bank** in the course menu | Look under **More**. If it is not there, you lack `moodle/contentbank:access` or `contenttype/mediabank:access` in that course. By default managers, course creators and editing teachers have both; students have neither. |
| **Media bank** is missing from **Plugins → Content bank → Manage content types** | The plugin is not installed, or its folder is in the wrong place (it must be in `contentbank/contenttype/mediabank`, or `public/contentbank/contenttype/mediabank` on Moodle 5.1 and later). |
| It is listed but switched off (the eye icon is closed) | Click the eye to switch it on. |
| No **Upload** button, or upload does nothing | You lack `contenttype/mediabank:upload`, or every kind is unticked under **Allowed file kinds** in the settings. |
| **Content bank** is not offered in the file picker of an activity | Enable the Content bank repository under **Plugins → Repositories → Manage repositories**, and check that you can use the Content bank in that course. |
| Another teacher's item is not in the list | It is **unlisted**. Unlisted items are visible only to their owner and to people with `moodle/contentbank:viewunlistedcontent` (managers and course creators by default). |
| Students cannot see the Content bank | By design. They see the media inside the course, however it was added. |

## 3. A file was refused on upload

The message names the file and the reason.

| Message | Cause and fix |
|---|---|
| "…its content is not the kind of file its name says" | The file is not what its name says, for example a document renamed to `.png`, or a damaged file. Export it again in the right format. |
| "…it is empty" | The file has no bytes. Check the original. |
| "…this kind of file is not allowed in the media bank" | The kind is switched off in the settings, or the extension is not supported. Supported: mp4, webm, mp3, m4a, ogg, pdf, png, jpg, gif, webp, and svg only if allowed. |
| "…it is larger than the limit of …" | Over the maximum for that kind (1 GB video, 200 MB audio, 100 MB PDF, 20 MB image, 2 MB SVG, unless changed). Compress it, or an administrator can raise the limit in the settings. |
| "…cannot replace this item. A replacement must be a .xxx file…" | A replacement must have the same extension as the item, so that links keep working. Upload the new file as a new item instead. |
| "…an SVG file may contain only a picture…" | The SVG has a script, animation, an embedded page, an event handler, a link that loads something, or is damaged. Export it as a plain SVG, or as a PNG. |
| "The caption file must be a WebVTT file: it starts with the line WEBVTT and is not larger than 1 MB." | Captions must be `.vtt` and begin with `WEBVTT`. |
| **Required** next to **Text alternative** | An image cannot be saved without one. Describe the picture. |

An iPhone video (a QuickTime-brand mp4) and an mp3 with a large cover-art tag are accepted. If a file you believe is fine is refused
as "not the kind of file its name says", try exporting it again from the original program.

## 4. A large upload fails, with no clear message

Almost always a server limit, because the plugin cannot raise any of these:

1. PHP: `upload_max_filesize`, `post_max_size`, `max_execution_time` and `max_input_time`.
2. The web server: nginx `client_max_body_size`, or Apache `LimitRequestBody`.
3. Moodle: **Security → Site security settings → Maximum uploaded file size**, and the course's **Maximum upload size**.
4. Moodle's **Private files space** (100 MB by default). The Content bank upload form limits each upload to it unless the uploader
   has `moodle/user:ignoreuserquota` ("Ignore user quota limit"). Raise it, or give that permission to people who upload videos.
5. The Media bank's own limit for the kind of file.

The smallest wins. To find which: try a file just under each limit, or look at the web server and PHP error logs for the request.

## 5. A course shows the wrong file

| What you see | Cause and fix |
|---|---|
| I replaced an item but a course still shows the old file | That course took a **copy**, not a link. Open its activity, pick the item again and choose **Link to the file**. |
| A link shows an empty file, or an older version | A link carries a copy of the item's content that Moodle refreshes when the link is read. Run the refresh: `php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'`. It also runs daily at 03:47. |
| A course's file stopped following replacements | Its link was converted to a copy: the item was moved, or deleted, its course or category was deleted, or someone ticked **Convert all links to copies**. This cannot be undone for that course; pick the item again as a link. |
| A course lost its file after a restore | See section 8. |

To see which courses use an item: its page shows **Where this item is used**, and the command line shows the same:

    php local/mediabank/cli/item.php --id=12 --usage

(The id is in the address of the item's page: `…/contentbank/view.php?id=12`.)

## 6. I cannot delete or replace an item

| What you see | Cause and fix |
|---|---|
| "This item cannot be deleted while courses link to it…" and no **Delete** | Courses link to it, and deleting it would take their file. Leave it, or convert the links to copies on the **Edit** page (**Convert all N link(s) to copies**, which cannot be undone), then delete. From the command line: `php local/mediabank/cli/item.php --id=12 --detach`. |
| Undoing a replacement | **Edit → Restore an earlier version**, or `php local/mediabank/cli/item.php --id=12 --restore=1` (`--versions` lists them). The file it replaces is kept, so the undo can be undone. |
| There is no earlier version to restore | The retention is 0, or the earlier versions expired (30 days by default), or the replacement was identical to the old file. |
| "There is no content bank item with id N." (command line) | The id does not exist, or is not a number from the Content bank. |
| "Content N is not a media bank item." (command line) | The id belongs to a different kind of Content bank item. |
| Disk space fills up after replacing large files | Each kept version uses its full size for the retention time. Lower **Keep replaced files for (days)**, or set it to 0 (nothing is kept, and a replacement cannot be undone). |

## 7. Video

| What you see | Cause and fix |
|---|---|
| A video takes a long time to start playing | An MP4 with its index at the end must be downloaded in full, or its end requested, first. It is prepared a few minutes after upload by a task that runs with cron; see below. Streaming preparation does not shrink or convert a video. |
| It has not been prepared | Check, in this order: cron is running; the setting **Prepare MP4 videos for streaming** is on; the task exists under **Site administration → Server → Tasks → Ad hoc tasks**; the file is an `.mp4` (`.webm` and `.m4a` are not touched). |
| Run the waiting tasks now | `php admin/cli/adhoc_task.php --execute`. For each video it prints `Media bank item N: optimized`, `nothing`, `gone` or `failed`. |
| It says `nothing` | The file was already streamable, or is not a plain MP4 that can be changed this way (for example a fragmented MP4). That is not an error. |
| It says `gone` | The item was replaced or deleted while the task ran. A replacement queues its own task. |
| It says `failed`, with "was left as it is: …" | The file has a layout that cannot be moved safely (for example a very large or damaged index). The video is untouched, and the file is not tried again until it is replaced. |
| The task keeps failing for disk or read reasons | Those are retried. The task needs temporary space in Moodle's temp folder for one extra copy of the video. |
| Videos uploaded before streaming preparation existed | `php local/mediabank/cli/optimize_videos.php --dry-run` counts them; without `--dry-run` it queues them. |
| I want the original back | The file as uploaded is kept as an earlier version for the retention time; restore it on the **Edit** page. |
| A video will not play in the browser | The plugin does not convert codecs. A codec the browser cannot play (for example HEVC in some browsers) stays unplayable; re-encode the video to H.264 and AAC. |
| The video plays but there are no captions | Captions are kept with the bank item. A course that links to or copies the file gets the bare file, so captions do not travel; build them into the video, or put a transcript on the course page. |

## 8. Backup and restore

| What you see | Cause and fix |
|---|---|
| After restoring a course on a **different site**, the file is missing | Moodle backs a link up as a reference only. Switch on **Include linked files in course backups** and take the backup again; restoring it then makes an ordinary copy. A backup made before the setting was on cannot be repaired. |
| The same setting is on but a link is still missing | Only **manual** course backups with files included carry the files. Import, course copy and automated backups are unchanged. Links in blocks and in section summaries are not carried. |
| Backups became large | By design: the linked files are in the backup. A course that links three 500 MB videos adds about 1.5 GB. Switch the setting off if that is too much. |
| Restored on the same site and the link became a copy | With the setting on: the item was deleted, or its file was gone, by restore time. (With the setting off, the file is dropped and a warning is logged.) |
| A restored copy has no alt text, transcript or captions | These belong to the bank item and do not travel in a copy. |

## 9. Privacy and erasure

| What you see | Cause and fix |
|---|---|
| A course has an empty file after a user was erased | Rare. It needs three things together: Moodle runs its own erasure before the plugin's (it does not today), the link was restored on the same site, and the erasure came before the next daily task. Run the refresh task after any restore: `php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'`. |
| A recording of an erased person is still in a course | By policy, courses keep their copies. Find the courses from **Where this item is used** and remove the file there. |

## 10. Web services

| What you see | Cause and fix |
|---|---|
| The functions are not listed when building a service | Enable web services and the REST protocol, then create an external service and add `contenttype_mediabank_get_items`, `_get_item` and `_get_usage`; tick **Can download files**. They are not in the mobile app service. |
| "There is no such media bank item that you can use." | The item does not exist, is of another kind, or the caller cannot see it. These all give the same message on purpose. |
| `get_usage` is refused | It is only for people who can manage the item. |
| A file address from `get_item` does not work | It is a web service address: add the user's token to it (`?token=…`), and the user needs access to the item. |

## 11. Removing the plugin

| What you see | Cause and fix |
|---|---|
| Moodle says the content type cannot be uninstalled | The tools plugin still depends on it. Uninstall `local_mediabank` first. |
| "Will be uninstalled / Can not be uninstalled" in the dry run | Run `php admin/cli/uninstall_plugins.php --plugins=local_mediabank --run`, then the dry run again, then the content type. |
| I want to go back to an earlier version of the plugin | Moodle cannot downgrade a plugin. Restore your site backup. |
| What is deleted | **Every item in the media bank**, with its versions, captions, alt text and transcripts. Course files stay, because each link is first turned into a copy. Make a full backup first. |

## 12. Still stuck: what to collect

1. The Moodle, PHP and database versions, and both plugin versions.
2. The exact message, and what you did, step by step, from a normal Moodle page.
3. For a task problem, the output of `php admin/cli/adhoc_task.php --execute` or of the scheduled task you ran.
4. For a file problem, the file type and size (not the file, if it is private) and which limits you checked in section 4.
5. A line from **Site administration → Reports → Logs** if the action was logged, and any error from the web server or PHP log.
   With **Site administration → Development → Debugging** set to *Developer*, Moodle shows more detail; switch it back afterwards.

Then open an issue on GitHub with the bug report form, as described in
[CONTRIBUTING.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CONTRIBUTING.md). **Do not use a public issue for a
security problem**; follow [SECURITY.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/SECURITY.md).
