# Development and verification

Commands run from the repository root. `package.json`, `composer.json`, and the
selected `.wp-env*.json` are the source for exact commands and fixture versions.
Inspect the existing runtime before installing dependencies or restarting it.

```sh
npm ci
composer install
npm run build
npm run wp-env -- start
```

The default wp-env starts the six split packages. Single-package commands such
as `npm run wp-env:stuff` select the corresponding fixture. Knowledge is provided
by pinned Gutenberg with its experiment enabled after startup; this fixture is
not a release dependency. The default omits the standalone Abilities API plugin;
use the explicit legacy config only for that compatibility work.

## Focused checks

| Change | Relevant checks |
| --- | --- |
| PHP | `composer run lint -- "path/to/file.php"`; `composer run phpcbf -- "path/to/file.php"` for formatting |
| WordPress JS/CSS | `npm run lint:js`, `npm run lint:css`, and affected behavior tests |
| Backend behavior | `npm run test:unit:backend`; `npm run test:integration:backend` when integration behavior changes |
| Stuff content/search | `npm run test:unit:stuff` |
| Assets | `npm run build`, then inspect the affected browser screen |
| Packaging/shared loaders | [Packaging checks](packaging.md) |
| Documentation only | Check links, commands against scripts/configs, and `git diff --check`; do not start WordPress solely for prose edits |

Run focused tests first and broaden for shared behavior touched by the change.
Do not repeat successful suites without a new change or unresolved concern.
Use narrow PHPCS exceptions where the root `personalos` text-domain rule
conflicts with the package's own WordPress.org domain; never rename package
strings back to the monolith domain. Keep WordPress scripts, Stylelint and its
config compatible using `package.json` and the lockfile; do not disable rules to
hide dependency mismatches or lint generated/vendor CSS.

## Runtime boundaries and repair

Diagnose failures using command output, the selected fixture and logs. Routine
local dependency/build/config repairs needed for authorized implementation are
allowed. Preserve unrelated services, user data and local overrides; ask only
when the repair needs destructive resets, expands scope, or changes a shared or
production environment. Report the actual blocker and continue independent work.

wp-env mounts `shared/php` and `vendor` under `wp-content` for split loaders.
Mount the root test source as `wp-content/personalos-tests`, outside plugins,
so startup cannot reactivate the legacy monolith. The test command runs there.
An absent optional Abilities fixture must not write STDERR during PHPUnit
bootstrap, which breaks isolated tests before they can skip.

Read logs through `npm run wp-env -- run cli -- tail -n 100 wp-content/debug.log`.
Use package-owned WP-CLI commands when registered; do not assume legacy `wp pos`
is available in the split suite.

Host-specific domains belong in ignored `.wp-env.override.json` under
`env.development.config` (`WP_HOME`, `WP_SITEURL`); leave test URLs local. Use
backups and serialization-aware WP-CLI search-replace, skipping GUIDs, for an
authorized domain change. On M5, offer the shared machine-context skill's optional
Tailscale setup; localhost remains the default without project-specific approval.
Normal startup reactivates configured packages: do not leave standalone ZIP test
copies active alongside the same source plugins.

## Browser acceptance

Exercise actual creation and editing, including relevant mobile layouts, API
responses and console errors. For the suite, check Notes creation with required
identity/source terms, TODO creation/completion, AI Chat creation and the actual
abilities response shape, and Stuff save/upload. Activation and route presence
previously missed Notes creation and AI Chat failures; do not claim those are
fixed without reproducing and verifying them. Follow package contracts for the
rest of the affected flows and report anything not exercised.
