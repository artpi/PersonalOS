# Install PersonalOS plugins

PersonalOS is a set of six independent plugins built on WordPress Knowledge. Install only the modules you want; no PersonalOS base plugin or sibling plugin is required.

## Requirements

- WordPress 7.0 or later **with a Knowledge runtime** that registers `wp_knowledge`, hierarchical `wp_knowledge_type`, and the core `/wp/v2/knowledge` REST collection. The WordPress version number alone does not guarantee that Knowledge is available. Check your runtime before installing.
- PHP 7.4 or later for Notes, TODO, Stuff, and AI Chat; PHP 7.2.24 or later for the Readwise and Evernote integrations.
- Pretty permalinks for the private app routes. In WordPress, open **Settings → Permalinks** and choose a structure such as **Post name**.
- A WordPress account with permission to edit posts to use the apps.

AI Chat additionally needs WordPress AI Client and a configured provider through Connectors to generate replies. Readwise and Evernote imports need your credentials for those services. Core Knowledge access does not require an AI provider.

## Choose your plugins

Download individual ZIPs from [GitHub Releases](https://github.com/artpi/PersonalOS/releases). In WordPress, use **Plugins → Add New Plugin → Upload Plugin**, upload a ZIP, and activate it.

| Plugin | Where to open it | What it adds |
| --- | --- | --- |
| Personal Notes | `/notes/` | Note library, Knowledge Type organization, and editor integration |
| Personal TODO | `/todo/` | Tasks, scheduling, recurrence, dependencies, history, and ICS feeds |
| Personal Stuff | `/stuff/` | Inventory with photos, places, tags, search, and print/PDF |
| Personal AI Chat | `/ai-chat/` | Conversations saved in Knowledge, generation through AI Client |
| Personal Readwise Sync | Settings → Personal Readwise Sync | Import Readwise highlights and articles |
| Personal Evernote Sync | Settings → Personal Evernote Sync | One-way import from selected Evernote notebooks |

As of September 6, 2026, release `0.3.0` contains five plugin ZIPs. Personal Stuff is merged into `main` and can be built from source; it is awaiting its first release ZIP. Check the release asset list before downloading.

Open Readwise or Evernote settings to add your per-user token and configure the import scope. The integrations run in the background; apps such as Personal Notes can read their imported Knowledge records.

## Try the local development site

The repository's `wp-env` configuration mounts all six packages. It uses pinned Gutenberg 23.7.0 and enables its Knowledge experiment as a **development fixture**. That fixture is not a bundled release dependency or a promise of production support for the experiment.

With Node.js, Composer, and Docker available:

```bash
git clone https://github.com/artpi/PersonalOS.git
cd PersonalOS
npm ci
composer install
npm run build
npm run wp-env -- start
```

Open [localhost:8901](http://localhost:8901), sign in using wp-env's default development credentials (`admin` / `password`), and configure pretty permalinks. Then open `/notes/`, `/todo/`, `/stuff/`, or `/ai-chat/`. Use these default credentials only for local development.

If your WordPress environment does not provide Knowledge, the apps need a compatible runtime before they can work. The repository fixture is a way to evaluate the plugins locally.

## Build installable ZIPs

From the repository root, after installing dependencies and building assets:

```bash
# Build all six standalone plugins.
npm run package:plugins
npm run verify:split-packages

# Or build just Personal Stuff.
npm run package:plugin -- --package=personal-stuff
```

The packaging command reports the output paths. Install the generated ZIP, rather than copying a package source directory: release ZIPs contain their own shared helpers and required bundled runtimes.

## Your data and connections

The apps store their durable records in WordPress Knowledge. User-created records are private by default and use WordPress permissions. Deactivation or uninstall leaves records in place. This does not guarantee that every Knowledge consumer displays every type of record: each app chooses its own relevant types.

Stuff photos are WordPress Media attachments. Their URLs remain publicly accessible to someone who knows the URL, even when the inventory record is private. Knowledge Type terms retain the visibility provided by your Knowledge runtime.

Readwise and Evernote contact the services you configure. AI generation sends prompts to your configured provider. See each [package readme](https://github.com/artpi/PersonalOS/tree/main/packages) for the exact privacy and retention behavior.
