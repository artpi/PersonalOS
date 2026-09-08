=== Personal Stuff ===
Contributors: artpi
Tags: inventory, knowledge, photos
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find and organize belongings with places, tags, and Gutenberg photo galleries.

== Description ==

Personal Stuff is a private inventory app at `/stuff/`, built on WordPress Knowledge. Browse a photo grid or list, search names, descriptions and location paths, organize hierarchical places, assign tags, and print a filtered collection to PDF using your browser.

Items are private Knowledge posts. Places and tags are ordinary Knowledge Type terms. Photos, captions, and their ordering use native Gutenberg image and gallery blocks, editable in both Stuff and the WordPress block editor. Personal Notes can be active alongside Stuff.

No custom database tables, item metadata, settings, or REST endpoints are added. The bundled operating skill explains how an agent can use the same native APIs.

== Installation ==

1. Upload the `personal-stuff` directory to `/wp-content/plugins/` and activate it.
2. Ensure the site provides `wp_knowledge`, hierarchical `wp_knowledge_type`, and their core REST endpoints.
3. Enable pretty permalinks in WordPress Settings > Permalinks for top-level WpApp routes.
4. Open Stuff from the admin menu, plugin launcher, or `/stuff/`.

Fixed routing terms are provisioned by slug when Knowledge is available. No other Personal plugin is required.

== Frequently Asked Questions ==

= Where are photos stored? =

Photos are WordPress Media attachments. New files uploaded for Stuff receive cryptographically random filenames before entering the uploads directory. Media files still have public URLs; knowing a URL permits access. Existing attachments and external photo URLs retain their existing filenames and access rules.

= How do I edit a photo or remove an item? =

Use the native image/gallery block controls to edit captions, alt text, order, and image source. The WordPress Media Library manages attachment deletion. The Gutenberg document controls provide Move to trash for an item. Removing an image block leaves its Media file intact.

== Privacy ==

Personal Stuff does not contact external services automatically. A photo URL you add is fetched by the browser from that host and may disclose the visitor's IP address and ordinary request information to that host. No telemetry is collected.

Inventory posts are private by default and use WordPress's existing permissions. Place/tag terms have the visibility provided by the Knowledge taxonomy; this plugin adds no separate term privacy layer. Media URLs remain public and hard to guess, rather than access-controlled. Original image metadata is not stripped by this plugin.

== Data Retention ==

Deactivating or uninstalling leaves Knowledge posts, taxonomy terms, and Media files in WordPress. There is no importer or separate inventory storage to remove. WordPress may keep normal revisions, editing metadata, and attachment metadata.

== Source and Build ==

Source is included under `src/`. In the PersonalOS repository run `npm ci`, `composer install`, `npm run build`, and `npm run package:plugin -- --package=personal-stuff`. The ZIP bundles shared helpers and WpApp 1.3.2 (GPL-2.0-or-later) for routing and app access control. See TECHNICAL.md for the storage contract and tests. The portable agent skill is included at skills/personal-stuff/SKILL.md and linked from the app.

== Changelog ==

= 0.1.1 =
Empty Knowledge Type REST descriptions now include a derived role and complete term path without changing stored term descriptions.

= 0.1.0 =
Initial Knowledge-backed Personal Stuff app.
