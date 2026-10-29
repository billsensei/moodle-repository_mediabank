# Media bank: Glossary

Plain-language meanings of the words used in the Media bank and its guides, in alphabetical order. Words in *italics* have their own entry. For the full story see the [Teacher guide](TEACHER-GUIDE.md), the [Administrator guide](ADMIN-GUIDE.md) and the [FAQ](FAQ.md).

## A

**Ad hoc task.** A job Moodle runs once, in the background, when *cron* next runs. The Media bank uses one to prepare each uploaded MP4 for *streaming*. Administrators can watch the queue under **Site administration → Server → Tasks → Ad hoc tasks**.

**Alt text.** See *text alternative*.

**Alias.** See *link*.

**Allow-list.** A list of what is permitted, with everything else refused. The *SVG* check works this way: only listed drawing elements and attributes are accepted.

## B

**Bank.** Short for the *Content bank*, or for the Media bank's part of it.

**Beta.** The maturity level of both plugins: released for testing. Try them on a copy of your site first. Moodle shows `[Warning] Declared maturity level [MATURITY_BETA]` when you install them. It is not an error.

**Bundle.** The zip on each GitHub release that holds the two plugin zips, the guides, the walk-through PDFs, the screenshots and the change logs. It is **not** installable. Install the two plugin zips instead.

## C

**Captions.** The spoken words of a video or audio file as timed text, in a **WebVTT** (`.vtt`) file. You add them on the *item's* Edit page. They stay with the item. A course that links to or copies the file does not get them.

**Content bank.** Moodle's own place for keeping reusable content. It exists at site, category and course level. The Media bank adds a *content type* to it.

**Content type.** A kind of content the Content bank can hold, such as H5P. The Media bank is one. Its plugin is `contenttype_mediabank`.

**Copy.** What a course gets when you choose **Make a copy of the file**: its own separate file. It never changes, even if the *item* is replaced. Compare *link*.

**Cron.** Moodle's scheduler. It runs the *scheduled tasks* and *ad hoc tasks*. If cron is not running, videos are not prepared for *streaming* and old versions are not deleted.

## E

**Embed in backups.** The setting **Include linked files in course backups** (`embedinbackups`). Off by default. When on, manual course backups carry the linked files, which are restored as ordinary *copies* where a *link* cannot be kept. Backups get bigger.

**Erasure.** Removing a person's data when they ask (GDPR). Their *items*, earlier *versions*, *captions*, *text alternatives* and *transcripts* are erased. Courses that linked to their media keep the file as a *copy*.

**Event.** A record Moodle keeps of something that happened, such as a link being saved or a version restored. It shows in the site's logs.

## F

**Fast start.** Another name for preparing an MP4 for *streaming*.

**File picker.** The window Moodle opens when you add a file to an activity or form. The Media bank appears in it as the **Content bank** *repository*.

**File resource.** The Moodle activity, **File**, that puts one file on a course page. It is the one tested with link-or-copy.

## G

**GDPR.** The European data protection law. In Moodle it means the privacy tools for exporting and erasing a person's data. See *erasure*.

## I

**Item.** One file in the Media bank, with its *text alternative*, *transcript*, *captions* and earlier *versions*. In the Content bank an item has its own page, with **Edit**, **More** and **Exit** buttons.

## K

**Kind.** The type of file an *item* is: video, audio, PDF or image. The administrator chooses which kinds are allowed and the size limit of each.

## L

**Link.** What a course gets when you choose **Link to the file**: a pointer to the *item*. Replace the item and the course gets the new file. Moodle calls this a *file alias* or *reference*. Compare *copy*.

**Local plugin.** A general-purpose Moodle plugin. `local_mediabank` is the Media bank's second plugin ("the tools"). It adds the backup support and the command-line tools. It needs `contenttype_mediabank`.

## M

**Maturity.** See *Beta*.

**MIME type.** The label that says what kind of file something is, for example `image/png`. The Media bank reads what is *inside* a file as well as its name, so a document renamed to `.png` is refused.

**Moov.** The index of an MP4 file. When it is at the end of the file, a browser must fetch the whole file or its end before playing. *Streaming* preparation moves it to the front.

## P

**Plugin.** An add-on for Moodle. The Media bank is two: `contenttype_mediabank` and `local_mediabank`.

**Privacy provider.** The part of a plugin that tells Moodle what personal data it keeps and how to export or erase it.

**Public.** An *item* that other teachers can see and use. The opposite of *unlisted*. **Make public** reverses **Make unlisted**.

## R

**Reference.** See *link*.

**Replace.** **More → Replace with file** on an *item*. The new file must be the same *kind*. Courses that *link* get the new file. The old file is kept as a *version*.

**Repository.** A source of files in the *file picker*, such as **Upload a file** or **Content bank**. Teachers need the **Content bank** repository enabled to pick media inside a course.

**Retention.** How long a replaced file is kept as an earlier *version*. The setting **Keep replaced files for (days)** is 30 by default. 0 keeps nothing.

## S

**Scheduled task.** A job Moodle runs at set times through *cron*. The Media bank has two: **Delete expired media bank versions** (daily, 03:17) and **Refresh the content of media bank links** (daily, 03:47).

**Streaming.** Playing a video while it is still downloading. After an MP4 is uploaded or replaced, a background task moves its index to the front so playback can start sooner. Nothing is re-encoded and no ffmpeg is needed.

**SVG.** A picture file made of text, which can also carry scripts. It is **off by default**. When an administrator allows it, it is shown only as a picture, sent only as a download with a sandbox policy, and checked against an *allow-list* on upload.

## T

**Tagged PDF.** A PDF with the structure that screen readers need. The Media bank cannot check it for you.

**Text alternative.** A short description of a picture for people who cannot see it. Required for every image. It stays with the *item*, not with a course.

**Transcript.** The spoken words of a video or audio file as plain text. It is shown under the preview in the bank and stays with the *item*.

## U

**Unlisted.** An *item* hidden from other teachers. You and managers can still see and use it. **More → Make unlisted**. Compare *public*.

## V

**Version.** An earlier file kept when an *item* is replaced. On the Edit page, **Restore an earlier version** brings one back for every course that *links* to the item. The file it replaces is kept, so you can undo the undo.

## W

**WebVTT.** The text format for *captions*, in a file ending `.vtt`.

**Web service.** A way for another system to ask Moodle for data. The Media bank has three read-only functions: list items, get one item, and the courses that use it. They are not in the mobile app service.
