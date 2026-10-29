# Media bank tools (local_mediabank)

Companion to `contenttype_mediabank`, which it requires (install that one first). For Moodle 5.0 to 5.3. Status: **Beta**.

What it provides:
- Backup and restore of links to media bank items. With the site setting "Include linked files in course backups"
  (Site administration > Plugins > Content bank > Media bank), manual course backups carry the linked files, and a
  restore on another site turns them into ordinary copies instead of losing them.
- A command-line tool for one item: `php local/mediabank/cli/item.php --id=N [--usage] [--versions] [--detach] [--restore=N]`.

Install the plugin zip from Site administration > Plugins > Install plugins. Guides come with the release bundle,
`mediabank-<release>-bundle.zip`. Release notes: `CHANGES.md`.
