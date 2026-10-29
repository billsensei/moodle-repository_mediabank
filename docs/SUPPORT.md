# Media bank: Getting support

The Media bank is a free, open-source project in **Beta**. Help is given on a best-effort basis by the maintainer and other users. There is no paid support, no service agreement and no promised reply time.

## Start here

Most questions are already answered. Look in this order:

| If you… | Read |
|---|---|
| are installing it for the first time | [Install walk-through (PDF)](Install-Walk-Thru.pdf) or [INSTALL.md](INSTALL.md) |
| are a teacher | [Use walk-through (PDF)](Use-Walk-Thru.pdf) or the [Teacher guide](TEACHER-GUIDE.md) |
| see an error or something does not work | [TROUBLESHOOTING.md](TROUBLESHOOTING.md): organised by what you see |
| have a quick question | the [FAQ](FAQ.md) |
| do not know a word | the [Glossary](GLOSSARY.md) |
| manage the site: settings, limits, backups, privacy | the [Administrator guide](ADMIN-GUIDE.md) |
| are upgrading | [UPGRADING.md](UPGRADING.md) |
| want to know what is planned | the [Roadmap](ROADMAP.md) |

## Who to ask

| Your question | Ask |
|---|---|
| "How do I use it?" or "What does this setting do?" | Your own **Moodle administrator** first. They know your site, and the Administrator guide is written for them. |
| A bug, or something the guides do not explain | Open a [GitHub issue](https://github.com/billsensei/moodle-repository_mediabank/issues/new/choose) and pick the **bug report** form. |
| An idea | Open an issue and pick the **feature request** form. Say what you are trying to do. |
| A security problem | **Do not** open a public issue. Report it privately; see [SECURITY.md](../SECURITY.md). |
| A problem in Moodle itself, not in the Media bank | Moodle's own channels. Security problems in Moodle go to <https://moodle.org/security/>. |

There is no chat or forum for this project. Use issues for questions too.

## How to ask so you get an answer

A good report lets someone else see what you saw. Please include:

- **The versions:** Moodle (shown at the bottom of **Site administration → Notifications**), PHP, your database, and both plugin versions (**Site administration → Plugins → Plugins overview**).
- **What you did,** step by step, what you expected, and what happened.
- **The exact message** Moodle showed. A screenshot is fine.
- **For a video that will not play or a file that is refused:** the file type and size, and whether other files of the same kind work. Do not attach private or copyrighted files.
- **For a server error:** the line from the web server or PHP log, or turn on debugging in Moodle (**Site administration → Development → Debugging**) and copy what it shows. Remove passwords and personal details first. TROUBLESHOOTING.md has a section, "Still stuck: what to collect", that lists what helps.

Never post passwords, tokens or web service keys in an issue.

## Supported versions

- **Moodle:** 5.0, 5.1, 5.2 and 5.3. Moodle 4.5 and earlier are not supported.
- **Plugins:** only the newest release of each plugin is looked at. If you are on an older one, upgrade first (see [UPGRADING.md](UPGRADING.md)).
- **Not supported:** the Moodle mobile app, and databases other than MariaDB and PostgreSQL.

## Before you report a bug

1. Make sure you have the newest release: <https://github.com/billsensei/moodle-repository_mediabank/releases>.
2. Purge all caches (**Site administration → Development → Purge caches**) and try again.
3. Search the existing [issues](https://github.com/billsensei/moodle-repository_mediabank/issues), including closed ones.
4. Try it on a copy of your site, with other add-on plugins switched off, if you can.

## Want to help?

Answering another person's question, improving a guide and testing on your own site all count. See [CONTRIBUTING.md](../CONTRIBUTING.md), and add yourself to [CONTRIBUTORS.md](CONTRIBUTORS.md).
