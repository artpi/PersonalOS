=== Personal Notes ===
Contributors: artpi
Tags: notes, knowledge, productivity
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, edit, search, and organize personal notes stored as WordPress Knowledge records.

== Description ==

Personal Notes stores notes in the shared WordPress Knowledge surface using `wp_knowledge` and `wp_knowledge_type`.

Notes are private by default and use Knowledge type terms such as `artifact`, `note`, `manual`, `inbox`, `now`, projects, areas, and resources.

The private Notes app is available at `/notes/` and follows the signed-in user's WordPress admin color scheme.

When writing in the WordPress block editor, the Notes sidebar can search and filter note-like Knowledge, preview a note, and insert it as a `pos/note` block. The native Knowledge Type panel can reassign the current note's `wp_knowledge_type` terms. Notes saved as Markdown, plain text, or classic HTML continue to use the classic editor.

== Installation ==

1. Upload the `personal-notes` folder to `/wp-content/plugins/`.
2. Activate Personal Notes.
3. Ensure your site provides the WordPress Knowledge runtime, then open `/notes/`.

== Frequently Asked Questions ==

= Does this require another Personal plugin? =

No. Personal Notes only requires a WordPress runtime that provides Knowledge records.

== Privacy ==

Personal Notes does not contact external services. User-created notes remain in WordPress unless removed by the user.

== Data Retention ==

Personal Notes stores user-created Knowledge records in WordPress. Deactivating or uninstalling the plugin does not delete notes or Knowledge type terms.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

Release ZIPs bundle WpApp 1.3.2 under GPL-2.0-or-later for app routing, access control, and theme isolation.

== Changelog ==

= 0.1.0 =
Initial independent package.
