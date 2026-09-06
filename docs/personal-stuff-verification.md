# Personal Stuff verification

Verified locally on 2026-09-06 using wp-env, WordPress 7.1 and the pinned Gutenberg 23.7.0 Knowledge fixture. The development site runs all six split packages; the separate test site also exercised the release ZIP with only Gutenberg and Personal Stuff active.

## Automated checks

- Full PHP unit suite: 171 tests, 1,047 assertions, 10 existing skips; no failures.
- Stuff PHP coverage: six tests / 31 assertions covering vocabulary, native REST permissions/content, Notes editor compatibility and upload filename/thumbnail behavior.
- Stuff JS suite: four passing tests using WordPress block parsing and serialization, including unknown blocks, gallery order and search.
- PHP and JS lint passed. `git diff --check` passed.
- Production build passed; package verification accepted all six standalone ZIPs, including Stuff's bundled helpers, WpApp, editor assets, technical contract and operating skill.
- The extracted Stuff ZIP activated independently. Its shared base loaded from the ZIP's own `includes/shared` directory, the fixed `stuff` term existed, and authenticated native Knowledge REST returned HTTP 200.
- Operating skill validation passed.

CSS lint could not start because the repository configuration references the unavailable `@wordpress/stylelint-config/scss-stylistic`. The environment was left unchanged. Styles were checked in the browser; normal webpack bundle-size warnings remain.

## Browser checks

Used the in-app browser and Chrome against `http://localhost:8901/stuff/`.

- Created raw Home / Garage place terms and a Travel tag, then assigned them to private item posts.
- Created a name-only item, uploaded photos, edited the description inline, and reopened saved records.
- Verified typo search, parent-place filtering and the no-photo filter. Contextual Previous navigation followed the filtered collection.
- Uploaded through Stuff's photo input, native Gutenberg, and the Media Library inside Stuff. All new files had 32-character random hexadecimal basenames. WordPress image sizes retained the random stem and uploads had the saved item as parent.
- Edited a gallery caption and photo order in native Gutenberg with Personal Notes active. Stuff displayed the saved caption and cover order. A later place/tag-only save retained the exact `post_content` checksum.
- Confirmed Personal Notes' sidebar remains available in the Stuff Gutenberg editor. The Notes library displayed its own note without automatically enrolling Stuff items. A Markdown note still opened in the classic editor.
- Checked dark appearance and a 390px Chrome viewport; document width equaled viewport width without horizontal overflow.
- Opened native Print preview with the full sample collection. After correcting print layout overflow, Chrome's PDF renderer produced one page containing both sample items and their photos. The final PDF check captured the app-generated print content; temporary browser instrumentation was removed by reloading. No physical print job was sent.
- Confirmed anonymous `/stuff/` access redirects to login.

The development site is left running with synthetic sample records: Camera kit, Charging cables, and the Packing checklist note. Sample images are the source Stuff app's icons. Public upload URLs remain public even though the items and app are private.

## Scope of evidence

These checks establish the native storage/editor and standalone package paths on the tested fixture. They do not establish private media access, atomic multi-editor concurrency, offline support, or exhaustive compatibility with third-party Gutenberg blocks. Those are not features of this implementation.
