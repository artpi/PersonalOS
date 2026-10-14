# Personal Notes contract

Notes is an independent private WpApp at `/notes/`, backed by shared Knowledge.
See the [shared contract](../../docs/knowledge-contract.md) for ownership and
vocabulary and the package PHP/JS for exact routes and fields.

## Library and editors

Use WordPress DataViews and canonical Knowledge REST collections. Hand editing
to the native Knowledge editor instead of adding a private editor engine. Notes
may enable that editor when Knowledge has `show_ui` false while keeping the full
Knowledge list out of the admin menu.

Choose Gutenberg only when `has_blocks(post_content)` detects serialized blocks;
use the classic editor for Markdown, plain text, classic HTML and empty/new rows.
Do not persist a parallel format flag. Stuff's later editor filter explicitly
enables Gutenberg for its items, including empty ones.

Expose the raw native `wp_knowledge_type` panel. System and container terms are
intentionally assignable there; do not replace it with TODO's restricted picker.
Use Knowledge Type as the label. Notes owns the full PARA management UI, while
shared helpers own vocabulary normalization and query expansion.

The library lists `note` records. Stuff uses `artifact` + `stuff-item`, without
`note`, and should not automatically appear as ordinary Notes rows. Preserve
source/provenance terms (`manual`, `readwise`, `evernote`, `synced`) during edits.

Notes owns native permalinks and shortlinks for Knowledge rows carrying the
`note` identity and resolves them to the native WordPress editor. An editable
legacy `?p=<post ID>` request redirects to that editor; unauthorized requests
retain WordPress's normal response.

## Gutenberg sidebar

The package-local sidebar searches Knowledge, filters by Knowledge Type, previews
notes and inserts/drags `pos/note` blocks. It runs in block editors, including
Stuff, but not classic Markdown editors. Do not port the old monolith's separate
Readwise/Evernote document panels.

## Verification

Test library filtering, raw taxonomy editing, content-based editor choice,
provenance preservation and sidebar insertion with Stuff active. On a fresh site,
exercise Add note through the real core REST flow and verify all required identity
and source terms exist. Missing terms have previously blocked creation despite
successful activation; this is an acceptance requirement, not a claim of a fix.
