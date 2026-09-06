# Personal Stuff: lean implementation plan

Implemented on 2026-09-06 in `packages/personal-stuff`. This document preserves the agreed scope; [TECHNICAL.md](../packages/personal-stuff/TECHNICAL.md) defines the implemented contract and [the verification record](personal-stuff-verification.md) records the test results.

## 1. Shape of the plugin

**Personal Stuff is a JS inventory app over WordPress Knowledge, exposed as a private WpApp at `/stuff/`.** WordPress owns storage, REST, users, permissions, media and Gutenberg. Reuse those facilities directly.

Keep PHP to a small bootstrap using the existing shared helpers:

1. Ensure the fixed Knowledge Type terms exist.
2. Register the WpApp, launcher links and package assets.
3. Make native Knowledge/Gutenberg editing available for Stuff items when needed.
4. Randomize new Stuff upload filenames before WordPress saves them publicly.

No custom REST API, CRUD service layer, database tables, custom post/term meta, persistent plugin options, custom permissions, lock registry, job system or package-specific Abilities. Add a helper only when the implementation has real duplication or a clearly separate responsibility; do not scaffold controller/service classes in advance.

## 2. What is stored

| Data | Existing WordPress representation |
| --- | --- |
| Item | One `wp_knowledge` post |
| Item name | `post_title` |
| Description and ordered photos | Gutenberg blocks in `post_content` |
| Author/privacy/lifecycle | Native `post_author`, `post_status`, timestamps and trash |
| Place | Raw `wp_knowledge_type` term: name, slug, parent, description |
| Tag | Raw term in the same taxonomy |
| Item location/tags | Native post-to-term relationships |
| Photo file and image sizes | WordPress attachment and standard media metadata |

There is no quantity field, place-content post, content-ID link, gallery meta, saved path, photo count or featured-image mirror. Existing shared Knowledge provenance may be preserved; do not add a source field just for Stuff.

Photo order comes from block order. The first image supplies the cover. Paths, ancestor lists, search text and counts are computed in JS. Counts mean item posts, not quantities.

### Taxonomy tree

```text
wp_knowledge_type
├── artifact                         existing shared Knowledge identity
└── stuff                            fixed root
    ├── stuff-item                   fixed item identity
    ├── stuff-places                 fixed places branch
    │   ├── Home
    │   │   ├── Garage
    │   │   │   └── Blue box
    │   │   └── Office
    │   └── Camper
    └── stuff-tags                   fixed tags branch
        ├── cables
        └── camping
```

Each item receives `artifact` and `stuff-item`, zero or one physical place term, and chosen tags. Preserve unrelated Knowledge Type assignments. No place means unassigned; no fake room is needed.

The fixed root/branch terms are structural, not assignable through Stuff controls. Actual places remain assignable even when they contain child places: an item can be directly in Garage. This does not change existing PARA container behavior. The native Gutenberg Knowledge Type panel remains available.

Resolve fixed terms by **slug**, not name or saved numeric ID. Create missing terms parent-first on activation; reuse existing exact slugs. If Knowledge is unavailable then, repeat the same idempotent ensure operation once its taxonomy is available. No installed flag, schema-version option or root-ID option. Report conflicting root structure rather than silently making `stuff-2` or moving unrelated terms.

User-created places/tags use normal WordPress term APIs. Preserve their IDs and slugs on rename. Do not rewrite user hierarchy on every request.

Moving Blue box from Garage to Office changes only its term's `parent`. Items keep their assigned Blue box term. Their displayed paths and ancestor search matches change when JS recomputes the tree. There is no item `post_parent` location and no cascade of item writes.

Places use native term descriptions only. They have no galleries or Gutenberg documents of their own; that source feature is intentionally omitted.

## 3. Existing APIs only

| Action | Interface |
| --- | --- |
| List/read/create/edit/trash items | Existing `/wp/v2/knowledge` endpoints |
| Assign a place or tags | Native taxonomy field on the Knowledge REST record |
| Create/rename/move/delete places/tags | Existing Knowledge Type taxonomy REST endpoints |
| Upload/read media | Existing `/wp/v2/media` endpoints |
| Add/remove/reorder photos | Edit blocks in JS, then save the post's content through Knowledge REST |
| Search/tree/breadcrumbs/counts | JS over authorized records already loaded |

Use WordPress `api-fetch` and core-data where useful. Discover the taxonomy's actual REST base and post field from the runtime. Load all required pages before treating local search as complete. Read raw content in authorized edit context for editing; do not reconstruct a document from rendered content.

Use existing WordPress permissions. Hide actions the user cannot perform and handle core permission errors. No custom household sharing system or new roles are required; access follows the site's existing user configuration.

Keep validation close to the UI: required item name, one selected physical location, no self/descendant place move, no duplicate sibling names, and no deletion of a place with dependents. Use core validation as supplied; add a narrowly scoped hook only for a demonstrated invariant that cannot be kept through existing paths. Do not build a replacement taxonomy controller or promise transaction/locking guarantees beyond WordPress.

Preserve drafts on failure. Refetch before overwriting a document from an older snapshot and show changed data for review. This is ordinary best-effort conflict handling, not a new atomic version protocol. Do not automatically repeat an uncertain create/upload POST; reconcile known saved IDs first.

## 4. Gutenberg and the Stuff UI

Store descriptions as ordinary paragraphs/headings/lists and photos as `core/gallery` with `core/image` children, or standalone image blocks. Use the registered WordPress block parser/serializer; no regex rewriting, custom photo format or PHP gallery formatter.

Both interfaces edit the same content:

- **Gutenberg:** normal native editor, core blocks and raw Knowledge Type panel.
- **Stuff:** quick-add/edit, location/tag controls, description editing, image upload and gallery arrangement. Reuse WordPress block-editor components for rich content instead of implementing another editor engine.

Fast controls change the intended block and preserve everything else. If a post contains multiple galleries or images mixed with text, retain those groups and ordering; do not flatten the document. Unknown/custom blocks, captions, alt text and layout must survive. Provide a direct native Gutenberg edit link as well.

Derive photo occurrences in document order from core image/gallery blocks. A repeated attachment is a repeated block occurrence, not necessarily an error. Use block paths only within the current in-memory document; store no photo UUIDs or Gutenberg client IDs. Do not expand dynamic queries or synced patterns into owned inventory photos.

New name-only items get valid initial block content. Verify that Stuff items stay Gutenberg-editable when emptied and that any small editor integration does not change Personal Notes' behavior for unrelated Markdown/plain-text Knowledge.

## 5. Photos and public filenames

New Stuff uploads use ordinary WordPress Media, with an unpredictable basename **before the file is moved into the public uploads directory**.

- Generate at least 128 random bits server-side, for example `bin2hex( random_bytes( 16 ) )`, plus the validated image extension. No original basename, item name, sequential ID or timestamp as the random component. Fail without a predictable fallback if secure randomness is unavailable.
- Apply this in both the Stuff UI and its native Gutenberg upload context, using existing WordPress upload hooks and a verified authorized destination post. No custom upload endpoint and no global renaming of unrelated uploads.
- Confirm the actual core upload paths used, including raw-body/multipart differences. Client-side renaming may help but does not replace server enforcement.
- Let retained originals, scaled files and generated thumbnails use the randomized basename. Do not leave an original-name public copy or rename only after upload.
- Preserve original bytes where supported and generate previews using normal WordPress media handling. Report unsupported phone formats or upload limits clearly.
- Save the item first, then upload and insert photos sequentially. Keep the item and earlier successful photos if a later file fails. Retain known successful attachment IDs instead of uploading them again on retry.
- Removing an image block removes a reference, not its file. Keep permanent attachment deletion in ordinary Media management; do not build a custom reference scanner or media-deletion workflow in Stuff.
- Existing attachments are not renamed retroactively. URL-only images remain references and cannot be randomized unless explicitly uploaded as new files. No automatic sideload/import feature.

**The image URLs remain public and hard to guess; they are not private access-controlled media.** Say this in help/readme. Keep the `/stuff/` UI and Knowledge records private through normal WordPress permissions. No media-mode setting or private-file-serving adapter is part of v1.

## 6. UI behavior to preserve from Stuff

The source audit used `~/GIT/stuff` at commit `12a19b1`; all 49 source Node tests passed. Port its useful behavior, not its Google infrastructure:

- Mobile-first visual grid and compact list, with cover images or placeholders.
- Search names, description text, tags and full ancestor location paths; preserve accent/case normalization and small typo tolerance.
- Combined place-subtree, tag and photo-presence filters; encode filters in app URLs.
- Name-only quick-add, item editing/moving and raw place/tag editing.
- Image galleries, camera/device uploads, captions, ordering and broken-image handling.
- Contextual previous/next, touch swipe, return to filtered results and inline description editing.
- Print / Save as PDF of the full filtered result set, waiting for images with a bounded timeout.
- Keyboard/touch accessibility, clear progress, retained drafts and partial-upload feedback.

Use DataViews for the compact list where it fits; the photo grid can use the same data and filter state. Port the source's small pure search functions rather than rebuilding search server-side. Avoid a request per card; fetch records/terms in pages and load image bytes lazily.

There is no quantity, place gallery, importer, Google login, spreadsheet repair, custom error-log screen or migration workflow. Native WordPress error handling/debug logging is enough. Client-side diagnostics can report malformed/missing data without a new endpoint or repair framework.

Home-screen/install polish should reuse existing app/site facilities. Do not build a separate offline data store or service-worker subsystem as a prerequisite. If a worker is later needed, keep it app-scoped with an explicit static-asset allowlist; never copy the source's broad same-origin caching over authenticated WordPress data.

## 7. Operating skill

Include a portable operating skill at `packages/personal-stuff/skills/personal-stuff/SKILL.md`, linked from app help/readme. Write it against the verified existing APIs once the integration works; no custom ability layer is needed.

The skill covers finding, adding, editing and moving items/places, and adding photos:

- Resolve fixed roots by slug and distinguish term IDs, post IDs and attachment IDs.
- Disambiguate duplicate names using full paths.
- Use normal Knowledge/term/Media APIs and preserve unrelated terms/content.
- Parse and serialize Gutenberg content correctly; never replace it with a photo-meta representation.
- Upload with the authorized Stuff destination context and verify randomized returned filenames.
- Preserve successful work on partial failure; distinguish image-block removal from file deletion.
- Verify saved content and relationships after changes; do not invent tags or assume permissions.

No importer is required. A future authorized data-entry/transfer request can use these ordinary operations without adding importing affordances to the plugin.

## 8. Files and delivery

Start small:

```text
packages/personal-stuff/
  personal-stuff.php                  thin bootstrap/hooks and term setup
  src/
    index.js                         app and components
    content.js                       block helpers if useful
    search.js                        source search logic
    style.scss
  skills/personal-stuff/SKILL.md
  TECHNICAL.md
  readme.txt
  LICENSE
  build/                             generated
  includes/shared/                   release-bundled existing helpers
  vendor/akirk/wp-app/                pinned existing runtime
```

Split files/classes only as real code size/responsibility warrants. No pre-created controller/repository/service directories. Follow existing package conventions, build commands and PHP style.

1. **Foundation:** term provisioning, private `/stuff/` WpApp, assets and existing API reads/writes. Name-only create/edit and place moves work.
2. **UI/content:** visual inventory/search/filters, native term editing, shared Gutenberg document editing and randomized photo uploads. Complete the source interactions above and the operating skill.
3. **Package/verify:** add the app to existing webpack/build/ZIP lists, wp-env configs and release/preview matrices. Bundle existing shared helpers and pinned WpApp; document the implemented schema in `TECHNICAL.md`.

The plugin must run independently of other Personal packages, with the Knowledge runtime available. Reuse the existing `PersonalOS_Wp_App` helper, My Apps integration and wp-admin launcher pattern. No my.wordpress.net-specific deployment requirement. Follow the current app targets (WordPress 7.0, PHP 7.4, WpApp 1.3.2) and existing test fixture conventions.

## 9. Focused verification

- Repeated/deferred term initialization preserves exact slugs and creates no duplicate roots or options.
- Items use only native posts/blocks/term relationships; raw places have no companion posts/meta; no quantity or custom REST namespace exists.
- Both editors round-trip representative paragraphs, nested galleries, standalone images, captions and custom blocks without data loss or invalid-block dialogs.
- Renaming/reparenting places updates derived paths/search while item term assignments remain unchanged.
- Core post/term/media permissions are respected. No permission widening or site-wide visibility change.
- New app/Gutenberg uploads have random basenames before public persistence, including originals/sizes. Unrelated uploads and existing attachments remain untouched.
- Failed uploads preserve prior work; stale drafts and uncertain POST results are handled visibly without blind retries.
- Search/filter/context navigation and full-result printing work on a representative inventory.
- The operating skill performs ordinary tasks through existing APIs and its changes appear in both UIs.
- Build/lint, relevant backend/JS tests and actual standalone ZIP activation pass. If wp-env fails, report the environment failure rather than repairing it as part of this feature.

Deactivation/uninstall retains records, terms and media. No new data migration or cleanup framework is needed.

## References

Source behavior: `../../stuff/src/components/stuff-app.js`, `../../stuff/src/search/search-index.js`, `../../stuff/src/services/media-service.js`, `../../stuff/SKILL.md`.

Existing integration: `../shared/php/class-personalos-wp-app.php`, `../shared/php/class-personalos-knowledge-bridge.php`, `../shared/php/class-personalos-knowledge-type-vocabulary.php`, `../packages/personal-notes/includes/class-personal-notes-plugin.php`.

Packaging: `../tools/package-plugin.mjs`, `../tools/sync-package-builds.mjs`, `../tools/verify-split-packages.mjs`. Existing technical-document precedent: `../packages/personal-todo/TECHNICAL.md`.
