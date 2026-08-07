=== Personal Readwise Sync ===
Contributors: artpi
Tags: readwise, highlights, knowledge, sync
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.2.24
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync Readwise highlights into WordPress Knowledge records.

== Description ==

Personal Readwise Sync imports Readwise highlights and articles into the shared WordPress Knowledge surface using `wp_knowledge` and `wp_knowledge_type`.

Synced rows are private by default, authored by the user whose Readwise token is configured, and tagged with `artifact`, `note`, `readwise`, and `synced`.

Readwise is a background integration. Its WordPress Settings screen contains the per-user connection, sync status, and import controls; imported content is available through Knowledge consumers such as Personal Notes.

== Installation ==

1. Upload the `personal-readwise-sync` folder to `/wp-content/plugins/`.
2. Activate Personal Readwise Sync.
3. Open Settings > Personal Readwise Sync and add your Readwise API token.

== Privacy ==

This plugin contacts the Readwise API at `https://readwise.io/api/v2/export/` using the API token entered by the user. It sends pagination and incremental sync parameters, receives exported highlights/articles, and stores them in WordPress Knowledge records. The optional book summary block sends the summary prompt, selected Readwise highlights, and draft context to providers configured through WordPress AI Client and Connectors.

Readwise terms: https://readwise.io/terms
Readwise privacy policy: https://readwise.io/privacy

== Data Retention ==

Personal Readwise Sync stores synced Knowledge records, Readwise identifiers, sync cursors, and user-entered settings in WordPress. Deactivating or uninstalling the plugin does not delete synced Knowledge records or user settings.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

== Changelog ==

= 0.1.0 =
Initial independent package.
