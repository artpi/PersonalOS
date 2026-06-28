=== Personal AI Chat ===
Contributors: artpi
Tags: ai, chat, knowledge, abilities
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.2.24
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chat with WordPress AI Client using Knowledge and registered abilities.

== Description ==

Personal AI Chat stores prompts, memories, artifacts, and chat transcripts in the shared WordPress Knowledge surface. It discovers tools through the WordPress Abilities API and uses WordPress AI Client when available.

The plugin activates without Notes, TODO, Readwise, or Evernote. If AI Client or provider credentials are missing, it shows setup status instead of fataling.

== Installation ==

1. Upload the `personal-ai-chat` folder to `/wp-content/plugins/`.
2. Activate Personal AI Chat.
3. Configure WordPress AI Client and provider Connectors.

== Privacy ==

This plugin is designed to send chat prompts and selected Knowledge context to providers configured through WordPress AI Client and Connectors. The exact destination depends on the provider configured by the site owner.

== Data Retention ==

Personal AI Chat stores conversation Knowledge records in WordPress. Deactivating or uninstalling the plugin does not delete chat transcripts, prompts, memories, artifacts, or provider connector settings.

== Source and Build ==

Source files live in this plugin directory. From the repository root, run `npm run build` and `npm run package:plugins` to produce release ZIPs with built assets and bundled shared helpers.

== Changelog ==

= 0.1.0 =
Initial independent package.
