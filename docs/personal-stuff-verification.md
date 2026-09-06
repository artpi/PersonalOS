# Personal Stuff verification

Verified locally on 2026-09-06 using wp-env, WordPress 7.1 and the pinned Gutenberg 23.7.0 Knowledge fixture. The development site runs all six split packages; the separate test site also exercised the release ZIP with only Gutenberg and Personal Stuff active.

## Automated checks

- Full PHP unit suite: 171 tests, 1,047 assertions, 10 existing skips; no failures.
- Stuff PHP coverage: six tests / 31 assertions covering vocabulary, native REST permissions/content, Notes editor compatibility and upload filename/thumbnail behavior.
- Stuff JS suite: four passing tests using WordPress block parsing and serialization, including unknown blocks, gallery order and search.
- PHP, JS and repository-wide WordPress CSS lint passed. `git diff --check` passed.
- Production build passed; package verification accepted all six standalone ZIPs, including Stuff's bundled helpers, WpApp, editor assets, technical contract and operating skill.
- The extracted Stuff ZIP activated independently. Its shared base loaded from the ZIP's own `includes/shared` directory, the fixed `stuff` term existed, and authenticated native Knowledge REST returned HTTP 200.
- Operating skill validation passed.

CSS lint initially could not start because the lockfile paired WordPress scripts 30.7.0 with the obsolete Stylelint 14 / WordPress config 21.33.0. After authorization to repair the tooling, the project explicitly pins Stylelint 16.12.0 and the matching WordPress config 23.6.0. A clean `npm ci` succeeds, and all WordPress source styles pass the unmodified WordPress lint rules. Generated/bundled files and the separate Next.js project are excluded. Formatting, equivalent color notation, selector ordering and a duplicate rule were corrected; normal webpack bundle-size warnings remain.

The tooling fix was checked with Node 20.20.2 and npm 10, matching CI's Node major: clean install, full CSS lint, four JS tests, production build and all six ZIP checks passed. A declaration comparison confirmed that all 13 reformatted stylesheets retain equivalent properties/values per selector and media context; reordered rules were reviewed for cascade behavior. CSS lint is now part of the PR workflow.

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

## UX refinement — 2026-09-06

The follow-up design uses cream/green and dark green palettes, uncropped photo cards, compact search with optional filters, place breadcrumbs, and a native DataViews header containing counts and actions. Add/edit uses the entire viewport on phones, a sticky save/progress footer, and optional photo/block controls. The source feedback and superseded requirements are recorded in `docs/personal-stuff-ux-feedback.md`.

- Browser checks on the development site at `http://artpi-m5.tailaea879.ts.net:8901/stuff/`: name-only creation, saved place assignment, Home subtree browsing, search, photo filtering and empty results, detail/editor transitions, and saving existing gallery content with its caption retained.
- The restyled file input opened the chooser and uploaded a synthetic icon into a native gallery, with randomized basename `c2d61a7cdcdd3a4c430625ece75c9a0e.png`. Progress changed from saving/uploading to saved; the photo filter reflected the upload.
- Chrome responsive inspection at 390×844 and 320×740 confirmed the editor starts at (0, 0), matches the viewport width/height, has no horizontal overflow, uses 16px inputs, and keeps the save footer at the bottom. Light and dark appearances were visually inspected. These are emulated layout checks; real iOS keyboard, focus zoom and camera behavior are not established by them. Chrome's automated touch dispatch was unavailable; functional interactions were exercised in the in-app browser.
- Personal Notes still lists the existing Packing checklist note and does not list the new Stuff items. No shared helper, PHP, storage, or Notes asset changes were made in this UX pass.
- Four block/search JS tests, JS lint, all WordPress source CSS lint, production build, and all six standalone package ZIP checks pass. Backend evidence above predates this JS/CSS pass; the previously reported test-site duplicate ZIP/source plugin activation issue was left untouched.

Print media emulation with dark mode enabled confirms both the document and body remain white; screen theme backgrounds are scoped away from printing.
