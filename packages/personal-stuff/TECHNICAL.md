# Personal Stuff

Personal Stuff is a JS inventory app over native WordPress Knowledge, Knowledge Type, and Media endpoints. PHP provisions vocabulary, exposes the WpApp, enables the item editor, and scopes filename randomization. It registers no REST routes, abilities, custom post types, taxonomies, post/term meta, settings, or tables.

## Canonical representation

| Value | Stored representation |
| --- | --- |
| Item | One `wp_knowledge` post; private on creation, current author |
| Identity | Native post ID; new-item slug is a random UUID and remains stable when the title changes |
| Name | `post_title` |
| Description and photos | `post_content`, serialized Gutenberg blocks |
| Identity terms | `artifact` and `stuff-item` in `wp_knowledge_type` |
| Physical place | Zero or one descendant term of `stuff-places` assigned to the item |
| Tags | Descendant terms of `stuff-tags` assigned to the item |
| Place or tag | Raw term `name`, `slug`, `parent`, `description` |
| Media | Native attachment post, file, and WordPress attachment metadata |

```
artifact
stuff
  stuff-item
  stuff-places
    Home
      Garage
        Blue box
  stuff-tags
    Travel
```

The five fixed terms are resolved by slug. Activation creates missing terms if Knowledge is available. Each later `init` checks actual terms so deferred availability needs no installed flag. Only fixed Stuff term parents are normalized; existing labels/descriptions and user branches are retained. Dynamic place/tag CRUD uses core taxonomy REST.

The app assigns the leaf identity, physical location and tags, preserving unrelated Knowledge Types. It never assigns structural roots itself. Native Gutenberg's raw taxonomy panel remains unrestricted. If another editor assigns multiple physical places, Stuff displays them and its editor warns that saving chooses the selected place. No quantity, linked place post, term content ID, place gallery, or custom Stuff metadata exists.

Derived in memory: breadcrumbs, descendants, cover, photo count, search index, filtered collection, previous/next order. Filters and item selection are URL query parameters (`q`, `place`, `tag`, `photo`, `item`); place/tag filter values are slugs. DataViews layout and paging stay in memory. There is no localStorage database, service worker or offline write queue.

Stuff owns links for Knowledge rows carrying the `stuff-item` identity. Native
permalinks and shortlinks resolve to `/stuff/?item=<post ID>`. An editable legacy
`?p=<post ID>` request redirects there as well; unauthorized requests retain
WordPress's normal response.

## Content and editing

Core paragraphs, images and galleries are the only native structures needed. Gallery children and document block order define photo order; the first image is the cover. Captions, alt text, image links, gallery layout and any other blocks live in their ordinary block attributes/HTML.

The app uses `BlockEditorProvider`, `BlockList`, native block settings, the core Media Library, and `@wordpress/blocks` parsing/serialization. Unavailable custom blocks retain Gutenberg's original-content representation; users can open the full editor for plugin-specific controls. A title/place/tag edit does not send `content` when it has not changed. Content edits use normal Gutenberg serialization, which may normalize delimiter whitespace. The inline description editor changes only the first top-level paragraph (or prepends one), preserving other blocks.

The app shell and Add item action render without waiting for REST collection requests. Clicking Add item immediately opens a transient editor and generates its UUID slug in memory without creating a Knowledge row. Name and block content are editable while the fixed vocabulary and inventory load independently in the background. Save and photo upload become available as soon as the required vocabulary is ready; they do not wait for the inventory. Save creates the private row with that slug. The first photo upload also saves the row because WordPress Media requires a saved, authorized parent; closing an untouched new editor leaves no empty item behind. Existing records retain their slug and status; publishing, trash and restore use WordPress's native editor controls.

The inventory uses a search-led photo grid with uncropped covers, optional filters, and derived place breadcrumbs/child navigation. Add/edit fills the viewport at widths up to 700px, with 16px or larger inputs and a sticky save/progress footer. Block settings remain optional and stack below the content on phones. New-item presentation is transient React state, not persisted metadata. DataViews `previewSize` means a column count, not a pixel width; leave it unset for responsive defaults. DataViews still owns filtering, sorting and layouts; the app progressively increases the first page through an intersection sentinel to provide infinite scrolling. Collection thumbnails use responsive candidates from WordPress-rendered image markup while detail and print views retain full content.

Before a Stuff save, the app reads the current row and compares content, title, modification time and taxonomy assignments with the last version it loaded/saved. A mismatch requests reopening the item. This is a best-effort stale-edit guard, not an atomic lock. WordPress remains responsible for native editor locking, revisions and permissions.

## Uploads and recovery

New files are renamed by `wp_handle_upload_prefilter` and `wp_handle_sideload_prefilter`, before the public move. The basename is 16 cryptographically random bytes rendered as 32 hexadecimal characters, followed by an extension validated with `wp_check_filetype` and the current user's allowed MIME types. Core still validates actual file content. Random-number failure and disallowed extensions fail closed.

The filters require both `upload_files` and `edit_post` on a verified Stuff item. REST upload context comes from the `post` parameter on `POST /wp/v2/media`, scoped with before/after-callback hooks. The Gutenberg-only API-fetch middleware supplies that parent for uploads lacking it. Native Media Library uploads use core's `post_id`; the Stuff editor gives the library its saved item ID. Unrelated uploads are untouched.

Original, scaled and thumbnail files retain the randomized stem through WordPress's normal image processing. Existing attachments are never renamed. External URL references are not sideloaded. The file URLs are public, including if the parent is private; randomness reduces guessability and does not provide media authorization. EXIF and other original image metadata are not stripped by Stuff.

The explicit photo uploader saves the item first, uploads sequentially and saves each successful image block before continuing. Other fields and blocks remain editable during this background work, while Save, Close, and starting another file upload remain unavailable. Edits made during an in-flight request stay marked as unsaved and are included in the next upload checkpoint or explicit save. Earlier successes survive later failure. Block-editor/Media Library uploads use normal editor Save behavior. Errors retain the editor state; there is no blind automatic retry of uncertain POSTs. Recover an uncertain upload from the native Media Library before trying again. Removing a photo block unlinks it; permanent attachment deletion belongs to Media Library.

## Integration and API

`PersonalOS_Wp_App` wraps pinned WpApp 1.3.2 at `/stuff/`, requires `edit_posts`, supplies My Apps registration when supported, and isolates the app from the theme. Pretty permalinks are required by this top-level route. Admin menus and plugin action links are launchers. The existing WpApp helper may use its shared transient rewrite-flush option during activation; Stuff adds no persistent options or term-ID registry.

PHP passes paths discovered with `rest_get_route_for_post_type_items` and `rest_get_route_for_taxonomy_items`, plus the taxonomy's actual REST field. JS uses API-fetch's native nonce middleware:

- Knowledge collection/item CRUD: `/wp/v2/knowledge`, using `context=edit` for raw content.
- Terms: resolved core Knowledge Type REST collection; paginate all terms and expand descendants in JS.
- Empty term descriptions in that core collection receive a response-only, derived role and root-to-leaf path with term IDs. Stored descriptions are never changed.
- Files: `/wp/v2/media`, with `post=<item ID>` on upload.

Every permission decision is WordPress's existing post, term or attachment permission. Taxonomy terms retain the Knowledge runtime's native visibility. No claim is made that raw term names/descriptions are private merely because the app is private.

Personal Notes deliberately lists `note` records; Stuff does not add the `note` identity. Items therefore do not become ordinary Notes rows automatically. They share the same CPT, taxonomy and native editor. Notes' sidebar remains available on Stuff items. Stuff's editor-selection filter runs after Notes and enables Gutenberg for Stuff items even if empty. Other Knowledge rows keep Notes' content-based classic/Gutenberg choice.

All content remains on deactivation/uninstall. Release builds bundle shared helpers and WpApp inside this package; the package has no runtime dependency on Personal Notes or the monorepo.

## Verification

From the repository root:

```
npm run build
npm run test:unit:stuff
npm run test:unit:backend
npm run package:plugins
npm run verify:split-packages
npm run wp-env -- start
```

The suite config mounts Stuff with Notes and the other packages. `.wp-env.personal-stuff.json` provides the single-package fixture. The fixtures use pinned Gutenberg 23.7.0 to expose Knowledge; it is not a release dependency. On a fresh site, save a pretty permalink structure in Settings > Permalinks.

`PersonalStuffTest` covers idempotent/deferred raw terms without plugin options, Notes editor compatibility, private native REST rows, unknown content/unrelated term retention, permission-scoped filenames and raw-body upload derivatives. JS tests use the core serializer for gallery validity/order, unknown block retention, description isolation, and accent/typo/path search. The Jest command selects Node exports for dependencies while retaining JSDOM for block serialization.

Browser checks should cover term parent changes, a camera/file upload, gallery caption/order, inline description editing, native Gutenberg round trips with Notes active, URL filters, collection navigation, responsive layout, and Print/PDF. Check anonymous app access and inspect attachment filenames and generated sizes. Do not cache authenticated responses in a broad service worker.

## Design feedback

[Session-derived UX feedback](../../docs/personal-stuff-ux-feedback.md) provides
context for search, photos and mobile editing. Use the current contract above
when older feedback describes superseded behavior.
