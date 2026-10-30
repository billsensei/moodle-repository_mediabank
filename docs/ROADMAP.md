# Media bank: Roadmap

Where the project stands and where it could go. **This is a list of ideas, not promises.** Nothing here has a date. The maintainer decides what gets built, and some of it may never be. What *has* been released is in [CHANGELOG.md](../CHANGELOG.md).

## Where it stands

Both plugins are **Beta** (released for testing): `contenttype_mediabank` 0.11.0 and `local_mediabank` 0.3.0, for Moodle 5.0 to 5.3. The features work and are well tested, but the plugins have not had long use on real sites. What has and has not been tested is listed in the Administrator guide, section 12.

## 1. Towards a stable release

These are the gaps the guides already admit to. Closing them is what would justify dropping the word Beta. The list is a proposal.

| Gap | What would close it |
|---|---|
| Little use on real sites | Trials on real Moodle sites, and their bug reports. Open an [issue](https://github.com/billsensei/moodle-repository_mediabank/issues) with what you found. |
| The SVG check has had no manual security review | A review by a person who knows SVG and Moodle's file serving. Until then SVG stays **off by default**. |
| Only the **File** resource was tried for Link or Copy | Try the choice in other activities that use the standard file picker (for example Folder or Assignment attachments) and fix what differs. |
| Browser scenarios were last run before streaming preparation and SVG were added | Re-run the 7 scenarios on all four Moodle versions, and add scenarios for video and SVG. |
| Site-level items were covered only by automated tests | Browser scenarios for items in the site context. |
| Very large files and a real web server | Test uploads near the 1 GB limit through a real web server, with and without antivirus scanning. |

## 2. Ideas that would help teachers

Not decided. Each needs a design check against how Moodle works.

- **Convert just my course's link to a copy.** Today a teacher picks the item again and chooses "Make a copy of the file", and only a manager can convert all links at once.
- **Carry alt text, transcripts and captions with the file.** Today a course gets the bare file. Moodle gives a plugin no standard way to attach these to a file alias, so this is a hard one.
- **A clearer "where is this used" page,** with links to the activities and not only the course names.
- **Translations.** The plugins ship English only. Language packs are welcome: see [TRANSLATING.md](TRANSLATING.md).

## 3. Ideas for administrators

- **Listing in the Moodle plugins directory,** so sites can install and update from Moodle itself. This needs the stable-release work above first.
- **A report of items nobody links to,** to help clear space.

## 4. Video, audio and files

- **Streaming:** done in 0.10.0 (the MP4 index is moved to the front, in PHP). Not done, and not planned for now: re-encoding, resolution caps, several quality levels and HLS. They need a program such as ffmpeg on the server, which the project has chosen to avoid.
- **Other containers:** `.webm` and `.m4a` are left as they are.
- **More file kinds** only if there is a clear need and a safe way to check them. SVG showed how much work "safe" can be.

## 5. Not planned

- **Moodle 4.5 and earlier.** The plugins use Moodle 5.0 features.
- **Oracle and SQL Server.** Never tested. The plugins have no tables of their own, so they may work, but nobody has tried.
- **The Moodle mobile app.** The plugin has no mobile support.

## How to influence this

- **Ask or suggest:** open an issue and pick the feature request form. Say what you are trying to do, not only the feature you imagine.
- **Build it:** read [CONTRIBUTING.md](../CONTRIBUTING.md) and the [Development guide](DEVELOPMENT.md), and open a pull request. A small, tested change is more likely to be merged.
- **Report a security problem privately,** as [SECURITY.md](../SECURITY.md) describes.
