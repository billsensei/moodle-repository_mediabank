# Media bank: Upgrading

How to move to a newer version of the Media bank, and what changes for you at each version. For a first install, see
[INSTALL.md](INSTALL.md). If something goes wrong, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Before you start

1. **Make a full backup** of the site: database and the Moodle data folder. Moodle cannot downgrade a plugin, so the backup is the
   only way back to an earlier version.
2. Try the upgrade on a **copy** of your site first. Both plugins are Beta.
3. Read "What changed in each version" below, for every version between yours and the new one.
4. Check your version at **Site administration → Plugins → Plugins overview** (search for "Media bank"). It shows the release,
   for example `0.9.2`, for each of the two plugins.

**Do not uninstall first.** Uninstalling deletes every item in the media bank. An upgrade keeps them.

## The steps

Always install the **content type** (`contenttype_mediabank`) first, then the **tools** (`local_mediabank`). The tools plugin names
the content type version it needs, and Moodle refuses it if the content type is older.

### From the screen

1. Put the site in maintenance mode if you like (**Site administration → Development → Maintenance mode**). The upgrade is quick
   and does not change the database layout, so this is optional.
2. Go to **Site administration → Plugins → Install plugins**, and install the new content type zip as you did the first time.
   Moodle sees that it is newer and shows **Upgrade**. Continue through the screens.
3. Do the same for the tools zip.
4. On the settings screen that follows, check any **new** settings (see the version list). Saved settings keep their values.
5. Do the "After the upgrade" steps below.

### By hand

Replace the folders on the server with the ones from the new zips (paths are in "Installing by hand" in
[INSTALL.md](INSTALL.md)). Delete the old folder first, so a file that was removed in the new version does not stay behind. Then
open **Site administration → Notifications** or run:

    php admin/cli/upgrade.php

Moodle upgrades the content type before the tools plugin.

### Purge caches

If the Content bank looks the same after the upgrade, or a new string shows as `[[name]]`, purge all caches: **Site administration
→ Development → Purge caches**, or `php admin/cli/purge_caches.php`.

## After the upgrade

1. **Run the link refresh once**, as the Administrator guide says. It brings links made by older versions up to date now and not at
   03:47:

       php admin/cli/scheduled_task.php --execute='\contenttype_mediabank\task\sync_links'

   It prints how many links it refreshed. Running it again prints 0.
2. **Check the install**: open the Content bank in a course as a teacher, open an item, and open a course page that links to one.
3. **Check cron is running**, if you are coming from a version before 0.10.0. Streaming preparation runs as a background task.
4. If you come from a version before 0.10.0 and want older videos prepared for streaming, queue them:

       php local/mediabank/cli/optimize_videos.php --dry-run
       php local/mediabank/cli/optimize_videos.php

   The first only counts them. This needs the tools plugin 0.3.0 or later.

## What changed in each version

Short notes on what matters to someone upgrading. The full lists are in [CHANGELOG.md](https://github.com/billsensei/moodle-repository_mediabank/blob/main/CHANGELOG.md) and in each plugin's
`CHANGES.md`.

| From | To | What you notice | What to do |
|---|---|---|---|
| any | 0.11.0 | New setting **Allow SVG images**, **off by default**. SVG uploads are refused unless it is on. | Nothing, unless you want SVG. See "SVG files" in the Administrator guide before switching it on. |
| before 0.10.0 | 0.10.0 or later | New setting **Prepare MP4 videos for streaming**, on by default. New and replaced MP4s are queued for a background task. The MP4 as uploaded is kept as an earlier version. | Make sure cron runs. Queue older videos with `optimize_videos.php` if you want. To keep the old behaviour, switch the setting off. |
| before 0.9.2 | 0.9.2 or later | An item's file is served only to people who may see the item. Before, any logged-in user who knew the address could fetch any item, unlisted ones included. Language strings are in alphabetical order. | Nothing. This is a fix. |

Only 0.9.2 and later were ever released on GitHub, and those are the versions that have been tested as an upgrade path. Versions
before 0.9.2 were development builds. If you run one, take a backup and check the result carefully.

## Does the upgrade change my data?

- **Database.** The plugins have no tables of their own. An upgrade does not change the database layout. Item information (alt text,
  transcript, size) is kept with the Content bank item, as before.
- **Files.** Your files are not moved or rewritten. The exception is streaming preparation (0.10.0 and later), which rewrites an
  MP4 in place, in a background task, and keeps the file as uploaded as an earlier version.
- **Settings.** Saved values are kept. A new setting gets its default.
- **Links in courses.** They keep working. The refresh task makes sure each carries the item's current file.

## Upgrading Moodle itself

The Media bank works on Moodle 5.0, 5.1, 5.2 and 5.3.

1. Check that the Media bank release you run is one that lists your **new** Moodle version (see the README and the FAQ).
2. Moodle 5.1 and later keep plugin code in a `public/` folder. When you move from 5.0 to 5.1 or later by replacing the code
   folder, put the two plugin folders in the new place ("Installing by hand" in [INSTALL.md](INSTALL.md) has the table). Moodle's
   own upgrade documentation covers the rest of the move.
3. Run Moodle's upgrade as normal. Upgrade the Media bank afterwards if a newer release is available, not at the same time, so
   that a problem is easier to trace.
4. Moodle 4.5 and earlier are not supported.

## If the upgrade fails

| What you see | What to do |
|---|---|
| Moodle reports a missing dependency for the tools plugin | Upgrade the content type first, then the tools plugin. |
| "The ZIP package must contain just one directory", or a name mismatch | Use the original zip. Do not unzip and re-zip it. |
| Moodle says the plugin is older than the installed version | Moodle cannot downgrade a plugin. Install the newer zip, or restore the site backup to go back. |
| A new setting or string is missing after the upgrade | Purge all caches. |
| The upgrade stops partway | Run `php admin/cli/upgrade.php` again; Moodle continues. If it fails with the same message, keep the message and see TROUBLESHOOTING.md, "What to collect". |

## Going back

You cannot downgrade. To return to an earlier version, restore the **database and data folder from the backup** you made
before the upgrade, together with the earlier plugin folders. Restoring only the plugin folders will not work.

## Checking a download

Each release on GitHub has the two plugin zips and a bundle zip with the guides. To check you have the right files, compare the
version and release in the zip's `version.php` with the release page.
