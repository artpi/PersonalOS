# Packaging, releases and deployment

The current suite contains six independent plugins. Four expose private WpApps:
Notes (`/notes/`), TODO (`/todo/`), AI Chat (`/ai-chat/`), and Stuff (`/stuff/`).
Readwise and Evernote are background sync integrations with Settings pages.
App wp-admin entries are launchers, not duplicate app screens.

## Build and verify

```sh
npm run build
npm run package:plugins
npm run verify:split-packages
```

For one package:

```sh
npm run package:plugin -- --package=personal-stuff
npm run verify:split-packages -- --package=personal-stuff
```

The root WordPress scripts build is followed by `tools/sync-package-builds.mjs`.
Installed packages must use their own `build/` JS, CSS, block metadata and asset
manifests, not the root build or a sibling plugin's chunks. Shared CSS imports
must remain package-distinct during extraction.

`tools/package-plugin.mjs` bundles shared PHP under `includes/shared/`, WpApp
for the four app packages, and the minimal Evernote/PSR transport runtime for
Evernote. Use package headers and the build scripts for dependency/PHP versions,
not copied version lists. Each ZIP must activate without a sibling plugin or
the root monolith. Guard bundled shared classes against redeclaration.

Inspect ZIP contents and run `verify:split-packages`: check excluded files,
package-local assets/helpers, privacy and data-retention sections, proper
WordPress.org names/domains/licenses, and no placeholder readme or sibling
runtime dependency. Do not introduce `Requires Plugins` between suite packages
or remote executable admin scripts. These checks support store readiness but
are not evidence of store approval or of working UI flows.

## CI and releases

`.github/workflows/release.yml` builds a six-package matrix and uploads each
`<package-slug>.zip`. Update the relevant package header/readme versions using
three-part semver; do not change only the root legacy version.

The shared build action still supports a legacy preview fallback when no package
slug is passed. `pull_request_target` uses the base workflow while local actions
may come from the PR merge ref, so retain compatibility with base-workflow inputs
until a CI migration lands. This fallback is not the current suite deployment.
Verify available release assets before documenting downloads; a source merge
alone does not publish a package.

## Deployment

Deploy only when requested. The whole suite means all six independently built
packages; root `personalos.php` is the legacy monolith and must remain inactive.
Private helpers live in ignored `tools/local/`. Keep hosts, credentials and
backups there, not in tracked instructions. Verify installed package assets and
then exercise the creation flows in [Development](development.md#browser-acceptance).
