# Readwise and Evernote sync contracts

These are independent background integrations with wp-admin Settings screens,
not WpApp destinations. Use the [Knowledge contract](knowledge-contract.md) for
storage and ownership and [Packaging](packaging.md) for bundled dependencies.

## Shared boundaries

The thin shared sync base owns reusable settings/auth/request facilities; each
package owns iteration, permissions, normalization, cursors and behavior. Preserve
provider IDs and user-scoped credentials/cursors. Transport feeds normalized notes
into idempotent Knowledge upserts rather than depending on the monolith registry,
Notes module or another package. Package source is under `packages/`.

## Evernote

Personal Evernote Sync is a one-way import transport. It owns active-user
iteration, current-user switching, notebook/tag scope checks, per-user cursor/cache
settings and idempotent upserts by `evernote_guid`. Load the bundled Evernote SDK
when present and normalize matching sync-chunk notes into Knowledge. Bundle only
needed SDK/PSR runtime files, not Composer's development autoload or the legacy
two-way Notes module. The old module README is not the split sync contract.

## Readwise

Personal Readwise Sync owns `pos/book-summary`. Generate summaries through a
package REST endpoint backed by WordPress AI Client/Connectors, without the old
`/pos/v1/openai/*` routes or package-local provider credentials. Preserve Readwise
integration IDs and source terms on updates.

## Verification

For sync changes, test user isolation, scope filters, repeat-run upserts,
provenance retention and cursor behavior after partial failure. For Readwise
summary changes, verify configured/unconfigured generation and persisted output.
Use fixtures or the specifically authorized account; packaging checks alone do
not verify provider access or synchronization behavior.
