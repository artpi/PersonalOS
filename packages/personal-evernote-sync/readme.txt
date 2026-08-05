=== Personal Evernote Sync ===
Contributors: artpi
Tags: evernote, notes, knowledge, sync
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync Evernote notes into WordPress Knowledge records.

== Description ==

Personal Evernote Sync stores Evernote notes in the shared WordPress Knowledge surface, preferring `wp_knowledge` and falling back to `wp_guideline`.

Synced rows are private by default, authored by the user whose Evernote token is configured, and tagged with `artifact`, `note`, `evernote`, and `synced`.

The private Evernote sync app is available at `/evernote/` for per-user connection settings and follows the signed-in user's WordPress admin color scheme.

== Installation ==

1. Upload the `personal-evernote-sync` folder to `/wp-content/plugins/`.
2. Activate Personal Evernote Sync.
3. Open `/evernote/` and add your Evernote developer token and selected notebook GUIDs.

== Privacy ==

This plugin contacts Evernote using the developer token entered by the user. It stores configured notebook identifiers and synced note metadata in WordPress user meta and Knowledge records.

Bundled runtime libraries: Evernote Cloud SDK for PHP (Apache-2.0) and PSR Log (MIT). License files are included in release ZIPs.

Evernote terms: https://evernote.com/legal/terms-of-service
Evernote privacy policy: https://evernote.com/privacy

== Data Retention ==

Personal Evernote Sync stores synced Knowledge records, Evernote identifiers, sync cursors, and user-entered settings in WordPress. Deactivating or uninstalling the plugin does not delete synced Knowledge records or user settings.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

Release ZIPs bundle WpApp 1.3.2 under GPL-2.0-or-later for app routing, access control, and theme isolation.

== Changelog ==

= 0.1.0 =
Initial independent package.
