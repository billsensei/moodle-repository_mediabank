# Media bank: Architecture

How the two plugins are built and why. This is for developers and for administrators who want to know what the plugin does inside
Moodle. For using it, see the [Teacher guide](TEACHER-GUIDE.md) and the [Administrator guide](ADMIN-GUIDE.md). For working on it,
see [CONTRIBUTING.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CONTRIBUTING.md).

## 1. The idea, and what it builds on

Upload a file once, use it in many courses, and when the file is corrected, correct it everywhere. Moodle already has most of the
parts:

- the **Content bank** (`core_contentbank`) stores items and has a plugin type for the kinds of item it holds;
- **file aliases** (file references) let a course hold a file that is a pointer to another file;
- the **file picker** has a Content bank repository that offers "Link to the file" or "Make a copy of the file".

So the Media bank adds no table and no new way of linking. It adds a **content type** for media, rules around it, and care for what
core does not do. The main design rule is to use core's mechanisms, and add checks and bookkeeping around them.

## 2. The two plugins

| Plugin | Type | Does | Depends on |
|---|---|---|---|
| `contenttype_mediabank` | Content bank content type | Everything about items: upload rules, preview, versions, captions, links, serving, streaming preparation, web services, privacy, events | Moodle 5.0 to 5.3 |
| `local_mediabank` | Local plugin | Carries linked files in course backups and restores them; command-line tools | `contenttype_mediabank` |

They are separate because course backup and restore are extended by a plugin of a type that core's backup supports, and `local` is
one; and because a site can use the content type without ever touching backups. The content type does not know the tools plugin
exists. The one setting they share, `embedinbackups`, belongs to the content type.

```
contenttype_mediabank/
  classes/
    contenttype.php       the content type: upload, replace, delete, move, preview, serving
    content.php           an item: metadata kept in the item's configdata
    upload_rules.php      what may be uploaded: kinds, sizes, content sniffing
    svg_validator.php     allow-list check of SVG files (only used if SVG is allowed)
    usage.php             where an item is linked from; converting links to copies; refreshing links
    versions.php          earlier files kept when an item is replaced
    captions.php          the WebVTT file of a video or audio item
    faststart.php         moves an MP4's index to the front (pure logic, no Moodle calls)
    video_optimizer.php   queues and runs streaming preparation for an item
    hook_callbacks.php    reacts to core's after_file_created hook
    form/editor.php       the Edit form (core's Edit button)
    external/             three read-only web service functions
    event/                item_linked, links_converted, version_restored
    task/                 purge_versions, sync_links (scheduled); optimize_video (ad hoc)
    privacy/provider.php  GDPR export and erasure
  db/                     access.php, hooks.php, services.php, tasks.php (no install.xml: no tables)
  lib.php                 serves captions and earlier versions
  settings.php            admin settings
  templates/preview.mustache
local_mediabank/
  classes/embedded_links.php   finds linked files, embeds them in a backup, restores them as copies
  backup/moodle2/              the backup and restore plugin classes that call it
  cli/item.php, cli/optimize_videos.php
```

## 3. How an item is stored

There is **no table of the plugin's own**. An item is a row in core's `contentbank_content` with `contenttype =
'contenttype_mediabank'`, plus files.

| What | Where |
|---|---|
| The item | `contentbank_content` row (name, context, visibility, creator, times) |
| The file | file area `contentbank` / `public`, item id = the content id |
| Facts about the item | JSON in the row's `configdata`: `kind`, `width`, `height` (images), `alt` (text alternative), `transcript`, and `streamfailed` (see section 9) |
| Captions | file area `contenttype_mediabank` / `captions`, one `captions.vtt` per item |
| Earlier versions | file area `contenttype_mediabank` / `versions`, one per replaced file, in the folder `/N/` (N = version number) |
| Temporary copy while a video is prepared | file area `contenttype_mediabank` / `optimizing`, removed afterwards |
| Settings | `config_plugins` for `contenttype_mediabank` (section 12) |

Keeping facts in `configdata` means an item moves with core's own tools (copy, move, delete) without a schema to migrate.

## 4. A link is a file alias

When a teacher chooses **Link to the file**, core creates, in the activity's file area, a file whose `referencefileid` points at the
bank item's file through the Content bank repository. That is all a link is. The course module stores nothing about the Media bank.

Two consequences shape much of the code:

1. **A link carries a copy of the item's content hash and size.** Moodle refreshes them when the link is read. A link that nobody has
   opened can hold an empty placeholder, and Moodle keeps whatever a link holds when its source disappears. So the plugin refreshes
   links itself (`usage::sync_links`): when a link is created (section 5.2), before an erasure, and in a daily task.
2. **A link points at the item's current context.** Moving an item to another context would break every link, so a move first turns the
   links into copies (section 5.4).

`usage.php` finds links with SQL on the repository reference, ignores aliases in users' **draft** areas (they exist only while a form
is being edited), and does the conversion to copies (`detach_all`).

## 5. The main flows

### 5.1 Upload

```
Upload form (core) -> contenttype::upload_content
   -> upload_rules::validate   extension allowed? same kind? not empty? under the size limit?
                               does the content match the name?  (SVG: svg_validator)
   -> core stores the item and its file
   -> merge_metadata(kind, size)
   -> video_optimizer::queue   if it is an MP4: ad hoc task (section 9)
```

The check of content (`finfo` on the first 8 KB, skipping an MP3's ID3 tag) is the reason a document renamed to `.png` is refused.
Moodle decides a file's type from its name, which anyone can fake.

### 5.2 Link or copy

The file picker, the Content bank repository and the alias are all core. The plugin only reacts afterwards. `db/hooks.php` registers
`hook_callbacks::after_file_created` for core's `after_file_created` hook. It returns at once unless the new file is an alias to a
media bank item and is not in a draft area. If it is, it triggers the `item_linked` event and refreshes the link's content. A copy is
an ordinary file, so nothing of the plugin is involved.

### 5.3 Replace

```
contenttype::replace_content
   -> validate, and the new file must have the same extension as the old   (links keep working)
   -> versions::keep_current   keep the old file as version N if retention > 0 and the content differs
   -> core replaces the file in place
   -> merge_metadata, queue streaming preparation
```

The file is replaced in place, so every alias sees the new content the next time it is read.

### 5.4 Delete and move

`is_delete_allowed` refuses to delete an item that courses link to. Bulk deletion (a course or category being deleted) and move call
`usage::detach_all` first, which turns each link into an independent copy, so courses keep what they use. Versions and captions are
deleted or moved with the item.

### 5.5 Serving files

| File | Served by | Rule |
|---|---|---|
| The item's file | `contenttype::pluginfile` | `can_serve`: the item exists, belongs to this plugin and to the context in the URL, the user can access the media bank there and may view the item (unlisted items are hidden from people without that right). A refusal sends `send_file_not_found()`, **not** `false`, because core serves a download when the callback returns false |
| The item's file, if its type is on the inline list and its content matches | `send_stored_file` inline | `nosniff` header |
| Everything else | core's forced download | |
| An SVG | `send_stored_file` as a download | `Content-Security-Policy: sandbox; default-src 'none'`, `nosniff` |
| Captions and earlier versions | `lib.php` | same access rule; versions only for people who can manage the item, always a download |

Core only checks that the user is logged in. The access check above is the plugin's, and was added after a review found that any
logged-in user who knew an address could fetch any item.

### 5.6 Where the preview comes from

`get_view_content` renders `preview.mustache`: a `video`, `audio`, `iframe` (PDF) or `img` tag chosen from the file's type, then
captions, transcript, and the "where this item is used" panel (`usage::get_courses`; courses the viewer cannot see are counted, not
named). An SVG gets an `img` tag, which is why scripts in it cannot run on the page.

## 6. Versions and captions

Versions are plain files in their own file area with no database row, which is why an erased user's versions need the privacy
provider to find them by item (section 11). `purge_versions` (daily, 03:17) deletes versions older than the retention time, versions
of items that no longer exist, and orphaned caption files. A version keeps no user, author or source.

## 7. Backup and restore (`local_mediabank`)

By default core backs a link up as a reference only, so a restore on **another site** leaves no file. With the setting "Include linked
files in course backups":

- `backup_local_mediabank_plugin` (course and module level) calls `embedded_links::embed_in_backup`: for each link to a media bank item
  in that context it copies the file's bytes into the backup's file pool. Only manual course backups with files included apply
  (`applies_to_backup`); import, course copy and automated backups are unchanged.
- `restore_local_mediabank_plugin` remembers what was embedded (`remember`) and, after core's own restore, calls
  `restore_copies`. Core's queue of aliases to restore is checked: a link that core can restore on this site stays a link; any other
  becomes an **ordinary copy** from the embedded bytes.

A backup file is not trusted. A content hash from a backup must be 40 hex characters, the file must be inside the backup's file pool
(`pool_path` resolves and checks the real path), and its bytes must hash to its name. This was added after a security review found
that a crafted backup could name a path outside the pool.

What is not carried: links in blocks and section summaries, and a copy does not keep the item's alt text, transcript or captions.

## 8. Web services

Three read-only functions in `db/services.php`, implemented under `classes/external/`: `get_items`, `get_item`, `get_usage`. They
share `item.php` for the access rule and the shape of an item. Every function checks access to the media bank in the item's context,
which core's own content bank search does not. A missing item, an item of another kind and an item the caller may not see give the
same error, so item ids cannot be probed. They are not added to any service by default.

## 9. Streaming preparation

```
upload or replace of an .mp4 ──> video_optimizer::queue ──> ad hoc task optimize_video
                                                             │
        video_optimizer::optimize(contentid)  <──────────────┘
          1  faststart::needs_optimizing   (is the index after the data? supported layout?)
          2  faststart::rewrite            (to a temp file: index moved to the front, chunk offsets corrected)
          3  check the result              (same size, index first, same index length)
          4  put it in a work area, confirm the item still has the file we started from
          5  contenttype::replace_content  (keeps the original as a version; links follow)
          6  restore the item's last editor, refresh links
```

`faststart` is pure PHP with no Moodle calls, so it is tested with hand-built files and, where ffmpeg exists, a real video. It reads
the top-level boxes of the file, loads the index (`moov`), adds the index's length to every chunk offset (`stco`, `co64`) that points
between the new and the old position, and writes the pieces in the new order. Nothing is decoded. It refuses anything unusual:
fragmented files, a compressed index, malformed boxes, an index over 32 MB, more than 4 million offsets, nesting deeper than a real
index, or a 32-bit offset that would overflow. A file that fails is remembered by its content hash (`streamfailed`) and not tried
again until it is replaced; disk and read errors are not remembered, so they are retried.

## 10. SVG

SVG is off by default (`allowsvg`) because an SVG can contain scripts. When on:

- **Shown** only through an `img` tag, where scripts do not run.
- **Sent** only as a download with a sandbox security policy.
- **Checked** on upload and replace by `svg_validator`: the file is parsed as XML (no network, no entity expansion) and refused unless
  every element and attribute is on a list of what is allowed. It refuses scripts and animation however they are prefixed, `on...`
  attributes, links other than `#id`, anchors to web or mail addresses and small embedded pictures, any `url()` that is not exactly
  `#id` or an embedded picture, CSS with a backslash or loader functions, processing instructions, entity definitions, other
  encodings than ASCII-compatible ones, and files over 2 MB. Every regular expression that refuses on a match counts an engine
  failure as a match. The check is a second layer: a copy of an SVG in a course is served by Moodle itself.

The Administrator guide says plainly that this check has been through several rounds of security review and has not had a manual one.

## 11. Privacy and events

**Privacy.** `contenttype_mediabank\privacy\provider` declares and exports what belongs to the person who made an item: alt text,
transcripts, captions and earlier versions, under the plugin's own folder so core's export of the item is not overwritten. Erasure
deletes the person's items' extra files, and the files whose item row core already deleted. **Policy: courses keep their copies.**
Before an erasure the provider refreshes the links to the items about to go, so links that nobody had opened still hold the file.
Core's own provider deletes the item itself without calling the content type, which is why the refresh is needed. `local_mediabank`
stores nothing about people and declares itself a `null_provider`.

**Events.** Core reports upload, replace, rename, visibility, delete, move and view for every content bank item. The plugin adds
what core does not: `item_linked`, `links_converted` (with the count) and `version_restored`.

## 12. Settings, tasks and capabilities

| Setting | Default | Used by |
|---|---|---|
| `allowedkinds` | video, audio, pdf, image | `upload_rules` |
| `allowsvg` | off | `upload_rules`, `svg_validator` |
| `maxbytes_video`, `_audio`, `_pdf`, `_image` | 1 GB, 200 MB, 100 MB, 20 MB | `upload_rules` |
| `optimizevideo` | on | `video_optimizer` |
| `embedinbackups` | off | `local_mediabank` |
| `versionretentiondays` | 30 | `versions` |

| Task | When | Does |
|---|---|---|
| `purge_versions` | daily 03:17 | deletes expired versions and orphans |
| `sync_links` | daily 03:47 | refreshes every link's content |
| `optimize_video` | ad hoc | prepares one MP4 for streaming |

Capabilities: `contenttype/mediabank:access`, `:upload` and `:useeditor`, by default for managers, course creators and editing
teachers. Both these and Moodle's own `moodle/contentbank:*` capabilities must allow an action.

## 13. Decisions and trade-offs

| Decision | Why | Cost |
|---|---|---|
| No tables; facts in `configdata`, files in file areas | Moves and copies with core's own tools; nothing to migrate | No SQL queries on metadata |
| A link is a core file alias | Core's picker, backup and permissions already understand it | The plugin must refresh links and convert them before moves and deletes |
| Replacement must keep the extension | Core keeps the old file name, so a different extension would leave bytes under the wrong name | A `.mov` cannot replace an `.mp4` |
| Deleting or moving converts links to copies | Courses keep what they use | The item is then no longer followed by those courses |
| Erasure keeps courses' copies | Decided with the owner of the project | A recording of an erased person stays in courses until someone removes it |
| Streaming preparation in PHP, not ffmpeg | Nothing to install; no untrusted upload handed to another program | The byte moving is our own code, so it is tested hard and refuses anything unusual |
| Streaming preparation replaces the file | Links follow with no extra mechanism, and the original is kept as a version | The version takes disk space for the retention time |
| Refuse access with "file not found" | Core serves a download when a callback returns false | None |
| SVG off by default, shown only as an image | Scripts | An administrator must opt in |
| Backups are not trusted | A backup file can be crafted | Extra checks on restore |

## 14. Known limits

Alt text, transcripts and captions stay with the item and do not travel into a course. Only the **File** resource has been tested
for the Link and Copy choice. A teacher cannot convert only their own course's link. Links in blocks and section summaries are not
carried in backups. The plugin has no mobile support. The Administrator guide lists what has and has not been tested.
