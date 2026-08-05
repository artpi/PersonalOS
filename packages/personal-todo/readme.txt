=== Personal TODO ===
Contributors: artpi
Tags: todo, tasks, knowledge, calendar
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage task artifacts and calendar feeds stored as WordPress Knowledge records.

== Description ==

Personal TODO stores each task as its own Knowledge record tagged with `artifact` and `todo`. Tasks can also use shared Knowledge type terms such as `inbox`, `now`, `later`, `follow-up`, projects, areas, and resources.

The plugin includes a per-user ICS feed and registers TODO abilities when the WordPress Abilities API is available.

The private TODO app is available at `/todo/` and follows the signed-in user's WordPress admin color scheme.

== Installation ==

1. Upload the `personal-todo` folder to `/wp-content/plugins/`.
2. Activate Personal TODO.
3. Ensure your site provides the WordPress Knowledge or Guidelines runtime, then open `/todo/`.

== Frequently Asked Questions ==

= Does this require Personal Notes? =

No. Personal TODO writes directly to Knowledge and can run without Notes, Readwise, Evernote, or AI Chat.

== Privacy ==

Personal TODO does not contact external services. ICS feed access uses a per-user token and returns only that user's task records.

== Data Retention ==

Personal TODO stores task Knowledge records, task history comments, operational task meta, and per-user ICS tokens in WordPress. Deactivating or uninstalling the plugin does not delete tasks, comments, task meta, or tokens.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

Release ZIPs bundle WpApp 1.3.2 under GPL-2.0-or-later for app routing, access control, and theme isolation.

== Changelog ==

= 0.1.0 =
Initial independent package.
