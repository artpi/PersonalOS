# Shared Knowledge contract

The split runtime requires `wp_knowledge`, `wp_knowledge_type`, and
`/wp/v2/knowledge`. Use `PersonalOS_Knowledge_Bridge` and the shared vocabulary
in `shared/php`; do not restore Guidelines-era aliases, old CPTs, or a required
base plugin. Each release bundles its shared helpers independently.

## Storage and ownership

Use native post fields, registered meta, taxonomy, options and user meta.
Derive values when inexpensive; do not add a parallel storage layer. Preserve
meaningful integration keys such as `readwise_id`, `evernote_guid`,
`pos_blocked_by`, `pos_recurring_days`, `pos_model` and `pos_last_response_id`.
Register package-owned metadata through the Knowledge bridge and scope options
by package. Use `knowledge_source` when registered and the bridge's documented
fallback otherwise.

User artifacts are private, user-authored Knowledge rows. Site artifacts are
admin-created published rows. User credentials and cursors live in user meta;
site settings live in options. Permissions follow the actual Knowledge row and
operation, not a replacement sync-user registry. Do not migrate legacy `notes`
or `todo` CPT data or old monolith settings unless explicitly requested.

## Knowledge Type vocabulary

Use the name **Knowledge Type** in Notes and TODO UI. Resolve terms by stable
slugs/IDs rather than display labels. Preserve numbered PARA container labels
(`1-Status`, `2-Projects`, `3-Areas`, `4-Resources`) and normalize hierarchy
without replacing existing term identities. Parent filtering expands descendants.
Keep normalization and query expansion in the shared vocabulary.

Container assignment is interface-specific: TODO's assignment controls exclude
structural roots; the native Notes/Gutenberg taxonomy panel intentionally allows
raw terms, including system/container terms. Stuff's app assigns leaf terms but
also preserves unrelated terms. Do not impose a global prohibition that changes
the native editor. See each package contract for its identity and assignment rules.

The core Knowledge Type REST collection preserves stored term descriptions. When
Personal Stuff is active and a description is empty, its shared vocabulary adds
a response-only derived role and root-to-leaf name/ID path, so constrained
clients can classify a paginated term without resolving its parents. This never
writes generated text to WordPress term storage.

## Integration boundaries

Shared base classes own settings, assets, logging and Knowledge access without a
monolith module registry or sibling injection. Package-specific REST namespaces
are appropriate where the package needs them; Stuff deliberately uses core REST
only. Preserve native Gutenberg content and provenance when editing another
consumer's records. Term assignment happens after `wp_insert_post()` in the
shared create helper: term-dependent side effects must run after assignment.

Pressidian is an independent consumer. Preserve immutable WordPress slugs,
Knowledge types, content and TODO operational metadata; trash is a lifecycle
signal, not an invitation to hard-delete. Consult Pressidian's current sync
contract for enrollment and Markdown/Gutenberg conversion rather than freezing
its evolving client rules in this repository.
