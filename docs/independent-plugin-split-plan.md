# Lean Personal OS Plugin Split Plan

Date: 2026-06-10

## Executive Summary

The split should be smaller and more opinionated than the original module-by-module extraction plan.

Instead of extracting every current PersonalOS module, v1 should become a small family of independently installable plugins that share WordPress-native storage:

- **Notes**: UI and management for note-like Knowledge.
- **Sync for Readwise**: independent Readwise sync into Knowledge.
- **Sync for Evernote**: independent Evernote sync into Knowledge.
- **TODO**: task/checklist UI over separate Knowledge task artifacts, with ICS folded in.
- **AI Chat**: chat/agent UI using Knowledge, Abilities, WordPress AI Client, and Connectors.

Everything else should be removed from v1 or parked for later:

- No OpenAI module.
- No Perplexity module.
- No IMAP/email responder module.
- No podcast or ElevenLabs module.
- No standalone ICS module.
- No transcription/voice module in v1.
- No mandatory base plugin and no `Requires Plugins` dependencies between PersonalOS plugins.

The core design change is that **Knowledge becomes the shared data layer for notes, synced content, TODOs, prompts, memories, artifacts, and conversations**. TODOs should be separate Knowledge posts tagged with `artifact` and `todo` so other Knowledge-aware tools, such as Obsidian sync, can pull them without knowing about the TODO plugin.

## Target Plugin Set

| Plugin | Preferred display name | Responsibility | Required runtime surface | Optional integrations |
| --- | --- | --- | --- | --- |
| `personal-notes` | Personal Notes | Display, edit, search, and organize note-like Knowledge | Knowledge surface | Readwise/Evernote content appears automatically because it is stored as Knowledge |
| `personal-readwise-sync` | Personal Readwise Sync | Sync Readwise highlights/articles into Knowledge | Knowledge surface | Notes UI can display synced rows |
| `personal-evernote-sync` | Personal Evernote Sync | Sync Evernote notes into Knowledge | Knowledge surface | Notes UI can display synced rows |
| `personal-todo` | Personal TODO | Manage task artifacts, checklists, completion state, and ICS feeds | Knowledge surface | AI Chat can use TODO abilities when active |
| `personal-ai-chat` | Personal AI Chat | Chat UI, agent orchestration, prompt/memory/artifact loading, ability execution | Knowledge plus AI Client/Connectors for live AI | TODO/Notes/other plugins only through Knowledge and Abilities |

Use Personal-branded names for WordPress.org submission, and short names inside the product UI. For example, the plugin directory name should be `Personal Notes`, but the admin menu/sidebar can simply say `Notes`.

Avoid submitting `Notes` or `TODO` as standalone plugin names. They are too generic for Plugin Directory review, and the `notes` and `todo` slugs already exist on WordPress.org. `Personal Notes` and `Personal TODO` are more strategic because they keep the Personal OS product family recognizable without requiring a shared runtime plugin.

Tradeoff: `Personal` is friendlier and less infrastructure-heavy than `Knowledge`, but it is broader. Counter this in plugin subtitles, descriptions, screenshots, and readmes by saying these plugins manage a personal operating layer inside WordPress using the `wp_knowledge` and `wp_knowledge_type` APIs through the bridge.

The preferred sync plugin display names are `Personal Readwise Sync` and `Personal Evernote Sync`, with package slugs `personal-readwise-sync` and `personal-evernote-sync`. If Plugin Directory review treats Readwise or Evernote as protected marks that must appear after a connector, fall back to `Personal Sync for Readwise` / `personal-sync-for-readwise` and `Personal Sync for Evernote` / `personal-sync-for-evernote`.

This keeps every plugin independently useful:

- Readwise can sync without Notes installed.
- Evernote can sync without Notes installed.
- Notes can manage manual notes without Readwise or Evernote installed.
- TODO can manage tasks and expose ICS without AI Chat installed.
- AI Chat can run with whatever Knowledge and Abilities exist; it does not need TODO, Notes, Readwise, or Evernote.

### WpApp Presentation Layer

The three destination-style packages also ship as private WpApp applications while remaining normal standalone WordPress plugins:

| Package | App route | Capability | App surface |
| --- | --- | --- | --- |
| Personal Notes | `/notes/` | `edit_posts` | Notes editor, search, filters, and Knowledge organization |
| Personal TODO | `/todo/` | `edit_posts` | Task creation, editing, completion, recurrence, and ICS settings |
| Personal AI Chat | `/ai-chat/` | `edit_posts` | Conversation list, transcript, composer, and discovered abilities |

WpApp is a presentation and routing layer only. Package REST APIs, Abilities, settings, sync jobs, and Knowledge storage remain the domain contracts. The apps reuse those contracts instead of introducing app-only storage or sibling calls.

Personal Readwise Sync and Personal Evernote Sync are background integrations rather than app destinations. They keep conventional wp-admin Settings pages for credentials, scope, sync status, and manual controls; their imported content appears through Knowledge consumers such as Personal Notes. They do not register frontend routes or My Apps entries.

The Notes, TODO, and AI Chat release ZIPs bundle the same pinned WpApp 1.3.2 GPL runtime, keep My Apps integration enabled, and use WordPress capabilities on their routes. Because WpApp requires PHP 7.4, those three packages declare `Requires PHP: 7.4`; the two sync packages retain PHP 7.2.24. Package verification must reject missing or mismatched WpApp files in app ZIPs and reject accidental WpApp files in sync ZIPs.

## Dependency Model

### Runtime Surfaces

Each v1 plugin should integrate only with shared WordPress runtime surfaces:

- Knowledge runtime: `wp_knowledge` CPT, `wp_knowledge_type` taxonomy, and `/wp/v2/knowledge`.
- Standard post meta, options, REST, cron, capabilities, actions, and filters.

Package code should not hardcode the concrete post type, taxonomy, or REST collection outside the shared bridge. TODO uses the resolved Knowledge post type as the durable task store. Each actionable task should be its own Knowledge post with `artifact` and `todo` terms. Do not use one giant Knowledge post as the TODO database.

AI Chat additionally needs WordPress 7.0 AI surfaces for live model calls:

- Connectors screen for provider credentials.
- AI Client via `wp_ai_client_prompt()`.
- Abilities API in WordPress core for agent-callable operations.

If AI Client or provider credentials are missing, AI Chat should activate and show setup guidance rather than fatal.

The current monolith `.wp-env.json` mounts `WordPress/abilities-api`, but that is pre-split/pre-WordPress-7.0 scaffolding. The split target should not install the standalone Abilities API plugin by default because WordPress 7.0 already includes Abilities. Keep a separate legacy compatibility config only if we deliberately test a pre-7.0 runtime.

### WordPress.org Submission Baseline

Every package should be submittable to the WordPress.org Plugin Directory as soon as it is split. Do not treat the first split as an internal-only package format.

Each package needs:

- A single main plugin file with a complete plugin header: `Plugin Name`, `Description`, `Version`, `Requires at least`, `Requires PHP`, `Author`, `License`, `License URI`, and `Text Domain`.
- A `readme.txt` with matching display name, stable numeric `Stable tag`, no more than five tags, a short description under the directory limit, and no keyword stuffing.
- GPL-compatible licensing for all PHP, JavaScript, CSS, images, icons, vendor packages, and generated assets.
- Corresponding source and build instructions for any minified or built JavaScript/CSS included in the ZIP.
- No remote executable code, CDN-loaded admin scripts, obfuscated code, hidden iframes, or activation-time outbound requests.
- Clear external service disclosure where needed: Readwise, Evernote, and AI Chat must document what service is contacted, what data is sent, what credentials/settings are required, and link to service terms/privacy pages.
- A data retention/uninstall policy: by default, synced Knowledge and user-created TODOs/notes should remain unless the user explicitly chooses cleanup.
- A complete working workflow at submission time. A plugin that only says "install another PersonalOS package" is not ready.

Do not add `Requires Plugins` for PersonalOS sibling plugins in v1. The only acceptable hard dependency is the shared WordPress runtime surface the package actually uses. If Knowledge or AI Client is missing, the package should activate and show a focused admin notice or setup screen.

Store-readiness acceptance criteria:

- The ZIP installs and activates on a clean WordPress site with no sibling PersonalOS plugins active.
- Plugin Check/readme validation passes for each package before release.
- The package privacy disclosure matches its network behavior.
- The package does not expose upsell, trialware, disabled premium-only UI, or "coming soon" admin screens.
- Plugin names, slugs, text domains, option names, script handles, and PHP symbols are unique and prefixed.

### No PersonalOS Runtime Dependency

Do not create a shared PersonalOS Core plugin for v1. Shared implementation code is allowed only as build-time vendored helper classes bundled into each generated plugin ZIP.

Avoid:

- Shared `POS_Module` base class.
- Central module registry.
- `POS::get_module_by_id()`.
- Constructor injection between plugins.
- `Requires Plugins: personalos-core`.

Use:

- Direct WordPress APIs.
- Shared source helpers copied into each package at build time.
- Small package-local helpers where code is genuinely package-specific.
- Hooks and filters where a plugin needs an extension point.
- Abilities API for agent-callable operations.
- Knowledge queries for durable context.

### Bundled Shared Helpers

Use a shared source directory in the monorepo for helper code that every plugin may need, but never ship it as a required plugin.

Recommended shared source:

```text
shared/
  php/
    class-personalos-plugin-base.php
    class-personalos-sync-plugin-base.php
    class-personalos-knowledge-bridge.php
    class-personalos-knowledge-type-vocabulary.php
    class-personalos-settings-helper.php
    class-personalos-admin-notice-helper.php
    class-personalos-assets-helper.php
    class-personalos-plugin-health.php
```

Build behavior:

- Copy the shared helper files into each package ZIP under `includes/shared/`.
- Keep helper classes dependency-free and compatible with every target package.
- Wrap each shared class definition with `if ( ! class_exists( 'Class_Name' ) )` so multiple PersonalOS plugins can be active together.
- Prefer stable, prefixed class names such as `PersonalOS_Plugin_Base`, `PersonalOS_Sync_Plugin_Base`, `PersonalOS_Knowledge_Bridge`, and `PersonalOS_Settings_Helper`.
- Keep helper public methods stable across simultaneously active package versions.
- Do not autoload shared helper files through Composer in a way that fatals when the same class exists.

### Shared Base Classes

Yes, the split should keep the good parts of the current `POS_Module` / `External_Service_Module` pattern, but as bundled base classes rather than a required base plugin.

Treat `modules/class-pos-module.php` as source material, not code to copy unchanged. The useful ideas are:

- Settings schema and option-name conventions.
- WP-CLI command registration.
- Sync hook naming, cron registration, and one-off continuation scheduling.
- Logging to `error_log()` and WP-CLI when present.
- Small wrappers for REST-visible meta and package-local block registration.

Do not carry forward the monolith assumptions:

- No global `POS::$modules` registry.
- No `POS::get_module_by_id()`.
- No constructor injection between sibling plugins.
- No root `build/{$module_id}` asset path.
- No default `show_in_menu => personalos` for CPTs.
- No hardcoded `pos` WP-CLI namespace for every package.
- No dependency on `POS_CPT_Rest_Controller`.
- No constructor that automatically registers everything before the package bootstrap has checked runtime state.

Recommended base class shape:

- `PersonalOS_Plugin_Base`: bundled in every package that wants shared plumbing.
- `PersonalOS_Sync_Plugin_Base`: extends `PersonalOS_Plugin_Base` and is used by Personal Readwise Sync, Personal Evernote Sync, and any future sync package.
- `PersonalOS_Knowledge_Bridge`: either composed inside the base class or exposed through a lazy `$this->knowledge()` method. Rolling the bridge into the base class API is good, but keep the bridge implementation as its own class so Knowledge detection remains testable and replaceable.
- `PersonalOS_Settings_Helper`: implements package-scoped site settings and user settings, borrowing the scope idea from PR #72 without carrying over the monolith settings screen.

`PersonalOS_Plugin_Base` should own only package-local plumbing:

- Plugin slug, display name, text domain, version, main file, package directory, package URL.
- Storage key naming such as `personal_notes_<setting>` or `personal_readwise_sync_<setting>`.
- `get_setting()`, `update_setting()`, and Settings API registration for this one package, with explicit `scope => user` or `scope => site`.
- `register_cli_command()` with a package-local namespace, for example `personal notes ...` or `personal readwise-sync ...`.
- `register_block_from_package()` using the package directory, not the monolith root build.
- `register_post_meta()` / `register_term_meta()` helpers with explicit object subtype.
- `log()` with package slug context.
- `knowledge()` / `ensure_knowledge_terms()` convenience methods that delegate to `PersonalOS_Knowledge_Bridge` and `PersonalOS_Knowledge_Type_Vocabulary`.

### Knowledge Runtime Resolution

`PersonalOS_Knowledge_Bridge` is the comms layer between the split plugins and the underlying Knowledge implementation. It centralizes runtime availability and metadata registration without carrying aliases for superseded APIs.

Runtime contract:

1. Post type: `wp_knowledge`.
2. Type taxonomy: `wp_knowledge_type`.
3. REST collection: `/wp/v2/knowledge` or the route registered for `wp_knowledge`.
4. Source/provenance meta: `knowledge_source` when registered, otherwise package-prefixed provenance meta.
5. If the Knowledge runtime is unavailable, activate cleanly and show setup guidance instead of fataling.

The bridge must not fall back to old PersonalOS `notes`, `todo`, or `notebook` storage or to superseded runtime aliases.

The base class should expose this through methods rather than constants:

- `$this->knowledge()->post_type()`
- `$this->knowledge()->type_taxonomy()`
- `$this->knowledge()->rest_base()`
- `$this->knowledge()->source_meta_key()`
- `$this->knowledge()->register_post_meta( $key, $args )`
- `$this->knowledge()->register_type_meta( $key, $args )`

Package services should query and write through the resolved values. For example, Personal TODO should create tasks in `$this->knowledge()->post_type()` and assign terms in `$this->knowledge()->type_taxonomy()` so availability checks and metadata registration remain centralized.

`PersonalOS_Sync_Plugin_Base` should own sync mechanics only:

- `get_sync_hook_name()` using the plugin slug, for example `personal_readwise_sync_cron`.
- `register_sync( $interval )`.
- `unschedule_sync()`.
- `schedule_single_sync( $delay )` for paginated sync continuation.
- `get_sync_cursor()` / `update_sync_cursor()` helpers if useful.
- An abstract or overridable `sync()` method.

Base class constraints:

- The base class must be stable and additive. Because each plugin bundles its own copy with `class_exists` guards, the first active plugin may provide the class for all active Personal packages.
- If a breaking helper API change is unavoidable, either rebuild all split packages together or introduce a new class name such as `PersonalOS_Plugin_Base_2`.
- The base class may offer convenience methods, but it must not make one package incomplete without another package.
- Package bootstraps should instantiate one package class and explicitly call a `register()` method on `plugins_loaded` or `init` after runtime checks. Avoid heavy side effects at file load time.

Shared helper responsibilities:

- Knowledge bridge: one place to detect `wp_knowledge` and `wp_knowledge_type`, resolve the registered REST route, and register shared metadata.
- Source meta compatibility: use the runtime's registered `knowledge_source` meta and fall back to package-prefixed provenance meta when it is unavailable.
- Term resolution and idempotent creation for canonical type, source, and PARA/collection terms.
- Type label registration through the `wp_knowledge_types` runtime label API so shared terms such as `todo`, `note`, `project`, `area`, and `resource` have readable labels.
- Knowledge type vocabulary ownership: one bundled helper defines the shared term tree, normalizes existing term parents, and expands parent terms to child IDs for queries.
- Scoped settings ownership: one helper stores `scope => site` settings in options and `scope => user` settings/state in user meta using package-scoped keys.
- Token-to-user lookup for package-owned token settings, so private feeds or callbacks can resolve a request token to the owning WordPress user before querying Knowledge.
- Capability and REST permission checks for shared data access.
- Settings API wrappers for package-local options pages.
- Admin notices for missing Knowledge, AI Client, Connectors, or setup state.
- Asset registration helpers for package-local `*.asset.php` metadata.

Shared helper constraints:

- No central registry.
- No cross-plugin service locator.
- No shared options table that makes one package own another package's configuration.
- No activation-time outbound HTTP.
- No feature code that would make a package incomplete without a sibling plugin.

Acceptance criteria:

- Activating all five plugins together produces no class redeclaration fatals.
- Activating all five plugins together works no matter which package's bundled base class loads first.
- Updating one package with a newer helper version does not break older sibling packages.
- Knowledge API changes can be handled primarily in the shared bridge and then rebuilt into each package ZIP.

### Soft Inter-Plugin Relationships

The plugins should interoperate through data and capabilities, not object references.

```text
Readwise  -> resolved Knowledge post type <- Notes UI
Evernote  -> resolved Knowledge post type <- Notes UI
TODO UI   -> resolved Knowledge post type <- Obsidian / other Knowledge consumers
TODO UI   -> Abilities    <- AI Chat
AI Chat   -> Knowledge + Abilities + AI Client + Connectors
```

Examples:

- Readwise creates Knowledge rows with `artifact`, `note`, `readwise`, `synced`, and any relevant child term under `resource`.
- Evernote creates Knowledge rows with `artifact`, `note`, `evernote`, `synced`, and any relevant child term under `resource`.
- Notes queries Knowledge rows tagged with `note`, regardless of source.
- TODO creates one Knowledge row per actionable task, tagged with `artifact` and `todo`.
- TODO stores workflow fields in registered REST-visible post meta and keeps title/content useful for consumers that only understand Knowledge.
- TODO registers abilities such as list/create/update/complete when the Abilities API exists.
- AI Chat discovers TODO through `wp_get_abilities()`, not through a PHP class.

## Knowledge Storage Model

Use current WordPress Knowledge implementation guidance and verify it against the `wp_knowledge` API exposed by the pinned runtime fixture.

Knowledge uses the `wp_knowledge_type` taxonomy, so rows can and should receive multiple canonical, domain, and source terms.

### Canonical Terms

Use the narrowest existing canonical term where possible:

- `artifact`: notes, synced external documents, TODO records, AI-generated durable outputs, long-form source material.
- `memory`: remembered facts and durable user/site context.
- `skill`: reusable procedural instructions, prompts, agent workflows.

Do not use `plan` for TODO items in v1. TODOs are actionable artifacts, not planning documents. If a future workflow needs durable agent planning documents, revisit `plan` for those documents only.

### Shared Routing And PARA Terms

Create or ensure additional Knowledge type terms as routing, source, and PARA-style collection terms.

Do not create a separate PersonalOS-only collection taxonomy in v1. Using the resolved Knowledge type taxonomy for PARA-style organization makes the data useful to unrelated Knowledge-aware plugins. A tool that knows only Knowledge can discover tasks, notes, projects, areas, and inbox items by querying one shared taxonomy.

Ownership:

- The WordPress Knowledge runtime owns the type taxonomy registration.
- Bundled shared helper `PersonalOS_Knowledge_Type_Vocabulary` owns the shared term vocabulary, parent normalization, label registration, and parent-to-child query expansion.
- Every PersonalOS package may call the shared helper before it writes or queries Knowledge. This keeps Notes, TODO, Readwise, Evernote, and AI Chat independently installable.
- Personal Notes owns the full collection/PARA management UI: browse, create, rename, star, and organize project/area/resource child terms.
- Personal TODO owns only the task-facing subset it needs: assign/filter by `inbox`, `now`, `later`, `follow-up`, and project/area child terms.
- Sync plugins own only source/resource assignments such as `readwise`, `evernote`, `synced`, and resource child terms.
- AI Chat consumes the vocabulary through Knowledge and Abilities; it must not require Notes or TODO PHP classes.

Source/routing terms:

- `personalos`
- `note`
- `todo`
- `readwise`
- `evernote`
- `ai-chat`
- `conversation`
- `daily-note`
- `synced`
- `manual`

PARA/collection terms:

- `status`
- `inbox`
- `now`
- `later`
- `follow-up`
- `project`
- `area`
- `resource`
- `archive`
- `starred`

Recommended hierarchy:

```text
artifact
  note
    daily-note
  todo
  conversation
source
  manual
  synced
  readwise
  evernote
  ai-chat
status
  inbox
  now
  later
  follow-up
project
  <user-created project terms>
area
  <user-created area terms>
resource
  reference
archive
  <user-created archive terms>
starred
```

Container-only rule:

`status`, `project`, `area`, `resource`, and `archive` are category containers. They should exist to create hierarchy, improve UI organization, and support parent-level query expansion, but they should never be applied directly to Knowledge posts. Apply only their children:

- Status rows use children such as `inbox`, `now`, `later`, and `follow-up`.
- Project rows use user-created child terms under `project`.
- Area rows use user-created child terms under `area`.
- Resource/reference rows use child terms under `resource`, such as `reference` or user-created resource collections.
- Archived rows use child terms under `archive` if archive classification is needed.

The helper should enforce this in term-picking APIs by hiding container-only terms from assignable lists, rejecting direct assignment helpers for those slugs, and expanding parent filters to children when a caller asks for a container.

Sorting convention:

The old PARA starter content used numbered top-level labels so WordPress core taxonomy pickers, simple REST consumers, and alphabetical dropdowns showed the buckets in the intended order. Keep that behavior, but do not use labels as identifiers.

| Canonical slug | Display label | Parent | Notes |
| --- | --- | --- | --- |
| `status` | `1-Status` | root | Container for task/status buckets |
| `inbox` | `Inbox` | `status` | Default capture bucket |
| `now` | `NOW` or `Now` | `status` | Current-focus bucket; prefer one spelling consistently in UI |
| `later` | `Later` | `status` | Deferred bucket if enabled |
| `follow-up` | `Follow Up` | `status` | Waiting/follow-up bucket if enabled |
| `project` | `2-Projects` | root | Container only; never assign directly to posts |
| `area` | `3-Areas` | root | Container only; never assign directly to posts |
| `resource` | `4-Resources` | root | Container only; never assign directly to posts |
| `archive` | `5-Archive` | root | Container only; never assign directly to posts |
| `starred` | `Starred` | root | Optional shortcut/filter term; `flag = star` can remain a UI hint |

The vocabulary helper should resolve by slug and term ID, not by display label. It should idempotently repair parent relationships and display labels for these canonical terms. Because v1 does not need legacy PersonalOS data compatibility, the new split can use canonical singular slugs such as `project`, `area`, and `resource`; if a future importer reads old starter-content terms such as `projects`, `areas`, or `resources`, it can map them as aliases inside the helper instead of spreading plural special cases through each plugin.

The runtime should treat the resolved Knowledge type taxonomy as hierarchical. If a term already exists as a flat term, normalize it by setting its parent with `wp_update_term()` rather than deleting and recreating it.

Moving a term under a parent does not change the term's slug or term ID, and existing object relationships continue to point at the same term. A row tagged with `now` is still directly reachable by the `now` term after `now` becomes a child of `status`.

Assign term IDs, not raw strings, when setting hierarchical Knowledge type terms.

Rules:

- Keep canonical data-kind terms such as `artifact`, `memory`, and `skill` on every row, even when subtype terms are nested beneath them.
- Add source terms such as `readwise`, `evernote`, `manual`, or `ai-chat` for provenance and filtering.
- Add PARA terms only when they help route work or retrieval.
- Use `project` and `area` parent terms for grouping; user-created child terms represent the actual project or area.
- Avoid a PersonalOS-only term when a broad term communicates the same thing. `todo`, `inbox`, `now`, and project child terms are more useful to other plugins than `personalos-todo` or `personalos-now`.
- Do not rely on every REST client to include child terms automatically when querying a parent. Package helpers should either query the specific child term or explicitly expand parent terms to child IDs when the UI asks for "all projects", "all areas", or "all status buckets".

This satisfies the idea of a "note" Knowledge type term and the old PARA-inspired notebook behavior without losing compatibility with the broader Knowledge meaning of `artifact`.

### Row Mapping

| Data | Knowledge terms | Owner |
| --- | --- | --- |
| Manual note | `artifact`, `note`, `manual`, optionally `inbox`/project child/area child/resource child terms | Notes |
| Daily note | `artifact`, `note`, `daily-note`, optionally `now` or an area/project child term | Notes |
| Readwise highlight/article | `artifact`, `note`, `readwise`, `synced`, and optional resource child terms | Readwise |
| Evernote note | `artifact`, `note`, `evernote`, `synced`, and optional resource child terms | Evernote |
| TODO item | `artifact`, `todo`, optionally `inbox`/`now`/`later`/`follow-up`/project child/area child terms | TODO |
| Checklist/project task group | multiple `artifact`, `todo` task rows sharing a project/area child term or linked by meta | TODO |
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

- Runtime source meta: use `$this->knowledge()->source_meta_key()` so the bridge uses registered `knowledge_source` meta and otherwise falls back to package-prefixed provenance meta. Use stable values such as a source URL, `native:personal-notes`, or `native:personal-readwise-sync:<external-id>`.
- `_personalos_source`: fallback/source category when `knowledge_source` is unavailable, or supplemental package-local source grouping such as `manual`, `readwise`, `evernote`, `todo`, `ai-chat`.
- `_personalos_external_id`: source-system ID.
- `_personalos_source_url`: source URL.
- `_personalos_synced_at`: last sync timestamp.
- `_personalos_source_hash`: sync/update idempotency.
- `_personalos_chat_provider_response_id`: provider-specific response continuation ID, only where still needed.

Do not use meta as the primary discovery mechanism. Query by the resolved Knowledge type taxonomy first, then use meta to refine.

Do not store PARA category membership in post meta when a Knowledge type term can represent it. Keep meta for operational state such as TODO recurrence/blocking/scheduling, sync hashes, and provider response IDs.

Do not self-register a global unprefixed `knowledge_source` convention if the active runtime does not expose it. Let `PersonalOS_Knowledge_Bridge` centralize detection and fallback behavior so an API shape change is fixed once and then rebuilt into all package ZIPs.

For TODO, keep the old plugin's storage shape as much as possible. Use `wp_knowledge` through the bridge and preserve the current task mechanics:

- Open tasks are `private` or `publish`.
- Scheduled tasks use `post_date` / `post_date_gmt`, `future` status where useful, and a package cron hook.
- Completed tasks may continue to use the trash workflow, with TODO history recorded as comments.
- Recurrence, blocking, and pending moves stay in registered REST-visible post meta.
- Do not invent `todo_status`, `todo_due_at`, `todo_scheduled_at`, `todo_completed_at`, or `todo_priority` for v1 unless the implementation proves it needs them. The old architecture is good enough here, just move it onto Knowledge.

### PR #72 Multi-User Ideas To Carry Forward

[PR #72, "Per-user permissions"](https://github.com/artpi/PersonalOS/pull/72), is worth carrying forward as design input, but not as code to copy unchanged. It was built for the monolith's private CPTs, while the split should rely on Knowledge scoping for Knowledge rows.

Specific PR #72 changes to incorporate:

- `class-pos-settings.php` introduced setting scopes and separate storage for user settings vs global settings. In the split, keep the idea but name the scopes `user` and `site`: user settings/state go to user meta; site settings go to options.
- `modules/class-pos-module.php` added `get_setting( $id, $user_id )`, `update_setting( $id, $value, $user_id )`, `get_user_ids_with_setting()`, `find_user_for_setting_token()`, `run_for_user()`, and per-user sync state helpers. Recreate these as package-safe methods on `PersonalOS_Plugin_Base`, `PersonalOS_Settings_Helper`, and `PersonalOS_Sync_Plugin_Base`.
- `personalos.php` added `use_personalos`, `admin_personalos`, and monolith-specific `map_meta_cap` handling for `notes` and `todo`. Do not copy that as a PersonalOS-wide permission system. Knowledge already handles user scoping; split packages should call `current_user_can( 'read_post'|'edit_post'|'delete_post', $knowledge_id )` and use the Knowledge REST permissions.
- `modules/notes/class-notes-module.php` changed note creation and draft auto-publish to `private`, and filtered dashboard/widget queries by author unless the user has admin access. In the split, default user-created notes, TODOs, synced rows, memories, chat transcripts, and agent-created artifacts to private Knowledge rows authored by the owning user; use published Knowledge only for site-wide/admin-provided artifacts.
- `modules/readwise/class-readwise.php` made `token` and `autotag` user-scoped, looped sync over users with configured tokens, stored `page_cursor` and `last_sync` per user, wrote private notes with `post_author = get_current_user_id()`, and deduped by external ID within that author. Personal Readwise Sync should do the same against the resolved Knowledge post type.
- `modules/evernote/class-evernote-module.php` made `token`, `synced_notebooks`, and `active` user-scoped; reset cached clients between users; stored `usn`, `last_sync`, `last_update_count`, and `cached_data` as per-user sync state; wrote private notes as the current user; and scoped Evernote GUID lookups by author. Personal Evernote Sync should follow that pattern.
- `modules/openai/class-pos-ai-podcast-module.php` and `modules/openai/class-pos-ollama-server.php` resolved request tokens back to a user before serving private data. Personal TODO should use that pattern for per-user ICS tokens, and future tokenized AI or feed endpoints should do the same.
- `modules/imap/class-imap-module.php` treated inbox credentials as site settings, matched message recipients to WordPress users, and dispatched work once per matched user. IMAP is deferred for v1, but this is the right pattern if any future site-level ingestion source needs to create user-owned Knowledge rows.
- Tests added in `SettingScopeTest`, `ModuleTokenMappingTest`, `NotesRestPermissionsTest`, `ReadwiseModuleSyncTest`, `EvernoteModuleSyncTest`, `IMAPModuleTest`, and `CapabilitiesTest` should be translated into split-package tests around the base helpers, Knowledge row permissions, per-user sync loops, and token-to-user resolution.

Artifact visibility rules:

- Per-user artifacts: manual notes, TODOs, Readwise rows, Evernote rows, AI chat transcripts, user memories, and agent-created artifacts default to private Knowledge rows authored by the user. Queries may pre-filter by author for performance, but still need final `current_user_can( 'read_post', $post_id )` checks before returning data.
- Site artifacts: plugin-provided default skills, shared prompts, shared instructions, shared resources, and other admin-curated Knowledge can be published/site-scoped only from an administrator-controlled setup or settings flow. Do not publish site-wide Knowledge on every activation or page load.
- Package settings: credentials and cursors that belong to a person are user settings/state; package version, feature toggles, public defaults, and site-wide service configuration are site settings.
- External tokens: per-user feed/callback tokens must map back to a user before querying Knowledge. Site tokens should be reserved for site-wide data only.

### Current Storage Surface Inventory And Port

The split should preserve the shape of the old plugin where it made the code simple. The important change is the object subtype: old note/task state moves from `notes` and `todo` CPT rows to `wp_knowledge`, and old notebook organization moves from `notebook` terms to `wp_knowledge_type`.

Use the same setting field IDs inside package classes, but generate package-scoped storage keys through `PersonalOS_Plugin_Base::get_setting_storage_key()`. That keeps the module code familiar while avoiding cross-plugin option or user-meta collisions.

Recommended storage key naming:

```text
<package_slug_with_underscores>_<setting_id>
```

Examples, used either as option names for `scope => site` or user meta keys for `scope => user`:

- `personal_readwise_sync_token`
- `personal_readwise_sync_last_sync`
- `personal_evernote_sync_synced_notebooks`
- `personal_todo_ics_token`
- `personal_ai_chat_default_prompt`

#### Options And Settings

| Current option pattern | Current owner | New owner | New storage key | Decision |
| --- | --- | --- | --- | --- |
| `pos_data_version` | Monolith | Each package | `personal_notes_data_version`, `personal_todo_data_version`, etc. | Site option; replace with per-package schema/version option if needed |
| `notes_user` | Notes | none | none | Do not port. PR #72 removed this pattern; sync ownership comes from the user whose token/settings are being processed |
| `readwise_token` | Readwise | Personal Readwise Sync | `personal_readwise_sync_token` | User setting; keep setting ID `token`; sync loops over users with this key |
| `readwise_autotag` | Readwise | Personal Readwise Sync | `personal_readwise_sync_autotag` | User setting; keep as selected assignable Knowledge type child term ID or slug |
| `readwise_page_cursor` | Readwise | Personal Readwise Sync | `personal_readwise_sync_page_cursor` | User sync state; keep for paginated sync continuation |
| `readwise_last_sync` | Readwise | Personal Readwise Sync | `personal_readwise_sync_last_sync` | User sync state; keep for incremental sync |
| `evernote_token` | Evernote | Personal Evernote Sync | `personal_evernote_sync_token` | User setting; keep setting ID `token`; sync loops over users with this key |
| `evernote_synced_notebooks` | Evernote | Personal Evernote Sync | `personal_evernote_sync_synced_notebooks` | User setting; keep the concept; values remain external notebook/tag IDs, not Notes plugin terms |
| `evernote_active` | Evernote | Personal Evernote Sync | `personal_evernote_sync_active` | User setting; keep as pause/resume switch |
| `evernote_usn` | Evernote | Personal Evernote Sync | `personal_evernote_sync_usn` | User sync state; keep as sync cursor |
| `evernote_last_sync` | Evernote | Personal Evernote Sync | `personal_evernote_sync_last_sync` | User sync state; keep for sync state |
| `evernote_last_update_count` | Evernote | Personal Evernote Sync | `personal_evernote_sync_last_update_count` | User sync state; keep for idempotent sync checks |
| `evernote_cached_data` | Evernote | Personal Evernote Sync | `personal_evernote_sync_cached_data` | User sync/UI state; keep if the new sync UI still needs cached notebook/tag choices |
| `ics_token` | ICS | Personal TODO | `personal_todo_ics_token` | User setting; fold into TODO and resolve feed requests from token to user before querying Knowledge |
| `openai_api_key` | OpenAI | none | none | Remove; use WordPress Connectors and AI Client |
| `openai_prompt_describe_image` | OpenAI | Personal AI Chat or deferred image feature | Knowledge `skill` row if revived | Do not keep as provider module option |
| `openai_ollama_auth_token` | OpenAI/Ollama compatibility | none | none | Remove/defer with voice/realtime/Ollama compatibility |
| `ai-podcast_*`, `elevenlabs_api_key`, `perplexity_api_token`, `slack_*`, `imap_*`, `imap_last_email_id` | Deferred/removed modules | none in v1 | none | Do not port in v1 |

Keep site settings as separate options, like the current `POS_Module::get_setting_option_name()` pattern, not as one serialized mega-option. Keep user settings and sync state as separate user-meta keys. The old approach makes package-local settings easy to inspect with WP-CLI and easy to delete on uninstall, and PR #72 showed why user-owned credentials and cursors must not be site-wide options.

#### Post Meta On Knowledge Rows

Register these through the bridge, for example `$this->knowledge()->register_post_meta( ... )`, so the object subtype is consistently `wp_knowledge`. Keep `show_in_rest => true` for fields that the UI or external Knowledge consumers should see.

| Current key | Current object | New object | Owner | Decision |
| --- | --- | --- | --- | --- |
| `url` | `notes`, `todo`, and attachments by convention | resolved Knowledge post type | Shared helper | Keep as the human/source URL. It is intentionally broad and useful to non-PersonalOS consumers |
| `readwise_id` | `notes` | resolved Knowledge post type | Personal Readwise Sync | Keep; use for idempotent upsert and external lookup |
| `readwise_category` | `notes` | resolved Knowledge post type | Personal Readwise Sync | Keep for source category; also tag with `readwise`, `synced`, and relevant resource child terms |
| `readwise_author` | `notes` | resolved Knowledge post type | Personal Readwise Sync | Keep and register; currently written but not registered |
| `evernote_guid` | `notes` and Evernote attachments | resolved Knowledge post type and attachments | Personal Evernote Sync | Keep for note/resource identity |
| `evernote_content_hash` | `notes` and attachments | resolved Knowledge post type and attachments | Personal Evernote Sync | Keep for idempotency/content rewrite checks |
| `reminders_id` | `todo` | resolved Knowledge post type | Personal TODO | Keep if Reminders import/export remains; otherwise leave dormant but registered only when used |
| `pos_blocked_by` | `todo` | resolved Knowledge post type | Personal TODO | Keep; points to another TODO Knowledge post ID |
| `pos_blocked_pending_term` | `todo` | resolved Knowledge post type | Personal TODO | Keep; store a resolved Knowledge type term slug or ID to apply when unblocked |
| `pos_recurring_days` | `todo` | resolved Knowledge post type | Personal TODO | Keep recurrence interval exactly as today |
| `pos_model` | prompt notes | resolved Knowledge rows tagged `skill` and `ai-chat` | Personal AI Chat | Keep; prompt/persona Knowledge can still choose a model |
| `pos_chat_prompt_id` | chat conversation notes | resolved Knowledge rows tagged `conversation` and `ai-chat` | Personal AI Chat | Keep; points to a prompt/persona Knowledge post ID |
| `pos_last_response_id` | chat conversation notes | resolved Knowledge rows tagged `conversation` and `ai-chat` | Personal AI Chat | Keep only while AI Client still needs provider response continuation |
| `_pos_placeholder_title` | chat conversation notes | resolved Knowledge rows tagged `conversation` and `ai-chat` | Personal AI Chat | Keep for generated chat title flow |
| `pos_transcribe` | attachments | attachments | Deferred transcription/Evernote resource handling | Do not port to v1 unless transcription returns; Evernote can still set it on audio attachments if it deliberately schedules transcription |
| `slack_channel_id` | prompt notes | resolved Knowledge skill rows if Slack returns | Deferred Slack | Do not port in v1 |
| `pos_podcast`, `soundtrack`, `prompt_id` | podcast notes | none in v1 | Deferred podcast | Do not port in v1 |
| `phone`, `email`, `address` | `crm_person` | none in v1 | Deferred CRM | Do not port in v1 |

Prefer these existing keys over newly invented equivalents for v1. For example, use `pos_blocked_by`, not `todo_blocked_by`; use `pos_model`, not `ai_model`. This keeps the code close to the old module code while changing the underlying post type to Knowledge.

For new fields not present in the old plugin, use a package prefix (`pos_` or the package slug prefix) and register the field through the base class with explicit type, auth, sanitize, and REST schema.

#### Term Meta On Knowledge Type Terms

The current `notebook` taxonomy carries both organization and integration bindings. Move that to the resolved Knowledge type taxonomy.

| Current key | Current taxonomy | New taxonomy | Owner | Decision |
| --- | --- | --- | --- | --- |
| `flag` | `notebook` | resolved Knowledge type taxonomy | Personal Notes plus shared vocabulary helper | Keep as multi-value term meta for UI hints such as `star`; do not make discovery depend on it |
| `evernote_notebook_guid` | `notebook` | resolved Knowledge type taxonomy | Personal Evernote Sync | Keep for binding a Knowledge type term to an Evernote notebook/tag |
| `evernote_type` | `notebook` | resolved Knowledge type taxonomy | Personal Evernote Sync | Keep values such as `notebook` and `tag` |

For PARA, terms and hierarchy are the primary model. A project should be a child term under `project`; an area should be a child term under `area`; an inbox/status bucket should be under `status`; and resource/archive membership should use child terms under `resource` or `archive`. The parent terms are containers only and should never be applied directly to Knowledge posts. The old `flag = project` pattern can be retained as a UI hint during the port, but the new canonical structure should be term hierarchy.

The old Bucketlist module only adds a `bucketlist` notebook flag and term. Do not extract it in v1; if it returns, represent it as a Knowledge type term or child hierarchy under TODO/areas rather than a separate runtime.

#### User Meta

| Current key | Current owner | New owner | Decision |
| --- | --- | --- | --- |
| `pos_last_chat_model` | OpenAI chat UI | Personal AI Chat | Keep or rename only if the UI code is being rewritten heavily. It is user preference state, not durable Knowledge |

#### Comments And Derived Fields

Keep TODO activity notes as comments:

- `comment_type = todo_note`
- `comment_post_ID` points to the TODO Knowledge post.
- The same `post_updated` and `set_object_terms` hooks can create history comments after they are scoped to task rows in the resolved Knowledge post type.

Keep these as derived REST fields, not stored meta:

- `blocking`: query TODO Knowledge rows where `pos_blocked_by` equals the current task ID.
- `scheduled`: read the next scheduled cron event for the current task ID.

Cron hook names should be package-scoped, for example `personal_todo_scheduled` instead of `pos_todo_scheduled`, but the behavior can stay the same.

#### Porting Rule

When porting old code to the new base:

- Keep setting IDs, meta keys, and method shapes close to the old modules.
- Change the post type from `notes`/`todo` to `$this->knowledge()->post_type()`.
- Change taxonomy from `notebook` to `$this->knowledge()->type_taxonomy()`.
- Change module option prefixes from old module IDs to package slugs.
- Change root paths and script handles to package-local paths and handles.
- Remove sibling object references; use Knowledge queries, hooks, and Abilities.
- Do not add compatibility reads from old CPTs or old options.

## Current Module Mapping

| Current module/feature | V1 decision |
| --- | --- |
| Notes | Build Personal Notes UI over new Knowledge rows tagged `note`; do not migrate old `notes` CPT data |
| Readwise | Extract as independent Knowledge sync plugin |
| Evernote | Extract as independent Knowledge sync plugin |
| TODO | Extract as standalone Knowledge-backed TODO plugin using one new Knowledge post per task tagged `artifact` and `todo`; do not migrate old `todo` CPT data |
| ICS | Fold into TODO plugin |
| OpenAI | Remove as module; AI Chat uses AI Client + Connectors; do not migrate old provider settings |
| AI Chat | Extract as Personal AI Chat |
| Daily | Rebuild in Notes as a new daily-note view/template |
| Bucketlist | Rebuild in TODO as tasks/lists or defer |
| Perplexity | Remove/defer |
| IMAP | Remove/defer |
| Email responder | Remove/defer with IMAP |
| Slack | Defer; future separate integration if needed |
| Transcription | Remove/defer |
| Podcast | Remove/defer |
| ElevenLabs | Remove/defer with podcast |
| Voice/realtime/Ollama compatibility | Remove/defer unless explicitly revived |

## Revised Extraction Plan

### No Legacy Data Migration

Do not spend v1 effort on backward compatibility with the current monolith's stored data.

Out of scope:

- Migrating old `notes` CPT rows.
- Migrating old `todo` CPT rows.
- Migrating old prompt notebooks such as `prompts-chat` or `prompts-podcast`.
- Migrating old `ai-memory` or `ai-chats` notes.
- Migrating old OpenAI/provider API key settings.
- Keeping read-only compatibility views, redirects, or CPT fallback layers for old PersonalOS data.

Use the current codebase as implementation reference only. The split plugins should create and manage new Knowledge-backed data going forward.

### Phase 1: Establish Shared Helpers And Package Skeletons

Goal: create the independent plugin shape before moving feature code.

- Add package directories for Notes, Readwise sync, Evernote sync, TODO, and AI Chat.
- Add main plugin files, headers, `readme.txt`, licenses, text domains, and activation smoke tests.
- Add `shared/php/` helper sources and packaging logic that copies helpers into each ZIP under `includes/shared/`.
- Add `PersonalOS_Knowledge_Bridge` for Knowledge detection, resolved post type/taxonomy/REST helpers, source meta handling, and missing-runtime admin notices.
- Add package-local settings helpers only where the package actually has settings.

Acceptance criteria:

- Each package activates without sibling PersonalOS plugins.
- Each package can detect missing Knowledge and show a non-fatal admin notice.
- Activating all packages together produces no class redeclaration fatals.
- CI can package each skeleton as its own WordPress.org-ready ZIP.

### Phase 2: Build Package-Local Knowledge Repositories

Goal: give each package a tiny data layer over Knowledge without a shared runtime plugin.

- Implement create/update/query helpers for note-like rows, TODO rows, skills, memories, artifacts, and conversations as package-local services using bundled shared helpers.
- Call `PersonalOS_Knowledge_Type_Vocabulary` from each package that writes or queries Knowledge.
- Ensure routing, source, and PARA/collection terms idempotently when a package first needs them; this must work even when Notes is not installed.
- Normalize known Knowledge type term parents idempotently through the shared vocabulary helper so existing flat terms move into the shared hierarchy without changing slugs or term IDs.
- Add shared helper methods that expand parent terms to child term IDs for parent-level filters such as all status buckets, all projects, all areas, or all sources.
- Use the bridge-resolved source meta key, source hashes, and external IDs for new sync rows and idempotent syncs.
- Create user-specific Knowledge rows as `private` and authored by the owning user. Create site-wide Knowledge rows as `publish` only from an administrator-controlled setup flow.
- Query Knowledge through normal WordPress APIs and filter returned rows with `current_user_can( 'read_post', $post_id )`; do not bypass Knowledge permissions with direct SQL.
- Add package helper methods for `get_setting( $id, $user_id )`, `update_setting( $id, $value, $user_id )`, user sync state, user discovery by configured setting, and token-to-user lookup.
- Do not read from or write to old PersonalOS CPTs/options as a compatibility path.

Acceptance criteria:

- A private manual note can be created in the resolved Knowledge post type with `artifact`, `note`, and `manual`.
- A site-wide shared skill/instruction can be created only from an admin-controlled flow and is distinguishable from user-private artifacts.
- A private manual note or TODO can be assigned to `inbox`, `now`, and project/area/resource child terms through the resolved Knowledge type taxonomy.
- A TODO item can be created as a separate Knowledge row with `artifact`, `todo`, and optional PARA terms.
- Reparenting an existing flat `now`, `project`, `area`, `readwise`, or `evernote` term preserves the term slug and term ID.
- Parent-level filters return expected child-term rows because package helpers explicitly expand the term tree.
- Readwise/Evernote sync upserts are idempotent for new syncs.
- No code path requires the old monolith's `notes` or `todo` CPT.

### Phase 3: Build Notes UI On Knowledge

Goal: make Notes a UI over Knowledge, not a private CPT owner.

- Move Notes screens, blocks, and REST reads/writes to the Personal Notes package.
- Target the resolved Knowledge post type only.
- Query note-like rows by resolved Knowledge type terms, primarily `note`.
- Default manual note creation to private Knowledge authored by the current user.
- Add filters for source terms: manual, daily-note, readwise, evernote, synced.
- Add PARA/collection browsing and editing using resolved Knowledge type terms such as `inbox`, `now`, project child terms, area child terms, and resource child terms.
- Select the native editor per Knowledge row from its canonical saved shape: serialized Gutenberg block content opens in the block editor, while Markdown, plain text, classic HTML, and empty/new rows open in the classic editor.
- Expose a package-local Notes sidebar in Gutenberg post editors. It searches and filters the core Knowledge REST collection, previews note-like rows, and inserts or drags them as `pos/note` blocks; do not port monolith-only Readwise or Evernote document panels.
- Do not include old `notes` CPT redirects, migration notices, or read-only compatibility views.

Acceptance criteria:

- Notes UI can create, edit, list, search, and filter note-like Knowledge.
- Notes editor selection preserves both Gutenberg block serialization and unconverted Markdown content.
- Gutenberg editors can search, preview, and insert Knowledge notes from the Notes sidebar without loading the monolith bundle.
- Notes UI can assign and filter Knowledge by PARA/collection terms without a separate taxonomy.
- Readwise/Evernote synced Knowledge appears in Notes UI without a PHP dependency.
- Notes plugin can be active by itself.

### Phase 4: Build Readwise And Evernote As Independent Sync Plugins

Goal: make sync plugins write directly to Knowledge.

Readwise:

- Store API credentials/settings in the Readwise plugin only, with `token`, `autotag`, `page_cursor`, and `last_sync` scoped to the owning user.
- Run sync once per user with a configured Readwise token, switching current-user context for row creation and isolating failures per user.
- Sync highlights/articles as Knowledge rows tagged `artifact`, `note`, `readwise`, `synced`, and any relevant resource child term.
- Use external IDs and source hashes for idempotent updates, and include author/user scope in duplicate checks.

Evernote:

- Store Evernote credentials/settings in the Evernote plugin only, with `token`, `synced_notebooks`, `active`, `usn`, `last_sync`, `last_update_count`, and `cached_data` scoped to the owning user.
- Run sync once per active user with configured Evernote settings, resetting cached clients between users and isolating failures per user.
- Sync notes as Knowledge rows tagged `artifact`, `note`, `evernote`, `synced`, and any relevant resource child term.
- Use external IDs and source hashes for idempotent updates, and include author/user scope in duplicate checks.

Acceptance criteria:

- Readwise can sync on a site without Personal Notes active.
- Evernote can sync on a site without Personal Notes active.
- Activating Notes later immediately displays synced Knowledge.
- Two users can configure different Readwise/Evernote tokens and sync state without seeing or overwriting each other's private Knowledge rows.

### Phase 5: Build TODO As Knowledge Task Artifacts And Fold In ICS

Goal: make TODO a standalone task plugin whose durable records are separate Knowledge posts tagged `artifact` and `todo`.

- Tag each task Knowledge post with `artifact` and `todo`.
- Assign PARA/collection terms such as `inbox`, `now`, `later`, `follow-up`, project child terms, and area child terms through the resolved Knowledge type taxonomy.
- Do not use `plan` or `personalos` for ordinary TODO rows.
- Keep the current TODO mechanics close: `post_status` for active/completed/trash state, `post_date` plus cron for scheduled tasks, `pos_recurring_days`, `pos_blocked_by`, `pos_blocked_pending_term`, optional `reminders_id`, and `url` as registered REST-visible meta on Knowledge posts.
- Keep TODO activity/history as `todo_note` comments attached to the task Knowledge post.
- Keep task title, excerpt, and content useful for non-PersonalOS consumers.
- Move ICS feed/export into the TODO plugin. Treat the ICS token as a user setting and resolve token-to-user before querying private Knowledge task rows.
- Register TODO abilities when the Abilities API exists.
- Remove the standalone ICS module.
- Do not include old `todo` CPT migration or compatibility views.

Acceptance criteria:

- TODO can create, edit, complete, filter, and search task Knowledge.
- Obsidian or another Knowledge-aware tool can discover TODOs by querying Knowledge type terms for `artifact` and `todo`.
- Obsidian or another Knowledge-aware tool can discover work context by querying Knowledge type terms for `inbox`, `now`, specific project/area terms, or helper-expanded `project`/`area` container filters.
- ICS feed works from TODO data without any separate plugin and returns only the token owner's readable tasks.
- AI Chat can discover TODO abilities through the Abilities API when both plugins are active.
- TODO works without Notes, Readwise, Evernote, or AI Chat.

### Phase 6: Replace OpenAI Module With AI Chat

Goal: make AI Chat an agent plugin, not a provider credential module.

- Move chat UI and agent REST endpoints into Personal AI Chat.
- Use Knowledge for prompts, skills, memories, artifacts, and chat transcripts.
- Use Connectors for provider credentials.
- Use AI Client via `wp_ai_client_prompt()` for supported model calls.
- Keep only tiny provider-specific adapters where AI Client lacks required behavior.
- Discover tools through `wp_get_abilities()`.
- Remove the OpenAI module as a shared dependency.

Acceptance criteria:

- AI Chat activates without Notes, TODO, Readwise, or Evernote.
- AI Chat can run with Knowledge and a configured AI provider.
- TODO tools appear only when TODO is active and registers abilities.
- Old OpenAI module settings are ignored; users configure providers through Connectors.

### Phase 7: Remove Monolith Coupling And Finish Packaging

Goal: move from a decoupled monolith to independent plugins.

Recommended package layout:

```text
packages/
  personal-notes/
  personal-readwise-sync/
  personal-evernote-sync/
  personal-todo/
  personal-ai-chat/
shared/
  php/
tools/
  package-plugin.mjs
```

For each package:

- Add its own plugin header.
- Add its own bootstrap.
- Copy shared helper classes from `shared/php/` into `includes/shared/` during packaging.
- Extend `PersonalOS_Plugin_Base` or `PersonalOS_Sync_Plugin_Base` only for package-local plumbing; keep feature behavior in package classes/services.
- Add package-local helpers only for package-specific behavior.
- Add activation checks/admin notices for missing Knowledge or AI setup.
- Add tests proving activation without sibling plugins.
- Add package build metadata so CI can produce a standalone ZIP.
- Do not add `Requires Plugins` for another PersonalOS plugin.
- Add `readme.txt`, `LICENSE`, package source/build notes, and local assets needed for Plugin Directory review.

Acceptance criteria:

- Each package can be installed and activated independently.
- Each package passes the store-readiness baseline before it is published as a split plugin.
- No package references `POS::get_module_by_id()`.
- No package requires `modules/class-pos-module.php`.
- No package requires a PersonalOS base plugin.
- No package copies `POS_Module` unchanged; the split base classes must use package-local paths, option names, CLI namespaces, and runtime checks.

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
  - `personal-notes.zip`
  - `personal-readwise-sync.zip`
  - `personal-evernote-sync.zip`
  - `personal-todo.zip`
  - `personal-ai-chat.zip`
- Update PR builds to use a matrix over the package list and upload each ZIP as a separate artifact.
- Update release builds to upload all package ZIPs as release assets.
- Update PR preview publishing to expose separate URLs for each plugin ZIP; if a Playground link is kept, provide a blueprint that installs the relevant package ZIPs explicitly.
- Keep the current monolith ZIP only until the split packages are ready, and do not submit it as part of the split plugin family.
- Add a store-submission CI job per package that runs readme validation, Plugin Check where available, license/source scans, and a simple activate/deactivate smoke test.
- Treat generated ZIPs as review artifacts: include the exact files intended for WordPress.org submission, including bundled shared helpers and built assets.

Package ZIP acceptance criteria:

- Each ZIP has exactly one top-level plugin directory matching the plugin slug.
- Each ZIP contains a valid plugin header.
- Each ZIP contains `readme.txt`, GPL-compatible license metadata, and corresponding source/build instructions.
- Each ZIP activates on its own.
- Each ZIP includes `includes/shared/` helper copies when the package uses shared helpers.
- No ZIP contains unrelated sibling package source.
- No package ZIP declares `Requires Plugins` for another PersonalOS plugin.
- CI fails if a package's declared ZIP contents are missing built assets, PHP bootstrap files, or package-local vendor files.
- CI fails on obvious WordPress.org blockers: remote executable scripts, placeholder readme fields, non-GPL-compatible bundled assets, or missing service/privacy disclosure for networked packages.

## JavaScript Build And Editor Surface Split

The current NPM/webpack setup is also monolithic:

- `webpack.config.js` defines one custom entry, `index: './src/index.js'`.
- `src/index.js` imports the Notes sidebar, notebook UI, and TODO UI into the same `build/index.js`.
- `personalos.php` registers one `pos` script from `build/index.js` and one `build/style-index.css`.
- Notes, Readwise, OpenAI, and TODO editor assets currently live under one root `src/` tree.
- `src-chatbot` is a separate Next/React app that builds to `src-chatbot/build/chatbot.*`, but it is still conceptually owned by the current OpenAI module.

The split must move editor scripts, blocks, and sidebars to package-local builds. Do not keep a shared root `pos` editor bundle as a hidden dependency.

Package ownership:

| Package | Frontend/editor ownership |
| --- | --- |
| Personal Notes | Notes sidebar, note search/drag UI, `pos/note` block, note-like Knowledge filters |
| Personal Readwise Sync | `pos/readwise` and `pos/book-summary` blocks, Readwise-specific document panels if still useful |
| Personal Evernote Sync | Evernote-specific document panels and open-in-Evernote UI, if still useful |
| Personal TODO | TODO admin/DataViews app, task editor forms, ICS admin affordances |
| Personal AI Chat | Chatbot app, AI message/tool blocks, model/prompt UI, artifact UI |

Frontend extraction rules:

- Replace root `src/index.js` with package-local entry points, for example `packages/personal-notes/src/index.js`, `packages/personal-todo/src/index.js`, and `packages/personal-ai-chat/src/index.js`.
- Each package should enqueue only its own generated asset file and asset metadata.
- Each package should register only the blocks it owns.
- Shared UI utilities should move into package-local copies unless they become a small real shared npm/composer package. Do not keep `src/components/*` as a runtime dependency between plugin ZIPs.
- The current `notebook` UI should not be carried forward as a cross-package dependency. Notes/TODO should filter by the resolved Knowledge type taxonomy and package-owned meta instead.
- AI-dependent blocks such as `pos/img-describe`, `pos/ai-message`, and `pos/ai-tool` should either move to AI Chat or be deferred with the other satellite AI features.
- Readwise/Evernote document panels should not require the Notes sidebar; they should read source/provenance meta from the current Knowledge post.
- TODO must not be bundled into Notes. Its admin UI and task forms belong to the TODO package.
- The package ZIP build must include each package's built JS/CSS and `*.asset.php` files.
- Build commands should be package-aware, for example `npm run build -- --package=personal-notes` or a package matrix that calls `wp-scripts build` from each package directory.
- Generated block metadata, `block.json`, translations, and `*.asset.php` files must live inside the owning package ZIP, not in a shared root build directory.

Frontend acceptance criteria:

- Activating only Personal Notes registers no TODO, Readwise, Evernote, or AI Chat scripts.
- Activating only Personal TODO registers no Notes sidebar script.
- Activating only Personal Readwise Sync registers only Readwise blocks/panels.
- Activating only Personal AI Chat registers the chat app and AI-owned blocks without requiring Notes or TODO.
- No package enqueues root `build/index.js`.
- No package references source files from a sibling package at runtime.
- CI fails when a package has source changes but its built assets are missing from the ZIP.

## Local wp-env Development

The default local development environment should dogfood the full split suite, but it must not hide hard dependencies between packages.

Default `wp-env` behavior:

- Target a WordPress 7.0 runtime where Abilities API, AI Client, and Connectors are core surfaces.
- Do not install or mount `WordPress/abilities-api` in the default split `.wp-env.json`; use it only in an explicit legacy/pre-7.0 compatibility config if needed.
- Mount all local split packages so contributors can work on the integrated PersonalOS experience:
  - `./packages/personal-notes`
  - `./packages/personal-readwise-sync`
  - `./packages/personal-evernote-sync`
  - `./packages/personal-todo`
  - `./packages/personal-ai-chat`
- Map the monorepo `shared/php` and `vendor` directories to `wp-content/shared/php` and `wp-content/vendor`. wp-env mounts each package independently, so these mappings make package fallback loaders and Composer runtimes available during activation without adding release-time sibling dependencies.
- Mount pinned Gutenberg 23.7.0 as the current Knowledge fixture and enable its historically named `gutenberg-guidelines` experiment after startup. The option name belongs to Gutenberg; the Personal package contract is Knowledge-only. Remove that fixture when the selected WordPress core image registers Knowledge itself; it is not a PersonalOS sibling or release dependency.
- Activate all local PersonalOS packages after startup for the default development site.

Testing behavior:

- Add package-specific wp-env scripts or generated wp-env configs that activate exactly one PersonalOS package plus the required WordPress runtime surface. Example scripts: `wp-env:notes`, `wp-env:todo`, `wp-env:readwise`, `wp-env:evernote`, and `wp-env:ai-chat`.
- CI should run both shapes: one "all packages active" integration job and a package matrix where each plugin is installed/activated alone.
- Any test that passes only when another PersonalOS package is active should fail the standalone package job unless the relationship is explicitly optional and mediated through Knowledge, hooks, REST, or Abilities.

Example default split config shape:

```json
{
	"plugins": [
		"https://downloads.wordpress.org/plugin/gutenberg.23.7.0.zip",
		"./packages/personal-notes",
		"./packages/personal-readwise-sync",
		"./packages/personal-evernote-sync",
		"./packages/personal-todo",
		"./packages/personal-ai-chat"
	],
	"mappings": {
		"wp-content/shared/php": "./shared/php",
		"wp-content/vendor": "./vendor"
	},
	"lifecycleScripts": {
		"afterStart": "wp-env run cli wp option update gutenberg-experiments '{\"gutenberg-guidelines\":true}' --format=json"
	}
}
```

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
- Podcast prompts and podcast-specific Knowledge behavior.
- Provider-specific OpenAI Responses continuation beyond what AI Chat truly needs.

If any deferred feature returns, it should follow the same rules:

- Store durable data in Knowledge.
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
- Add Knowledge availability tests for `wp_knowledge` available and the Knowledge runtime unavailable.
- Add regression tests proving split packages do not depend on old PersonalOS CPTs, notebooks, or provider settings.
- Add tests proving PARA/collection terms are stored in the resolved Knowledge type taxonomy, not in a separate taxonomy or post meta.
- Add tests proving canonical PARA terms use stable slugs, hierarchical parents, and numbered top-level display labels such as `1-Status`, `2-Projects`, `3-Areas`, and `4-Resources`.
- Add tests proving container-only terms such as `status`, `project`, `area`, `resource`, and `archive` are hidden from assignable term pickers, are not applied directly to Knowledge rows by helper APIs, and work only through child-term query expansion.
- Add tests proving old field IDs are registered against the bridge-resolved object types: `readwise_id`, `readwise_category`, `readwise_author`, `evernote_guid`, `evernote_content_hash`, `pos_blocked_by`, `pos_blocked_pending_term`, `pos_recurring_days`, `pos_model`, `pos_chat_prompt_id`, and `pos_last_response_id` on the resolved Knowledge post type; `flag`, `evernote_notebook_guid`, and `evernote_type` on the resolved Knowledge type taxonomy.
- Add tests proving the bridge resolves `wp_knowledge`/`wp_knowledge_type` and reports the runtime unavailable when either is missing.
- Add tests proving package storage keys use package-scoped prefixes such as `personal_readwise_sync_last_sync` and do not read old module options such as `readwise_last_sync`.
- Add tests, based on PR #72 `SettingScopeTest`, proving `scope => user` settings/state are stored in user meta and `scope => site` settings are stored in options.
- Add tests, based on PR #72 `ModuleTokenMappingTest`, proving per-user tokens resolve to authorized WordPress users and reject missing, short, unknown, or unauthorized-user tokens.
- Add tests, based on PR #72 `NotesRestPermissionsTest`, proving private Knowledge rows are readable/editable by their author and privileged users only, using the runtime's Knowledge REST permissions.
- Add idempotency tests for Readwise/Evernote external IDs and source hashes.
- Add tests, based on PR #72 `ReadwiseModuleSyncTest` and `EvernoteModuleSyncTest`, proving sync loops over users with configured tokens/settings, switches current-user context during row creation, stores cursors per user, and does not let one user's failure block another user.
- Add TODO ICS export tests against bridge-resolved Knowledge task data.
- Add TODO ICS token tests proving a token maps to one user and the feed includes only that user's readable task Knowledge.
- Add AI Chat tests proving tools are discovered through `wp_get_abilities()`.
- Add permission tests for private Knowledge reads/writes.
- Add package ZIP smoke tests for install, activate, deactivate, and uninstall policy for each split package.
- Add store-submission checks per package: Plugin Check/readme validation, license/source audit, no remote executable code scan, and service/privacy disclosure review.
- Add build matrix tests proving each plugin ZIP contains its own JS/CSS/block assets and no sibling package source.
- Add route registration tests proving the three WpApp paths are present with the expected capabilities, Readwise/Evernote do not register app routes, and all five plugins can be active together without runtime redeclaration errors.

## Risks And Mitigations

| Risk | Mitigation |
| --- | --- |
| Knowledge is not available in the target runtime | Feature-detect, show admin notices, and avoid private replacement unless explicitly chosen |
| Knowledge runtime APIs change | Keep availability and metadata integration inside bundled shared helpers, update the bridge, then rebuild every package |
| Knowledge type taxonomy becomes overloaded | Use a small shared vocabulary, hierarchical parent terms for PARA, and meta only for behavior/provenance that is not discoverable organization |
| Shared helper copies drift across packages | Keep shared helpers dependency-free, guarded by `class_exists`, stable across package versions, and copied by CI rather than manually edited in packages |
| Bundled WpApp versions drift across app packages | Pin WpApp 1.3.2 once in Composer, copy the same runtime into the three app ZIPs, and verify its source/license files per app package |
| Bundled base class becomes another monolith | Limit base classes to package-local plumbing, sync mechanics, CLI/settings/helpers, and Knowledge bridge access; reject registries, sibling injection, and feature logic |
| WordPress.org rejects split packages for review issues | Make Plugin Directory readiness a CI gate: readme, license, naming, privacy, remote-code, and activation checks must pass per ZIP |
| Old monolith assumptions leak into split packages | Add tests/static checks that reject references to old PersonalOS CPTs, module registries, and provider settings |
| TODO semantics are richer than plain Knowledge documents | Use one Knowledge post per task, register REST-visible task meta, and expose operational actions through Abilities |
| Sync plugins duplicate rows | Use external IDs, source hashes, and idempotent upserts |
| AI Chat tries to become another central runtime | Limit it to chat/agent behavior; use Abilities and Knowledge for integration |
| Removing satellite modules loses useful experiments | Park them explicitly and revive only as independent Knowledge/AI Client plugins |

## Decision Defaults

- V1 plugin set is Notes, Readwise, Evernote, TODO, and AI Chat.
- Public package names use the Personal family: Personal Notes, Personal TODO, Personal AI Chat, Personal Readwise Sync, and Personal Evernote Sync.
- Knowledge is the shared storage layer for notes, synced content, TODOs, prompts, memory, artifacts, and conversations.
- Knowledge owns row-level user scoping; split packages must use `private` user-authored rows for user artifacts, `publish` only for admin-created site artifacts, and normal `current_user_can( 'read_post'|'edit_post'|'delete_post' )` checks.
- The bridge-resolved Knowledge type taxonomy is also the shared PARA/collection layer; do not add a PersonalOS-only collection taxonomy in v1.
- PARA top-level display labels keep the old numeric sort convention: `1-Status`, `2-Projects`, `3-Areas`, `4-Resources`, with stable unnumbered slugs used for all code paths.
- `status`, `project`, `area`, `resource`, and `archive` are container-only terms for hierarchy/filter expansion and must never be applied directly to Knowledge posts.
- Do not replace `notes_user` with per-plugin "sync user" options. User-owned credentials and sync cursors are per-user settings/state, and sync plugins loop over configured users.
- Readwise and Evernote do not depend on Notes.
- TODO stores each task as its own Knowledge post tagged `artifact` and `todo`; it includes ICS.
- AI Chat replaces the OpenAI module.
- Perplexity, IMAP, podcast, ElevenLabs, transcription, and voice/realtime features are removed or deferred.
- No mandatory PersonalOS base plugin.
- No `Requires Plugins` dependency between PersonalOS packages.
- Shared helper/base classes are allowed, but only as build-time bundled code inside each plugin ZIP with `class_exists` guards.
- The split should introduce `PersonalOS_Plugin_Base` and `PersonalOS_Sync_Plugin_Base` by extracting the reusable settings, CLI, logging, sync, package asset, and Knowledge bridge access patterns from `POS_Module`; do not reuse `POS_Module` unchanged.
- Every split package should be WordPress.org-submittable immediately, not merely internally installable.
- Notes, TODO, and AI Chat expose private WpApp routes, bundle WpApp 1.3.2, and require PHP 7.4.
- Readwise and Evernote remain background integrations with wp-admin Settings pages, no WpApp route/runtime, and a PHP 7.2.24 minimum.

## References

- [PersonalOS PR #61: Migrate OpenAI tools to WordPress Abilities API, Notes as prompts everywhere](https://github.com/artpi/PersonalOS/pull/61)
- [PersonalOS PR #72: Per-user permissions](https://github.com/artpi/PersonalOS/pull/72)
- [WordPress 7.0 Connectors API dev note](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)
- [WordPress 7.0 AI Client dev note](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)
- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress Plugin Header Requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Including a Software License](https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/)
- [How Your Readme.txt Works](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Add Your Plugin](https://wordpress.org/plugins/developers/add/)
- [WpApp](https://github.com/akirk/wp-app)
- [create-wp-app](https://github.com/akirk/create-wp-app)
- `/Users/artpi/GIT/wp-agent-skills/skills/wp-plugin-directory-guidelines/SKILL.md`
