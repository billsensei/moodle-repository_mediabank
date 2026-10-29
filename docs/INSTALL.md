# Installing the Media bank

The Media bank lets your site keep videos, audio, PDFs and images in one place (the Content bank) and use them in any course, either as a **link** (change the file once and every course gets the new version) or as an **independent copy**.

**Prefer pictures?** [Install-Walk-Thru.pdf](Install-Walk-Thru.pdf) shows every click, with screenshots, for people who do not use the command line.

It is two plugins, installed in this order:

| Order | Plugin | Zip file |
|---|---|---|
| 1 | Media bank content type (`contenttype_mediabank`) | `contenttype_mediabank_<version>.zip` |
| 2 | Media bank tools (`local_mediabank`) | `local_mediabank_<version>.zip` |

The second one needs the first, so install them in that order. Do not unzip anything. Moodle reads the zip files as they are.

**Before you start:** make a backup of your site (database and `moodledata`). That is good practice for any plugin, and it is the only way to go back to an earlier version of this one (see "Removing the plugin" in the Admin guide).

## What you need

- Moodle **5.0, 5.1, 5.2 or 5.3**. These four have been tested (see the Admin guide). Moodle 4.5 and earlier are not supported.
- You must be a site administrator.
- Your host must allow installing plugins from the Moodle screen. If it does not, use "Installing by hand" below.

## About the "Beta" label

Both plugins are marked **Beta**. That means they are released for testing, not yet called stable, so Moodle may show a warning about it. That is expected and does not stop the installation. Try the plugin on a test copy of your site first.

## Installing from the Moodle screen

Do these steps once for each zip, **content type first**.

1. Log in as an administrator.
2. Go to **Site administration → Plugins → Install plugins**.
3. Under **Install plugin from ZIP file**, drop the zip into the **ZIP package** box (or choose it).
4. Leave **Plugin type** on its automatic setting if you see it. Moodle recognises these plugins by itself.
5. Click **Install plugin from the ZIP file**.
6. Moodle checks the file and shows **Validation successful, installation can continue**. Click **Continue**. The only warning you should see is the Beta one.
7. Click **Upgrade Moodle database now**, then **Continue**.
8. On the next screen Moodle shows the plugin's settings. The defaults are fine. Click **Save changes**.

Then repeat for the second zip.

## Check that it worked

1. Go to **Site administration → Plugins → Content bank → Manage content types**. **Media bank** should be in the list and switched on (the eye icon is open).
2. Go to **Site administration → Plugins → Repositories → Manage repositories**. **Content bank** should be enabled. Teachers need it to pick media into their courses.
3. Go to **Site administration → Plugins → Content bank → Media bank**. You should see the settings page.
4. Open a course as a teacher and go to the **Content bank** (in the course menu, usually under **More**). Click **Upload** and add a small picture or PDF. It should appear in the list.

If a step fails, see "If something goes wrong" below.

## After installing

Run the one-off "refresh links" step described under "Scheduled tasks" in the Admin guide, **only if you are upgrading from an earlier version**. A first-time install has nothing to refresh.

Before teachers upload large videos, read "Large files" in the Admin guide. Moodle and your web server each have limits that the plugin cannot raise.

## Upgrading to a newer version

Install the new zip the same way (content type first). Moodle sees that it is newer and offers to upgrade. Click through the screens. Do not uninstall first, because that deletes everything in the media bank.

## Installing by hand

Use this if your host does not let you install plugins from the screen.

1. Unzip each zip on your computer. Each contains one folder called `mediabank`.
2. Upload the folders to your Moodle code folder. The place depends on your Moodle version:

   | | Moodle 5.0 | Moodle 5.1 and later |
   |---|---|---|
   | Content type | `contentbank/contenttype/mediabank` | `public/contentbank/contenttype/mediabank` |
   | Tools | `local/mediabank` | `public/local/mediabank` |

   Upload the content type first. The folder must be called exactly `mediabank`, and the files inside must be directly in it (not in a second `mediabank` folder).
3. Make sure the web server user can read the files.
4. Open **Site administration → Security → Notifications** in your browser, or run `php admin/cli/upgrade.php` on the server. Moodle finds the new plugins and installs them.

## If something goes wrong

| What you see | What to do |
|---|---|
| "The ZIP package must contain just one directory" | You unzipped and re-zipped it. Use the original zip file. |
| "Plugin name mismatch" or "Plugin type mismatch" | The zip was changed or re-made by another tool. Use the original zip. |
| Moodle reports a missing **dependency** when you install the tools zip | Install the content type zip first, then try again. |
| The Install plugins screen says it cannot write to the plugin folder | Your host does not allow installing from the screen. Use "Installing by hand". |
| Media bank is missing from the Content bank | Go to **Plugins → Content bank → Manage content types** and switch it on. |
| Teachers cannot pick media inside an activity | Check that the **Content bank** repository is enabled (step 2 above), and that the teacher can use the Content bank in that course. |
| A large upload fails with no clear message | A server limit is being hit. See "Large files" in the Admin guide. |

If you still cannot install it, send the exact message Moodle shows, your Moodle version (shown at the bottom of **Site administration → Notifications**) and your PHP version.
