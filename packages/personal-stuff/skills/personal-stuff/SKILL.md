---
name: personal-stuff
description: Find, add, describe, photograph, tag, or move belongings in Personal Stuff using existing WordPress Knowledge, Knowledge Type, and Media APIs. Use for inventory operations on a site with Personal Stuff, including places and item photo galleries.
---

# Personal Stuff operating skill

Use the site's existing authenticated WordPress connection. Discover the available WordPress tools and core REST schema; do not invent Stuff endpoints or require another Personal plugin. Read `../../TECHNICAL.md` for the full storage contract.

## Resolve vocabulary and access

1. Confirm `wp_knowledge` and hierarchical `wp_knowledge_type` exist. Discover their REST routes/fields from `/wp/v2/types/wp_knowledge` and `/wp/v2/taxonomies/wp_knowledge_type`, or equivalent existing tools. The Knowledge collection is `/wp/v2/knowledge`. Read the taxonomy schema's `rest_namespace` and `rest_base` before querying terms; the resulting term collection may be `/wp/v2/wp_knowledge_type`, not an assumed pluralized route such as `/wp/v2/knowledge-types`.
2. Resolve terms by slugs `artifact`, `stuff`, `stuff-item`, `stuff-places`, `stuff-tags`; include `hide_empty=false`. Follow REST pagination. Never persist root IDs in options or another registry.
3. If fixed roots are absent, report that Personal Stuff must be activated with Knowledge available. Do not invent parallel taxonomies or create place posts.
4. Only operate within the authenticated user's existing permissions. Private item access does not make Media file URLs or raw taxonomy terms private.

## Find belongings

Query the core Knowledge collection with the resolved taxonomy REST field containing the `stuff-item` term ID. Use `context=edit` when raw content is needed and authorized. Inventory may include private, published, draft, pending or future records; exclude trash from ordinary browsing. Paginate all relevant results before claiming completeness.

Read descriptions from content blocks. Resolve location assignments against descendants of `stuff-places`; derive ancestor paths from raw `parent` relationships. Filter a parent place by expanding its descendants. Match tags under `stuff-tags`. Search names, description text, tag names and full location paths; normalize accents/case/whitespace. Report ambiguity rather than moving a guessed item.

## Add or update an item

- Create one Knowledge post with `status: private`, a meaningful `title`, and taxonomy IDs for `artifact`, `stuff-item`, zero/one physical place and any descriptive tags. Let WordPress assign the author, ID and slug.
- Store descriptions in native paragraph blocks and photos in core image/gallery blocks in `content`. Preserve all unrelated blocks, captions, gallery settings, and other Knowledge Type assignments.
- Before editing, fetch the current raw row. Send only fields that need changing. For a move, update taxonomy assignments and leave content untouched. Never send an empty content field accidentally.
- If concurrent edits are visible, refetch and reconcile before saving. Core REST updates do not provide an atomic compare-and-swap guarantee. Check the result of every write.
- There is no quantity, custom Stuff meta, place-content post, content ID or parallel photo list. Do not create any of these.

For a new description:

```html
<!-- wp:paragraph -->
<p>Camera and lenses in a padded pouch.</p>
<!-- /wp:paragraph -->
```

Prefer an available Gutenberg parser/serializer for changes involving existing blocks. Do not flatten a whole post to plain text or rebuild its gallery from derived thumbnails. Use the native editor if the available tools cannot preserve its block structure.

## Places and tags

Use native Knowledge Type term endpoints. A place is a raw term beneath `stuff-places`, optionally under another place. A tag belongs beneath `stuff-tags`. Use only native `name`, `slug`, `parent`, and `description`. Resolve the complete path before creating a same-named place, avoid duplicates/cycles, and never change the five fixed routing slugs.

Term deletion removes assignments and reparents children according to WordPress behavior. Follow the user's authorization for destructive operations. Prefer moving/renaming when that is what they requested. Photos belong to item posts, never to terms.

## Photos and failed uploads

1. Save the private item before uploading so a real authorized parent exists.
2. Generate an unpredictable filename before handing a new file to WordPress: at least 128 bits from a cryptographically secure random generator, preserving the validated extension. Do not expose descriptive source filenames in public uploads. The plugin enforces the same server-side rule in its verified item upload contexts.
3. Upload to core `/wp/v2/media` with the item ID in `post`. Both multipart and raw-body requests must identify that parent. Do not sideload a URL automatically; an external URL may remain a direct image block reference if requested.
4. Use the returned attachment ID and source URL in a native `core/image`, normally inside `core/gallery`. Preserve existing image attributes/captions. Serialize using Gutenberg; block order defines the cover and photo order.
5. Save each successful addition before continuing a batch. If a response is lost, inspect recent Media attachments for this parent before retrying; do not blindly duplicate uploads. Keep successful attachment IDs in the current operation's memory, without persistent locks/idempotency tables.
6. Removing an image block retains the Media attachment. Use WordPress's normal Media controls for an explicitly requested permanent deletion.

Random filenames provide hard-to-guess public URLs, not private media. Do not claim otherwise. Existing attachment names are unchanged; original image metadata may remain. Do not rename unrelated uploads or existing files retroactively.

When the user wants an external photo copied into Personal Stuff, retrieve and validate the image bytes, then upload them as Media attached to the saved item; do not leave an external image block in that case. Prefer a confirmed original-quality image over a derivative when both are available, but retain the provided URL as a fallback. Use the authorized client for protected images instead of assuming a share or display URL is directly downloadable.

WordPress may add ordinary derivative suffixes such as `-scaled` or `-rotated`. When checking filename randomization, verify that the unpredictable stem remains intact rather than requiring the final filename to contain only the stem and extension.

## Large batch operations

- Preflight the intended item, place, tag, and photo counts. Validate identifiers, parent references, hierarchy cycles, missing photo sources, and ambiguous names before writing.
- Start with one representative item and verify its status, terms, place path, Media parent, content blocks, and visible photo before continuing.
- Use deterministic pagination such as `orderby=id&order=asc`. Date-only ordering can omit or repeat rows at page boundaries when timestamps are tied. Respect the core `per_page` limit and follow every page.
- Keep resumability state local and temporary. It may map operation input identifiers to created post, term, and attachment IDs, but must not add import IDs, locks, registries, options, or custom fields to Personal Stuff data.
- Save and record each successful photo before continuing. A batch may continue past an isolated failure only when the caller authorized that behavior and the final report identifies every incomplete item or photo.
- Finish with an independent read-back reconciliation. Check exact counts, term parent relationships, private item status and assignments, Media parents, attachment references and order in serialized content, and randomized filename stems. Do not treat the write loop's own success messages as sufficient verification.

## UI handoff

Open `/stuff/` for the native inventory app or `wp-admin/post.php?post=<ID>&action=edit` for full Gutenberg editing. URL filters use `q`, `place` (slug), `tag` (slug), `photo` (`all`, `with`, `without`), and `item` (post ID). Collection links retain filters. Print/PDF uses the browser's print dialog on the full filtered set.

There is no importing UI, importer endpoint or bulk migration workflow in this plugin. Use ordinary authorized item operations and report what changed, including the final location and any photos that failed.
