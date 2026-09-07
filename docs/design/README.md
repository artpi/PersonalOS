# Knowledge landing page design

The public site is served directly from `docs/` by GitHub Pages at `personalos.net`. It has local CSS, a small script for screenshot tabs, and no runtime CDN, font service, or build dependency.

Preview it from the repository root:

```bash
python3 -m http.server 4173 --bind 127.0.0.1 --directory docs
```

Open `http://127.0.0.1:4173`.

## Design captures

- [Full-height desktop, 1440px wide](knowledge-landing-desktop.png)
- [Full-height mobile, 390px wide](knowledge-landing-mobile.png)

The three app screenshots under `docs/assets/knowledge-*.png` are unmodified browser captures of the running local wp-env site at 1440×900. The site had all six split plugins active, with its pinned Gutenberg Knowledge fixture. An isolated author account named Demo owns eight synthetic notes, seven synthetic tasks, and four synthetic inventory items. The Stuff screenshot is filtered to the synthetic Field kit tag. No personal notes, photos, or credentials are included. The hero map and record panel are explanatory illustrations, not app screenshots.

## Verification — September 6, 2026

- CSS and JavaScript pass the repository's WordPress linters; `node --check docs/site.js` and `git diff --check` pass.
- Chromium browser checks at 320, 375, 390, 768, 820, 1024, and 1440px: no horizontal overflow, page errors, or failed local asset responses.
- All three screenshot tabs work with clicks and keyboard navigation, including arrow keys, Home, and End. Selected state, focus, panel visibility, URL decoration, and captions update together.
- Axe's WCAG 2 A/AA and WCAG 2.1 AA checks report zero violations at the checked 1440px desktop viewport. This is an automated scan, not a comprehensive accessibility certification.
- All internal anchors and linked repository paths resolve. The default screenshot and all six modules remain available without JavaScript.
- Desktop and mobile captures were visually reviewed. Screenshots were decoded before full-page capture; the default tab is Notes. Reduced-motion preference is respected.
- The latest published release was inspected: `0.3.0` has the five pre-Stuff ZIPs. The landing page links to the releases list and identifies Stuff as source-available while its first ZIP is pending.

This change is static site content, styles, screenshots, and installation documentation. Plugin code and wp-env configuration are unchanged; backend suites were not rerun for this page redesign.
