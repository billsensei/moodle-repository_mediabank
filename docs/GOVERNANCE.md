# Media bank: Governance

How decisions are made in this project, who can make them, and what people can expect. It is short on purpose: the Media bank is a small project with one maintainer, and this page says so plainly instead of describing a committee that does not exist.

For the day-to-day routine see [MAINTAINERS.md](MAINTAINERS.md). For how to take part see [CONTRIBUTING.md](../CONTRIBUTING.md).

## Who decides

| Role | Who | What they can do |
|---|---|---|
| **Maintainer** | Bill White ([SenseiMoodle](https://github.com/billsensei)) | Merge changes, make releases, decide what the project does and does not do, handle security and conduct reports, and add or remove other maintainers. |
| **Contributor** | Anyone who sends an issue, a pull request, a test, a translation or an improvement to a guide | Propose changes and take part in discussion. Listed in [CONTRIBUTORS.md](CONTRIBUTORS.md) if they wish. |
| **User** | Anyone who installs it | Report problems and ask for things. |

There is no board, vote or paid staff. The maintainer has the final say. That is a limit on how fast the project can move, and the reason the rules below are written down.

## How decisions are made

1. **Small changes** (a typo, a clearer sentence, a bug fix with a test) are merged by the maintainer when CI is green and the change meets the checklist in [MAINTAINERS.md](MAINTAINERS.md).
2. **Larger changes** (a new feature, a new setting, a change to what the plugins store, to who can see what, or to a standing rule) start as an issue. Say what problem it solves and for whom. The maintainer decides whether it fits, and the answer is recorded in the issue.
3. **Reasons are written down.** A decision that people may ask about later goes in the [Roadmap](ROADMAP.md), the [Architecture](ARCHITECTURE.md) notes or the changelog, not only in a conversation.
4. **Saying no is allowed.** The maintainer may decline a change that works, if it does not fit the project, adds more to maintain than it gives, or weakens a standing rule. The reason is given.
5. **A decision can be reopened** with new facts. Ask in the issue.

## What the maintainer will not change without saying so

These are the project's commitments to the people who use it. They are the standing rules in [MAINTAINERS.md](MAINTAINERS.md), and changing one is announced in the changelog under **Changed**, with the reason.

- SVG stays **off by default**.
- Erasing a person keeps the courses' files as ordinary copies.
- The content type works without the tools plugin.
- The plugins add no database tables of their own without an upgrade step and a privacy review.
- Access to a file is decided by the plugin, not left to core.
- No personal data, tokens or passwords are published in the repository, the issues or the release notes.
- Security fixes are released first and described after ([SECURITY.md](../SECURITY.md)).

## Releases

The maintainer decides when to release, using [RELEASING.md](RELEASING.md). A code change gets a new version number. A release is a pre-release while the plugins are Beta. Releases follow the changelog rules in [CHANGELOG-POLICY.md](CHANGELOG-POLICY.md).

## Security and conduct

Both are handled privately by the maintainer.

- **Security:** report through GitHub's private vulnerability report, as [SECURITY.md](../SECURITY.md) says. Do not use a public issue.
- **Conduct:** follow [CODE-OF-CONDUCT.md](CODE-OF-CONDUCT.md). If the report is about the maintainer, report it to GitHub through its reporting tools.

## Disagreements

1. Say what you disagree with, and why, in the issue or pull request. Keep it about the work.
2. If you still disagree after the maintainer has answered, the decision stands for now. You may ask again if the facts change.
3. If you think the project is going the wrong way, the licence lets you take it another way. See "Forking" below.

## Becoming a maintainer

Maintainers are chosen by the existing maintainer, after a person has made several good contributions over time, such as fixes with tests, reviews or guide improvements, and has shown they follow the [Code of Conduct](CODE-OF-CONDUCT.md) and the [Style guide](STYLE-GUIDE.md). Ask in an issue. A new maintainer is added to [MAINTAINERS.md](MAINTAINERS.md) and [CONTRIBUTORS.md](CONTRIBUTORS.md), and is given access to the repository by its owner.

A maintainer may step down at any time by saying so in an issue. A maintainer who is inactive for a long time, or who breaks the Code of Conduct, may be removed by the owner, with the reason given to them in private.

## If the maintainer cannot continue

See "If the maintainer cannot continue" in [MAINTAINERS.md](MAINTAINERS.md). In short: the project can be forked, and anyone who wants to take it over can say so in an issue. The owner of the repository decides who gets access.

## Forking

The plugins are licensed under the GNU General Public License version 3 or later ([LICENSE](../LICENSE)). Anyone may copy, change and share them, including as a separate project, as long as the same licence goes with them. A fork should use a different plugin name if it will be installed on the same site, because Moodle tells plugins apart by name.

## Moodle's own rules

This project follows Moodle's plugin rules, coding standard and security guidance, and goes by Moodle's decisions about its APIs. If Moodle changes in a way that affects the plugins, the plugins change to match ([MAINTAINERS.md](MAINTAINERS.md), "When Moodle releases a new version"). The project is not part of Moodle HQ and is not endorsed by it.

## Changing this page

The maintainer can change this page. A change to who decides, or to the commitments above, is announced in the changelog.

## Copyright and licence

Copyright 2026 SenseiMoodle. Released under the GNU General Public License version 3 or later. See [LICENSE](../LICENSE).
