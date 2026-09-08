# PersonalOS

PersonalOS is a suite of independent WordPress plugins for notes, tasks, chat,
sync and a home inventory, sharing WordPress Knowledge as their data model.

| Plugin | Purpose |
| --- | --- |
| [Personal Notes](packages/personal-notes/readme.txt) | Notes library and native editing at `/notes/` |
| [Personal TODO](packages/personal-todo/readme.txt) | Tasks, recurrence, dependencies and ICS at `/todo/` |
| [Personal AI Chat](packages/personal-ai-chat/readme.txt) | AI Client/Connectors-backed conversations at `/ai-chat/` |
| [Personal Stuff](packages/personal-stuff/readme.txt) | Inventory, places and photos at `/stuff/` |
| [Personal Readwise Sync](packages/personal-readwise-sync/readme.txt) | Readwise import and summaries |
| [Personal Evernote Sync](packages/personal-evernote-sync/readme.txt) | One-way Evernote import |

## Development and installation

Start with [contributor guidance](AGENTS.md), [local development](docs/development.md)
and [packaging](docs/packaging.md). Build standalone ZIPs with
`npm run build` followed by `npm run package:plugins`; install the selected ZIPs
on a WordPress site providing the required Knowledge runtime. Package readmes
specify their requirements. The default wp-env config supplies a development
Knowledge fixture for all six packages.

[GitHub releases](https://github.com/artpi/PersonalOS/releases) contain published
assets; verify that the desired package ZIP is present. A source merge does not
by itself publish a release. The root `personalos.php` and `modules/` are the
legacy monolith, not the current suite, and must not be active alongside it.

## Principles

- Borrow from Building a Second Brain and GTD.
- Implement features using native WordPress storage, APIs and components.
- Keep each package independently installable while preserving interoperability.

The public landing page lives in `docs/`. Historical module documentation and
split plans remain available for reference, not as current installation guidance.
