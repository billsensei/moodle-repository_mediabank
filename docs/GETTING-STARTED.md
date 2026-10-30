# Media bank: Getting started

The shortest way from nothing to a file working in a course. It takes about fifteen minutes. Each step names the guide with the full story.

**Prefer pictures?** [Install-Walk-Thru.pdf](Install-Walk-Thru.pdf) and [Use-Walk-Thru.pdf](Use-Walk-Thru.pdf) show every click.

## Before you start

- You have a Moodle site, version **5.0 to 5.3**, and you are a site administrator.
- Use a **test copy** of your site. The plugins are **Beta** (released for testing), and you should make a backup first.
- You have a small file to try: a short MP4 video, a PDF or a picture.

## Part 1: Install it (administrator, about 5 minutes)

1. Open the project's **Releases** page on GitHub and the latest release. Under **Assets**, download two files:
   - `contenttype_mediabank_<version>.zip`
   - `local_mediabank_<version>.zip`

   Do not use **Code → Download ZIP**, the **Source code** links or the `-bundle.zip`. Moodle cannot install those. See "Which file do I download?" in [INSTALL.md](INSTALL.md).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Upload `contenttype_mediabank_….zip` first. Do not unzip it, and leave **Plugin type** alone. Click **Install plugin from the ZIP file**, **Continue**, then **Upgrade Moodle database now**, then **Continue**, then **Save changes**.
4. Repeat for `local_mediabank_….zip`.

To check it worked, go to **Site administration → Plugins → Content bank → Manage content types** and see that **Media bank** is listed and switched on. Also check that **Content bank** is enabled under **Site administration → Plugins → Repositories → Manage repositories**, because teachers need it to pick media into their courses. The full check is in [INSTALL.md](INSTALL.md).

If Moodle asks you to choose a plugin type, you have uploaded the wrong file. Go back to step 1. More in [INSTALL.md](INSTALL.md) and [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Part 2: Check that cron is running (administrator, 1 minute)

A video is prepared for fast playback, and old file versions are tidied up, by Moodle's scheduled tasks. They only run if **cron** runs. Ask your host, or look at **Site administration → Server → Tasks → Scheduled tasks** and see that the times of the last run are recent. See "Scheduled tasks" in the [Administrator guide](ADMIN-GUIDE.md).

You can try everything below without it. Videos are served as uploaded until the task runs.

## Part 3: Upload a file (teacher, about 2 minutes)

You need to be able to use the Content bank. By default managers, course creators and editing teachers can.

1. Open a course, then **Content bank**. It is in the course menu, usually under **More**.
2. Click **Upload**.
3. Click **Choose a file…**, pick your file, and click **Save changes**.

For a picture, Moodle also asks for a **text alternative**. Describe what the picture shows for someone who cannot see it. You cannot save an image without one.

The item now appears in the list. Click its name to see it.

If the file is refused, the message says why (the content is not the kind its name says, it is too big, or your site does not allow that kind). The [Teacher guide](TEACHER-GUIDE.md) has a table of the messages.

## Part 4: Use it in a course (teacher, about 3 minutes)

1. In the course, turn editing on and choose **Add an activity or resource → File**.
2. Give it a name. Under **Select files**, click **Add…**.
3. In the list on the left, choose **Content bank**, then click your item.
4. Choose **Link to the file** (the default) or **Make a copy of the file**.
5. Click **Select this file**, then **Save and return to course**.
6. Open the resource as a student would, and check the file shows.

**Link or copy?** A **link** follows the item: replace the item later and every course that links to it gets the new file. A **copy** is the course's own file and never changes. If in doubt, keep **Link**.

## Part 5: Try a replacement (teacher, about 3 minutes)

This is what the Media bank is for.

1. Back in the Content bank, open your item. Open the **More** menu and choose **Replace with file**.
2. Pick a different file of the **same kind** (a PDF for a PDF), and save.
3. Open the course resource again. It shows the new file, because it was a link.
4. To undo, open the item's **Edit** page and use **Restore an earlier version**. The earlier file is kept for 30 days by default.

## What to do next

| If you want to | Read |
|---|---|
| add captions, a transcript or a text alternative | the accessibility part of the [Teacher guide](TEACHER-GUIDE.md) |
| change the limits, switch on SVG or backup of linked files | the [Administrator guide](ADMIN-GUIDE.md) |
| know what a word means | the [Glossary](GLOSSARY.md) |
| fix a problem | [TROUBLESHOOTING.md](TROUBLESHOOTING.md) and the [FAQ](FAQ.md) |
| ask for help or report a bug | [SUPPORT.md](SUPPORT.md) |
| update to a newer version later | [UPGRADING.md](UPGRADING.md). Do not uninstall first: that deletes the items. |

## Remember

- It is **Beta**. Try it on a copy of your site first.
- Install the content type **before** the tools plugin.
- Linked files follow the item. Copies never change.
- Uninstalling the content type **deletes every item in the bank**. Make a full backup first.
