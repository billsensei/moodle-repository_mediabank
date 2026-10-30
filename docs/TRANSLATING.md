# Media bank: Translating

The plugins ship in **English only** for now ([Roadmap](ROADMAP.md)). Moodle shows the language each person has chosen, and falls back to English for any word that has no translation, so a partly translated plugin works. This page says how to translate the words on the screen, for your own site or for the project.

The guides in `docs/` are English only. Translating them is welcome, but is a separate job. See the end of this page.

## What there is to translate

| Plugin | File | Strings |
|---|---|---|
| `contenttype_mediabank` | `contenttype_mediabank/lang/en/contenttype_mediabank.php` | 68 |
| `local_mediabank` | `local_mediabank/lang/en/local_mediabank.php` | 2 |

Almost all of the work is the first file. The plugins have no JavaScript strings. The preview template uses the same file.

Each line looks like this:

    $string['allowsvg'] = 'Allow SVG images';

- The part in the first brackets is the **key**. **Never change it.**
- The part after the `=` is the **text**. That is what you translate.

## Rules for the text

1. **Keep placeholders exactly.** `{$a}` and `{$a->name}` are filled in by the plugin. Move them to where the sentence needs them, but do not change, translate or remove them. For example:

       $string['detachlinks'] = 'Convert all {$a} link(s) to copies';

2. **Keep the meaning of "link" and "copy".** They are the two choices a teacher makes, and the whole plugin depends on them. Use the same word each time, and use the words Moodle uses in your language for the file picker's **Link to the file** and **Make a copy of the file**, so that the screens agree. The [Glossary](GLOSSARY.md) says what each word means.
3. **Keep file extensions and technical names as they are:** `.pdf`, `.vtt`, `SVG`, `WebVTT`, `MP4`.
4. **Quotes.** The text is in single quotes. A single quote or apostrophe inside it must have a backslash before it, as in `l\'élément`. Many languages use apostrophes, so check each line.
5. **Do not add or remove strings.** If a string is missing in your file, Moodle shows the English one.
6. **Keep the same length where you can.** Labels sit in forms and buttons.
7. **Error messages say what happened and what to do.** Keep both parts ([STYLE-GUIDE.md](STYLE-GUIDE.md)).
8. **Help strings** (keys ending in `_help`) may contain Markdown. Keep the formatting marks.
9. **Privacy strings** (keys starting with `privacy:`) describe what the plugin stores. They appear in the site's privacy registry. Translate them carefully and do not weaken what they say.

## Option 1: Change the words on your own site (no code)

This is the quickest way, and it needs no change to the plugin.

1. Go to **Site administration → Language → Language customisation**.
2. Choose the language, and click **Open language pack for editing**.
3. Under **Show strings of these components**, choose `contenttype_mediabank`. Click **Show strings**.
4. Type your text into the box for each string. Click **Save changes to the language pack** at the bottom.

Moodle keeps your changes in the site's data folder, so they survive upgrades of the plugin. They are only on your site.

The language pack for your language must be installed first (**Site administration → Language → Language packs**).

## Option 2: Send a translation to the project

1. Find your language's code. Moodle uses a short code such as `fr`, `de`, `es`, `pt_br`, `nl`.
2. Copy the English file to a new folder named for your language. For the content type:

       contenttype_mediabank/lang/<code>/contenttype_mediabank.php

   and, for the tools plugin:

       local_mediabank/lang/<code>/local_mediabank.php

3. Keep the licence and the `defined('MOODLE_INTERNAL') || die();` lines at the top of the file. Translate the text of each `$string[...]` line.
4. Keep the strings in the same (alphabetical) order as the English file. The coding standard checks this.
5. Check the file has no syntax error:

       php -l contenttype_mediabank/lang/<code>/contenttype_mediabank.php

6. Install the plugin on a test site (or copy the folder into one), switch your user's language to yours, and look at each screen: the Content bank list, an item's page, the Edit page, the file picker's choice, the settings page and the error messages. Look for text that is cut off, placeholders that did not fill in, and strings still in English.
7. Open a pull request. Say which language it is and who checked it. See [CONTRIBUTING.md](../CONTRIBUTING.md) and add yourself to [CONTRIBUTORS.md](CONTRIBUTORS.md).

CI checks the language file's order and syntax. A new language needs no change to `version.php`, but it is shipped in the next release, so it does not reach people already installed until they update.

## Where the project's translations live

For now, translations are kept in the plugin's own `lang/<code>/` folder, as above. If the plugins are listed in the Moodle Plugins directory ([PLUGIN-DIRECTORY.md](PLUGIN-DIRECTORY.md)), Moodle's translation tool for plugins (AMOS) may become the better home for them. If that happens, this page will say how, and translations already in the repository will be moved over.

## Translating the guides

The guides are written in plain English on purpose ([STYLE-GUIDE.md](STYLE-GUIDE.md)), which makes them easier to translate.

- Start with the [Getting started](GETTING-STARTED.md) page, then the [Teacher guide](TEACHER-GUIDE.md).
- Keep the screen names in the language your Moodle shows, in bold, so people can find them. Where an English name helps (for example when searching the web), add it in brackets.
- Put a translation in `docs/<code>/` with the same file names, and say at the top which version of the English it came from.
- The two PDF walk-throughs show an English Moodle. They cannot be translated without new screenshots.
- A translation that is out of date is worse than none. Say plainly if you can no longer keep one up to date.

## Right-to-left languages

The plugins use Moodle's own pages and styles, so Moodle handles right-to-left (Arabic, Hebrew) for the page layout. The plugin's `styles.css` is small. If you find something that does not flip or line up in a right-to-left language, open an issue with a screenshot.

## Asking for help

If you are not sure about a string, open an issue ([SUPPORT.md](SUPPORT.md)) and quote its key. The maintainer can say where it appears.

## Copyright and licence

A translation is part of the plugin and is released under the same licence: GNU General Public License version 3 or later ([LICENSE](../LICENSE)). By sending one you agree to that.

Copyright 2026 SenseiMoodle.
