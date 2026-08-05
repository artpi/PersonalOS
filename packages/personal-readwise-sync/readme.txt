=== Personal Readwise Sync ===
Contributors: artpi
Tags: readwise, highlights, knowledge, sync
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync Readwise highlights into WordPress Knowledge records.

== Description ==

Personal Readwise Sync imports Readwise highlights and articles into the shared WordPress Knowledge surface, preferring `wp_knowledge` and falling back to `wp_guideline`.

Synced rows are private by default, authored by the user whose Readwise token is configured, and tagged with `artifact`, `note`, `readwise`, and `synced`.

The private Readwise sync app is available at `/readwise/` for per-user connection settings and follows the signed-in user's WordPress admin color scheme.

== Installation ==

1. Upload the `personal-readwise-sync` folder to `/wp-content/plugins/`.
2. Activate Personal Readwise Sync.
3. Open `/readwise/` and add your Readwise API token.

== Privacy ==

This plugin contacts the Readwise API at `https://readwise.io/api/v2/export/` using the API token entered by the user. It sends pagination and incremental sync parameters, receives exported highlights/articles, and stores them in WordPress Knowledge records. The optional book summary block sends the summary prompt, selected Readwise highlights, and draft context to providers configured through WordPress AI Client and Connectors.

Readwise terms: https://readwise.io/terms
Readwise privacy policy: https://readwise.io/privacy

== Data Retention ==

Personal Readwise Sync stores synced Knowledge records, Readwise identifiers, sync cursors, and user-entered settings in WordPress. Deactivating or uninstalling the plugin does not delete synced Knowledge records or user settings.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

Release ZIPs bundle WpApp 1.3.2 under GPL-2.0-or-later for app routing, access control, and theme isolation.

== Changelog ==

= 0.1.0 =
Initial independent package.
