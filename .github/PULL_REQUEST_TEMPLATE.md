## What does this change, and why?

<!-- One purpose per pull request. Link the issue it fixes, for example "Fixes #12". -->

## Which plugin?

- [ ] `contenttype_mediabank`
- [ ] `local_mediabank`
- [ ] Documentation, workflow or repository files only

## Tested on

<!-- Tick what you ran. The GitHub Actions workflow runs all of it again. -->

- [ ] Moodle 5.0
- [ ] Moodle 5.1
- [ ] Moodle 5.2
- [ ] Moodle 5.3

PHP version and database used:

## Checklist

See [CONTRIBUTING.md](../CONTRIBUTING.md) for the details.

- [ ] A bug fix has a test that fails without the fix. A new feature has tests for what it does and what it must refuse.
- [ ] `phpcs --standard=moodle` reports nothing, warnings included, and I ran it on a copy **inside** a Moodle tree.
- [ ] Every new class, method and test has a PHPDoc comment, and language strings are in alphabetical order.
- [ ] Access is checked in every entry point, and anything that reads an uploaded file has limits on what it reads or builds.
- [ ] If a person's data is stored, the privacy provider and its tests are updated.
- [ ] `version.php` is raised and `CHANGES.md` has an entry, if this change ships. (`upgrade.php` too, if the database changes.)
- [ ] The guides in `docs/` are updated, and any screenshot of a changed screen is retaken.
- [ ] This is not a security problem. Those go through [SECURITY.md](../SECURITY.md), not a pull request.

## Anything the reviewer should know

<!-- Risks, things you were unsure about, parts you did not test. -->
