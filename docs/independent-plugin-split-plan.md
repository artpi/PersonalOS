# Lean Guidelines-Based Plugin Split Plan

Date: 2026-06-10

## Executive Summary

The split should be smaller and more opinionated than the original module-by-module extraction plan.

Instead of extracting every current PersonalOS module, v1 should become a small family of independently installable plugins that share WordPress-native storage:

- **Notes**: UI and management for note-like Guidelines.
- **Readwise**: independent Readwise sync into Guidelines.
- **Evernote**: independent Evernote sync into Guidelines.
- **TODO**: task/checklist UI over separate `wp_guideline` task artifacts, with ICS folded in.
- **AI Chat**: chat/agent UI using Guidelines, Abilities, WordPress AI Client, and Connectors.

Everything else should be removed from v1 or parked for later:

- No OpenAI module.
- No Perplexity module.
- No IMAP/email responder module.
- No podcast or ElevenLabs module.
- No standalone ICS module.
- No transcription/voice module in v1.
- No mandatory base plugin and no `Requires Plugins` dependencies between PersonalOS plugins.

The core design change is that **Guidelines become the shared data layer for notes, imports, TODOs, prompts, memories, artifacts, and conversations**. TODOs should be separate `wp_guideline` posts tagged with `artifact` and `todo` so other Guidelines-aware tools, such as Obsidian sync, can pull them without knowing about the TODO plugin.

## Target Plugin Set

| Plugin | Responsibility | Hard Dependency | Optional Integrations |
| --- | --- | --- | --- |
| PersonalOS Notes | Display, edit, search, and organize note-like Guidelines | WordPress Guidelines | Readwise/Evernote content appears automatically because it is stored as Guidelines |
| PersonalOS Readwise | Sync Readwise highlights/articles into Guidelines | WordPress Guidelines | Notes UI can display imported rows |
| PersonalOS Evernote | Sync Evernote notes into Guidelines | WordPress Guidelines | Notes UI can display imported rows |
| PersonalOS TODO | Manage task artifacts, checklists, completion state, and ICS feeds | WordPress Guidelines | AI Chat can use TODO abilities when active |
| PersonalOS AI Chat | Chat UI, agent orchestration, prompt/memory/artifact loading, ability execution | Guidelines, AI Client/Connectors for live AI | TODO/Notes/other plugins only through Guidelines and Abilities |

This keeps every plugin independently useful:

- Readwise can sync without Notes installed.
- Evernote can sync without Notes installed.
- Notes can manage manual notes without Readwise or Evernote installed.
- TODO can manage tasks and expose ICS without AI Chat installed.
- AI Chat can run with whatever Guidelines and Abilities exist; it does not need TODO, Notes, Readwise, or Evernote.

## Dependency Model

### Hard Dependencies

Each v1 plugin depends only on WordPress core surfaces:

- `wp_guideline` CPT.
- `wp_guideline_type` taxonomy.
- Standard post meta, options, REST, cron, capabilities, actions, and filters.

TODO uses `wp_guideline` as the durable task store. Each actionable task should be its own Guideline post with `artifact` and `todo` terms. Do not use one giant Guideline as the TODO database.

AI Chat additionally needs WordPress 7.0 AI surfaces for live model calls:

- Connectors screen for provider credentials.
- AI Client via `wp_ai_client_prompt()`.

If AI Client or provider credentials are missing, AI Chat should activate and show setup guidance rather than fatal.

### No PersonalOS Runtime Dependency

Do not create a shared PersonalOS Core plugin for v1.

Avoid:

- Shared `POS_Module` base class.
- Central module registry.
- `POS::get_module_by_id()`.
- Constructor injection between plugins.
- `Requires Plugins: personalos-core`.

Use:

- Direct WordPress APIs.
- Small package-local helpers.
- Hooks and filters where a plugin needs an extension point.
- Abilities API for agent-callable operations.
- Guidelines queries for durable context.

### Soft Inter-Plugin Relationships

The plugins should interoperate through data and capabilities, not object references.

```text
Readwise  -> wp_guideline <- Notes UI
Evernote  -> wp_guideline <- Notes UI
TODO UI   -> wp_guideline <- Obsidian / other Guidelines consumers
TODO UI   -> Abilities    <- AI Chat
AI Chat   -> Guidelines + Abilities + AI Client + Connectors
```

Examples:

- Readwise creates `wp_guideline` rows with `artifact`, `note`, `readwise`, and `personalos` type terms.
- Evernote creates `wp_guideline` rows with `artifact`, `note`, `evernote`, and `personalos` type terms.
- Notes queries `wp_guideline` rows tagged with `note`, regardless of source.
- TODO creates one `wp_guideline` row per actionable task, tagged with `artifact` and `todo`.
- TODO stores workflow fields in registered REST-visible post meta and keeps title/content useful for consumers that only understand Guidelines.
- TODO registers abilities such as list/create/update/complete when the Abilities API exists.
- AI Chat discovers TODO through `wp_get_abilities()`, not through a PHP class.

## Guidelines Storage Model

Use the local Guidelines implementation guidance in `/Users/artpi/GIT/wp-agent-skills/skills/wp-guideline/SKILL.md`.

Guidelines uses `wp_guideline_type` as a taxonomy, so rows can and should receive multiple terms. That lets PersonalOS use canonical terms plus domain/source terms.

### Canonical Terms

Use the narrowest existing canonical term where possible:

- `artifact`: notes, imported documents, TODO records, AI-generated durable outputs, long-form source material.
- `memory`: remembered facts and durable user/site context.
- `skill`: reusable procedural instructions, prompts, agent workflows.

Do not use `plan` for TODO items in v1. TODOs are actionable artifacts, not planning documents. If a future workflow needs durable agent planning documents, revisit `plan` for those documents only.

### PersonalOS Routing Terms

Create or ensure these additional `wp_guideline_type` terms as routing/source terms:

- `personalos`
- `note`
- `todo`
- `readwise`
- `evernote`
- `ai-chat`
- `conversation`
- `daily-note`
- `imported`
- `manual`

This satisfies the idea of a "note Guideline type" without losing compatibility with the broader Guidelines meaning of `artifact`.

### Row Mapping

| Data | Guideline terms | Owner |
| --- | --- | --- |
| Manual note | `artifact`, `note`, `manual`, `personalos` | Notes |
| Daily note | `artifact`, `note`, `daily-note`, `personalos` | Notes |
| Readwise highlight/article | `artifact`, `note`, `readwise`, `imported`, `personalos` | Readwise |
| Evernote note | `artifact`, `note`, `evernote`, `imported`, `personalos` | Evernote |
| TODO item | `artifact`, `todo` | TODO |
| Checklist/project task group | multiple `artifact`, `todo` task rows, optionally linked by meta or parent/context | TODO |
| AI prompt/persona | `skill`, `ai-chat`, `personalos` | AI Chat |
| AI memory | `memory`, `ai-chat`, `personalos` | AI Chat |
| AI artifact | `artifact`, `ai-chat`, `personalos` | AI Chat |
| Chat transcript | `artifact`, `conversation`, `ai-chat`, `personalos` | AI Chat |

### Metadata

Use post fields for the human-readable content:

- `post_title`: title.
- `post_excerpt`: summary/search hint.
- `post_content`: full note/task/prompt/artifact body. For TODOs, keep this readable without the TODO plugin.
- `post_status`: `private` by default for user/agent-created data.
- `post_author`: owner.

Use post meta only for behavior, provenance, and sync:

- `_personalos_source`: `manual`, `readwise`, `evernote`, `todo`, `ai-chat`.
- `_personalos_external_id`: source-system ID.
- `_personalos_source_url`: source URL.
- `_personalos_synced_at`: last sync timestamp.
- `_personalos_source_hash`: import/update idempotency.
- `_personalos_chat_provider_response_id`: provider-specific response continuation ID, only where still needed.

Do not use meta as the primary discovery mechanism. Query by `wp_guideline_type` terms first, then use meta to refine.

For TODO, use registered REST-visible meta on `wp_guideline` rows:

- `todo_status`: `open`, `done`, `cancelled`.
- `todo_due_at`: due timestamp.
- `todo_scheduled_at`: scheduled timestamp.
- `todo_completed_at`: completion timestamp.
- `todo_priority`: optional priority.

## Current Module Mapping

| Current module/feature | V1 decision |
| --- | --- |
| Notes | Replace `notes` CPT ownership with PersonalOS Notes UI over `wp_guideline` rows tagged `note` |
| Readwise | Extract as independent Guidelines importer |
| Evernote | Extract as independent Guidelines importer |
| TODO | Extract as standalone Guidelines-backed TODO plugin using one `wp_guideline` post per task tagged `artifact` and `todo` |
| ICS | Fold into TODO plugin |
| OpenAI | Remove as module; AI Chat uses AI Client + Connectors |
| AI Chat | Extract as PersonalOS AI Chat |
| Daily | Fold into Notes as daily-note view/template |
| Bucketlist | Fold into TODO as tasks/lists or defer |
| Perplexity | Remove/defer |
| IMAP | Remove/defer |
| Email responder | Remove/defer with IMAP |
| Slack | Defer; future separate integration if needed |
| Transcription | Remove/defer |
| Podcast | Remove/defer |
| ElevenLabs | Remove/defer with podcast |
| Voice/realtime/Ollama compatibility | Remove/defer unless explicitly revived |

## Revised Migration Plan

### Phase 1: Add Guidelines Access Layer In The Monolith

Goal: make the current plugin able to read/write Guidelines without moving files yet.

- Add a small Guidelines repository/helper inside the current codebase.
- Detect `post_type_exists( 'wp_guideline' )` and `taxonomy_exists( 'wp_guideline_type' )`.
- Ensure PersonalOS routing terms idempotently.
- Add create/update/query helpers for note-like rows, TODO rows, skills, memories, artifacts, and conversations.
- Preserve user edits by using stable slugs/external IDs and source hashes.
- Keep current UI behavior running while the new storage layer is introduced.

Acceptance criteria:

- Guidelines support can be detected and reported.
- A private manual note can be created as `wp_guideline` with `artifact`, `note`, `manual`, and `personalos`.
- A TODO item can be created as a separate `wp_guideline` row with `artifact` and `todo` terms.
- Existing tests still pass.

### Phase 2: Migrate Notes, Prompts, Memory, And Conversations Additively

Goal: stop treating the `notes` CPT as the future storage owner.

Migrate existing rows additively:

| Existing storage | New Guidelines target |
| --- | --- |
| General `notes` posts | `artifact`, `note`, `manual`, `personalos` |
| `prompts-chat` notes | `skill`, `ai-chat`, `personalos` |
| `prompts-podcast` notes | Do not preserve as podcast behavior in v1; optionally migrate as archived `skill`, `personalos` |
| `ai-memory` notes | `memory`, `ai-chat`, `personalos` |
| `ai-chats` notes | `artifact`, `conversation`, `ai-chat`, `personalos` |
| Daily notes | `artifact`, `note`, `daily-note`, `personalos` |

Rules:

- Do not delete old CPT rows during the first migration.
- Store old post IDs in meta for traceability.
- Preserve title, content, slug, author, dates, status where possible.
- Convert notebook terms to prefixed Guidelines terms only if they are needed for filtering.
- Mark migrated rows with `_personalos_source_hash` so re-running migration is idempotent.

Acceptance criteria:

- Existing notes are visible through a Guidelines query.
- Existing chat prompts are discoverable as `skill` Guidelines.
- Existing AI memories are discoverable as `memory` Guidelines.
- Migration can run twice without duplicates.

### Phase 3: Convert Notes UI To Guidelines

Goal: make Notes a UI over Guidelines, not a private CPT owner.

- Change Notes screens, blocks, and REST reads/writes to target `wp_guideline`.
- Query note-like rows by `wp_guideline_type` terms, primarily `note`.
- Add filters for source terms: manual, daily-note, readwise, evernote, imported.
- Keep compatibility redirects or read-only access for old `notes` posts during migration.
- Remove Notes dependency from Readwise/Evernote code paths.

Acceptance criteria:

- Notes UI can create, edit, list, search, and filter note-like Guidelines.
- Readwise/Evernote imports appear in Notes UI without a PHP dependency.
- Notes plugin can be active by itself.

### Phase 4: Convert Readwise And Evernote To Independent Importers

Goal: make importers write directly to Guidelines.

Readwise:

- Store API credentials/settings in the Readwise plugin only.
- Sync highlights/articles as `wp_guideline` rows tagged `artifact`, `note`, `readwise`, `imported`, `personalos`.
- Use external IDs and source hashes for idempotent updates.

Evernote:

- Store Evernote credentials/settings in the Evernote plugin only.
- Sync notes as `wp_guideline` rows tagged `artifact`, `note`, `evernote`, `imported`, `personalos`.
- Use external IDs and source hashes for idempotent updates.

Acceptance criteria:

- Readwise can sync on a site without PersonalOS Notes active.
- Evernote can sync on a site without PersonalOS Notes active.
- Activating Notes later immediately displays imported Guidelines.

### Phase 5: Convert TODO To Guideline Task Artifacts And Fold In ICS

Goal: make TODO a standalone task plugin whose durable records are separate `wp_guideline` posts tagged `artifact` and `todo`.

- Migrate existing `todo` CPT rows additively into `wp_guideline` rows.
- Tag each task Guideline with `artifact` and `todo`.
- Do not use `plan` or `personalos` for ordinary TODO rows.
- Store due/scheduled/completed/status fields in registered REST-visible Guideline meta.
- Keep task title, excerpt, and content useful for non-PersonalOS consumers.
- Move ICS feed/export into the TODO plugin.
- Register TODO abilities when the Abilities API exists.
- Remove the standalone ICS module.

Acceptance criteria:

- TODO can create, edit, complete, filter, and search task Guidelines.
- Existing `todo` CPT rows can be migrated without deleting the old rows in the first pass.
- Obsidian or another Guidelines-aware tool can discover TODOs by querying `wp_guideline_type` for `artifact` and `todo`.
- ICS feed works from TODO data without any separate plugin.
- AI Chat can discover TODO abilities through the Abilities API when both plugins are active.
- TODO works without Notes, Readwise, Evernote, or AI Chat.

### Phase 6: Replace OpenAI Module With AI Chat

Goal: make AI Chat an agent plugin, not a provider credential module.

- Move chat UI and agent REST endpoints into PersonalOS AI Chat.
- Use Guidelines for prompts, skills, memories, artifacts, and chat transcripts.
- Use Connectors for provider credentials.
- Use AI Client via `wp_ai_client_prompt()` for supported model calls.
- Keep only tiny provider-specific adapters where AI Client lacks required behavior.
- Discover tools through `wp_get_abilities()`.
- Remove the OpenAI module as a shared dependency.

Acceptance criteria:

- AI Chat activates without Notes, TODO, Readwise, or Evernote.
- AI Chat can run with Guidelines and a configured AI provider.
- TODO tools appear only when TODO is active and registers abilities.
- Legacy OpenAI API key settings are deprecated or migrated to Connectors where possible.

### Phase 7: Physically Split Packages

Goal: move from a decoupled monolith to independent plugins.

Recommended package layout:

```text
packages/
  personalos-notes/
  personalos-readwise/
  personalos-evernote/
  personalos-todo/
  personalos-ai-chat/
```

For each package:

- Add its own plugin header.
- Add its own bootstrap.
- Add package-local helpers only.
- Add activation checks/admin notices for missing Guidelines or AI setup.
- Add tests proving activation without sibling plugins.
- Add package build metadata so CI can produce a standalone ZIP.
- Do not add `Requires Plugins` for another PersonalOS plugin.

Acceptance criteria:

- Each package can be installed and activated independently.
- No package references `POS::get_module_by_id()`.
- No package requires `modules/class-pos-module.php`.
- No package requires a PersonalOS base plugin.

## GitHub Actions And Build Artifacts

The current GitHub Actions setup builds a single monolithic plugin ZIP:

- `.github/workflows/release.yml` uploads one release asset named `wp-personal-os.zip`.
- `.github/workflows/pr-preview.yml` builds and publishes one PR preview ZIP named `wp-personal-os.zip`.
- `.github/actions/build-plugin/action.yml` runs root `npm run build` and `@wordpress/scripts plugin-zip`.
- Root `package.json` currently describes one plugin package through its `files` list.

The split must add multi-plugin build support before packages are considered release-ready.

Recommended CI shape:

- Add or extend a composite action that accepts `package-dir`, `plugin-slug`, and `zip-name`.
- Build shared assets once when possible, then package each plugin independently from `packages/<plugin-slug>/`.
- Produce one ZIP per plugin:
  - `personalos-notes.zip`
  - `personalos-readwise.zip`
  - `personalos-evernote.zip`
  - `personalos-todo.zip`
  - `personalos-ai-chat.zip`
- Update PR builds to use a matrix over the package list and upload each ZIP as a separate artifact.
- Update release builds to upload all package ZIPs as release assets.
- Update PR preview publishing to expose separate URLs for each plugin ZIP; if a Playground link is kept, provide a blueprint that installs the relevant package ZIPs explicitly.
- Keep a temporary monolith ZIP only while migration is incomplete, and label it as compatibility/legacy.

Package ZIP acceptance criteria:

- Each ZIP has exactly one top-level plugin directory matching the plugin slug.
- Each ZIP contains a valid plugin header.
- Each ZIP activates on its own.
- No ZIP contains unrelated sibling package source.
- No package ZIP declares `Requires Plugins` for another PersonalOS plugin.
- CI fails if a package's declared ZIP contents are missing built assets, PHP bootstrap files, or package-local vendor files.

## JavaScript Build And Editor Surface Split

The current NPM/webpack setup is also monolithic:

- `webpack.config.js` defines one custom entry, `index: './src/index.js'`.
- `src/index.js` imports the Notes sidebar, notebook UI, and TODO UI into the same `build/index.js`.
- `personalos.php` registers one `pos` script from `build/index.js` and one `build/style-index.css`.
- Notes, Readwise, OpenAI, and TODO editor assets currently live under one root `src/` tree.
- `src-chatbot` is a separate Next/React app that builds to `src-chatbot/build/chatbot.*`, but it is still conceptually owned by the current OpenAI module.

The split must migrate editor scripts, blocks, and sidebars to package-local builds. Do not keep a shared root `pos` editor bundle as a hidden dependency.

Package ownership:

| Package | Frontend/editor ownership |
| --- | --- |
| PersonalOS Notes | Notes sidebar, note search/drag UI, `pos/note` block, note-like Guidelines filters |
| PersonalOS Readwise | `pos/readwise` and `pos/book-summary` blocks, Readwise-specific document panels if still useful |
| PersonalOS Evernote | Evernote-specific document panels and redirect/open UI, if still useful |
| PersonalOS TODO | TODO admin/DataViews app, task editor forms, ICS admin affordances |
| PersonalOS AI Chat | Chatbot app, AI message/tool blocks, model/prompt UI, artifact UI |

Migration rules:

- Replace root `src/index.js` with package-local entry points, for example `packages/personalos-notes/src/index.js`, `packages/personalos-todo/src/index.js`, and `packages/personalos-ai-chat/src/index.js`.
- Each package should enqueue only its own generated asset file and asset metadata.
- Each package should register only the blocks it owns.
- Shared UI utilities should move into package-local copies unless they become a small real shared npm/composer package. Do not keep `src/components/*` as a runtime dependency between plugin ZIPs.
- The current `notebook` UI should not be carried forward as a cross-package dependency. Notes/TODO should filter by `wp_guideline_type` and package-owned meta instead.
- AI-dependent blocks such as `pos/img-describe`, `pos/ai-message`, and `pos/ai-tool` should either move to AI Chat or be deferred with the other satellite AI features.
- Readwise/Evernote document panels should not require the Notes sidebar; they should read source/provenance meta from the current Guideline post.
- TODO must not be bundled into Notes. Its admin UI and task forms belong to the TODO package.
- The package ZIP build must include each package's built JS/CSS and `*.asset.php` files.

Frontend acceptance criteria:

- Activating only PersonalOS Notes registers no TODO, Readwise, Evernote, or AI Chat scripts.
- Activating only PersonalOS TODO registers no Notes sidebar script.
- Activating only PersonalOS Readwise registers only Readwise blocks/panels.
- Activating only PersonalOS AI Chat registers the chat app and AI-owned blocks without requiring Notes or TODO.
- No package enqueues root `build/index.js`.
- No package references source files from a sibling package at runtime.
- CI fails when a package has source changes but its built assets are missing from the ZIP.

## What Gets Removed Or Deferred

Remove from the v1 extraction path:

- Perplexity.
- IMAP.
- Email responder.
- Podcast.
- ElevenLabs.
- Transcription.
- Voice/realtime/Ollama compatibility.
- Standalone ICS.

Defer unless specifically needed:

- Slack.
- Bucketlist as its own plugin.
- Podcast prompts and podcast-specific Guidelines behavior.
- Provider-specific OpenAI Responses continuation beyond what AI Chat truly needs.

If any deferred feature returns, it should follow the same rules:

- Store durable data in Guidelines.
- Use Connectors/AI Client for AI.
- Register abilities for AI-callable actions.
- Avoid hard dependencies on PersonalOS sibling plugins.

## Test Plan

Documentation-only branch:

- Verify branch name with `git branch --show-current`.
- Verify only documentation/lesson changes are present.
- Run `git diff --check`.

Implementation branches:

- Run `npm run test:unit:backend` after each phase.
- Run `composer run lint -- "file/path"` for changed PHP files.
- Add activation tests for each extracted plugin with no sibling plugins active.
- Add Guidelines availability tests for missing `wp_guideline` and `wp_guideline_type`.
- Add migration tests for old `notes`, `todo`, `prompts-chat`, `ai-memory`, and `ai-chats` rows.
- Add TODO migration tests that create separate `wp_guideline` task artifacts and preserve old `todo` CPT rows during the first pass.
- Add idempotency tests for Readwise/Evernote external IDs and source hashes.
- Add TODO ICS export tests against `wp_guideline` task data.
- Add AI Chat tests proving tools are discovered through `wp_get_abilities()`.
- Add permission tests for private Guidelines reads/writes.

## Risks And Mitigations

| Risk | Mitigation |
| --- | --- |
| Guidelines is not available in the target runtime | Feature-detect, show admin notices, and avoid private replacement unless explicitly chosen |
| `wp_guideline_type` becomes overloaded | Use canonical terms plus source/routing terms; keep meta for behavior and provenance |
| Existing Notes UI assumes `notes` CPT | Migrate additively and convert UI layer before deleting old CPT behavior |
| TODO semantics are richer than plain Guidelines documents | Use one Guideline post per task, register REST-visible task meta, and expose operational actions through Abilities |
| Importers duplicate rows | Use external IDs, source hashes, and idempotent upserts |
| AI Chat tries to become another central runtime | Limit it to chat/agent behavior; use Abilities and Guidelines for integration |
| Removing satellite modules loses useful experiments | Park them explicitly and revive only as independent Guidelines/AI Client plugins |

## Decision Defaults

- V1 plugin set is Notes, Readwise, Evernote, TODO, and AI Chat.
- Guidelines is the shared storage layer for notes, imports, TODOs, prompts, memory, artifacts, and conversations.
- Readwise and Evernote do not depend on Notes.
- TODO stores each task as its own `wp_guideline` post tagged `artifact` and `todo`; it includes ICS.
- AI Chat replaces the OpenAI module.
- Perplexity, IMAP, podcast, ElevenLabs, transcription, and voice/realtime features are removed or deferred.
- No mandatory PersonalOS base plugin.
- No `Requires Plugins` dependency between PersonalOS packages.

## References

- [PersonalOS PR #61: Migrate OpenAI tools to WordPress Abilities API, Notes as prompts everywhere](https://github.com/artpi/PersonalOS/pull/61)
- [WordPress 7.0 Connectors API dev note](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)
- [WordPress 7.0 AI Client dev note](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)
- [Guidelines Lands in Gutenberg 22.7](https://make.wordpress.org/ai/2026/03/23/guidelines-lands-in-gutenberg-22-7/)
- [Gutenberg issue #77230: Guidelines support for skills, memory, and plans](https://github.com/wordpress/gutenberg/issues/77230)
- `/Users/artpi/GIT/wp-agent-skills/skills/wp-guideline/SKILL.md`
