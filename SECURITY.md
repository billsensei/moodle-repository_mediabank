# Security policy

## Supported versions

The Media bank is **Beta** software. Only the newest release of each plugin gets security fixes.

| Plugin | Supported |
|---|---|
| `contenttype_mediabank` | the latest release (0.11.x) |
| `local_mediabank` | the latest release (0.3.x) |

Fixes are made for Moodle 5.0 to 5.3, the versions the plugins are tested on. Older plugin releases are not patched: upgrade
to the newest one. Moodle 4.5 and earlier are not supported.

## Reporting a vulnerability

**Please do not open a public issue, pull request or discussion for a security problem.**

Report it privately through GitHub:

1. Open the repository's **Security** tab.
2. Choose **Report a vulnerability**, or go straight to
   <https://github.com/billsensei/moodle-repository_mediabank/security/advisories/new>.

Please include:

- which plugin and version, the Moodle version, PHP version and database
- what the problem lets a person do, and what access they need (not logged in, any logged-in user, a teacher, an administrator)
- the steps to reproduce it, or a small file or request that shows it
- anything you know about a fix

The maintainer will read the report, tell you whether it is accepted, and agree with you a date for telling the public. A fix is
released first and described after, with credit to you if you want it.

## What counts

Examples of problems this policy is for: reading or changing a file or item the person should not be able to reach; getting
around the upload checks; a crafted file that crashes or exhausts a server; a link that leaks someone else's data; a flaw in
backup, restore, erasure or web services.

Earlier fixes are listed in the release notes (`CHANGES.md` in each plugin). They include serving files only to people allowed to
see the item (0.6.1), checks on backup contents when restoring (local 0.2.0), and limits on what is read from a video file when
preparing it for streaming (0.10.0).

## Not this project

Problems in **Moodle itself** should be reported to Moodle, not here, following
[Moodle's security policy](https://moodle.org/security/) (<security@moodle.org>). If you are not sure whether a problem is in
Moodle or in these plugins, report it here and it will be passed on if it belongs to Moodle.

A site that has not applied Moodle's own security updates, or runs a plugin version that is no longer the newest, is not a
vulnerability in the plugin.
