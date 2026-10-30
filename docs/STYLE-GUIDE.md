# Media bank: Style guide

How the guides, the screens' words and the release notes are written, so they read as one voice. It describes what the existing documents already do. It is for anyone writing or editing them.

The readers range from a teacher who has never opened the Content bank to an administrator who runs the site. Write for the less experienced reader first. If a developer needs more, put it in [ARCHITECTURE.md](ARCHITECTURE.md) or [DEVELOPMENT.md](DEVELOPMENT.md), not in the guides.

## Voice

- **Plain words.** Say "use", not "utilise"; "start", not "initiate". Short sentences. One idea in each.
- **Say what the person sees and does.** "Click **Save changes**" is better than "the changes should be saved".
- **Speak to "you".** Use "we" only for the project as a whole, and rarely.
- **Say what to do first, then why.** Put the reason after the step, in one sentence.
- **Be honest.** Say what is not done, what is not tested and what can go wrong. Do not hedge what is true, and do not promise what is not built. The [Roadmap](ROADMAP.md) lists ideas as ideas, not as plans with dates.
- **No marketing words.** Not "powerful", "seamless", "robust" or "easy". Show it.
- **No blame.** "The file was refused because its content is not a PNG" and not "you uploaded the wrong file".

## Spelling and punctuation

- **British spelling** in the documents: licence, organise, recognise, colour, behaviour, catalogue.
- **The language strings in the plugins are in plain English** (the Moodle `en` pack) and follow Moodle's own wording and case.
- **Do not use the em dash.** Use a full stop, a comma, a colon or brackets. A colon introduces what follows: "Two things can cause this: a full disk, or a missing file."
- Use the **Oxford comma only when it prevents confusion**, and keep lists parallel.
- Write dates as `2026-10-30` (year, month, day).
- Write numbers as digits when they are a count, a size or a version (`3 courses`, `100,000 characters`, `0.11.0`). Write "one" for one when it reads better.
- Sizes: `1 GB`, `200 MB`, `2 MB`. A space before the unit.

## Names

Use the same word for the same thing, every time. The [Glossary](GLOSSARY.md) is the authority.

| Say | Do not say |
|---|---|
| **item** (a thing in the Content bank) | file, asset, media, entry |
| **link** (the course follows the item) | alias, reference, shortcut |
| **copy** (the course's own file) | duplicate, clone |
| **earlier version** or **version** | revision, backup, history |
| **unlisted** and **public** | hidden, private, draft |
| **text alternative** (alt text is fine in brackets) | alt tag, description |
| **transcript** | script, text version |
| **captions** | subtitles |
| **Content bank** (capital C, as Moodle writes it) | content repository |
| **Media bank** (capital M) | the plugin, MediaBank |
| **administrator**, **manager**, **teacher**, **student** | admin user, instructor, learner |

- Say **plugin**, not "addon" or "module". An activity is not a plugin.
- The two plugins are always `contenttype_mediabank` ("the content type") and `local_mediabank` ("the tools"). Say "the content type" and "the tools plugin" in prose, and the full name in code and file names.
- **Beta** has a capital B, and explain what it means the first time it appears on a page.
- Do not call the project a repository when you mean a plugin. See the note in the [README](../README.md).

## Describing the screen

- **Bold** the words on a screen that a person clicks or reads: **Save changes**, **Content bank**, **Make unlisted**.
- Show a path with an arrow and bold: **Site administration → Plugins → Install plugins**. Use the arrow `→` with spaces.
- Name a setting exactly as the screen does, then say what it does.
- Put a file name, folder, command or setting key in `code` style. Show a command on its own lines, indented four spaces:

      php local/mediabank/cli/optimize_videos.php --dry-run

- Use `` `<version>` `` and `` `<release>` `` for parts that change, and say once what they stand for.
- Say which Moodle version a step applies to when it differs. If it is all of 5.0 to 5.3, say nothing.

## Structure

- The title is `# Media bank: <what the page is>`. Only one `#` heading in a document.
- A short opening paragraph says who the page is for and what it covers, and links to the nearest other guide.
- Headings are sentence case ("Installing from the Moodle screen"). Questions are fine in the FAQ and Troubleshooting ("Why can't I delete an item?").
- Use a **numbered list** for steps in order, a **bullet list** for items that are not in order, and a **table** for comparing things or for "what you see, why, what to do".
- A step is one action. If it has two, split it.
- Put a warning before the step it applies to, not after. Use **bold**, not capitals or exclamation marks.
- Link, don't copy. If another document says it, link to it. Two copies go out of step.
- A link says where it goes: "see [INSTALL.md](INSTALL.md)", not "click here".
- Link to other files in `docs/` with a relative path. Link to files outside `docs/` (like `CHANGELOG.md`) with `../`. In documents that are copied into the bundle, link to files that are in the bundle, or to the GitHub address.

## Screenshots and PDFs

- Take screenshots with real-looking sample content and a neutral site name. Never show a real person, email address, token, course or student.
- Mark the thing to click with a numbered red box, and match the number in the step.
- Add alt text to every picture that says what it shows and what to do with it. Do not start with "image of".
- Keep images in `docs/images/`. Name them for the guide and the step: `teacher-4-link-or-copy.png`.
- Retake the affected pictures when Moodle's screens change, and redo the matching pages of the [Install](Install-Walk-Thru.pdf) and [Use](Use-Walk-Thru.pdf) walk-throughs.
- A PDF is made from the same words as the guides. If you change a step in the guide, change it in the PDF.

## Accessibility of the documents

- Headings in order, never skipped.
- Tables have a header row and are not used for layout.
- Do not rely on colour alone. A red box also has a number.
- Spell out an abbreviation the first time on a page (GDPR, WebVTT, SVG), unless it is in the Glossary and linked.
- Check a new page reads sensibly with a screen reader, and that every picture's alt text still makes sense without the picture.

## Privacy and safety

- Never put a personal email address, a password, a token or a real person's name in a document, a screenshot or a changelog. Contact is through GitHub ([SUPPORT.md](SUPPORT.md)).
- Do not describe how to exploit a problem. Describe what it was and what to do ([CHANGELOG-POLICY.md](CHANGELOG-POLICY.md)).
- Do not give advice that changes security settings without saying what it costs.

## Language strings in the plugins

These are the words on the screen, so the same rules apply.

- Sentence case for labels and headings: "Allow SVG images", not "Allow Svg Images".
- Say what happened, why, and what to do, in that order, in an error message. Include the limit when a limit caused it: "The transcript is too long. It can have at most 100,000 characters."
- Keep `$string` keys in alphabetical order, as Moodle's coding standard requires. Keys for help text end in `_desc` or `_help`.
- Never put a name, address or other personal data in a string.

## Before you merge a document

- [ ] It follows the names table above.
- [ ] It has no em dashes, and uses British spelling.
- [ ] Every link works. Every path with an arrow is exactly what the screen says.
- [ ] A guide in the bundle is listed in `GUIDES` in `scripts/build_release.py`, and the bundle is swapped if it changed after a release ([RELEASING.md](RELEASING.md)).
- [ ] It is linked from the README, or from the guide closest to it.
- [ ] There is nothing private in it or its pictures.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
