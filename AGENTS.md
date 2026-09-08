# PersonalOS contributor guidance

PersonalOS is a monorepo of six independent WordPress plugins built on Knowledge.
The current code lives in `packages/`, with bundled helpers in `shared/php`.
The root `personalos.php`, `modules/`, and `src-chatbot/` belong to the legacy
monolith; do not activate it alongside the split suite.

## Read for the task

Read the relevant contract before changing its behavior; do not load every
reference for an unrelated edit.

| Work area | Guidance |
| --- | --- |
| Shared Knowledge storage, terms, permissions, interoperability | [Knowledge contract](docs/knowledge-contract.md) |
| Local setup, tests, lint, environment repair | [Development](docs/development.md) |
| ZIPs, CI, releases, deployment | [Packaging](docs/packaging.md) |
| Stuff UI, media, taxonomy, operating API | [Stuff contract](packages/personal-stuff/TECHNICAL.md) and [operating skill](packages/personal-stuff/skills/personal-stuff/SKILL.md) |
| TODO storage, recurrence, history, ICS, abilities | [TODO contract](packages/personal-todo/TECHNICAL.md) |
| Notes library and native editors | [Notes contract](packages/personal-notes/TECHNICAL.md) |
| AI Chat | [AI Chat contract](packages/personal-ai-chat/TECHNICAL.md) |
| Readwise and Evernote | [Sync contracts](docs/sync-contracts.md) |
| Public landing page | Static site in `docs/`; use synthetic screenshot data, distinguish four apps from two sync integrations, and verify release assets before advertising downloads. Attach a full-page screenshot to landing-page PRs. |

`docs/*-plan.md` files record design history; use current contracts and source
for implementation. Read legacy module documentation only for legacy work or
an explicit behavior comparison.

## Working agreements

- Preserve unrelated tracked and untracked work. If a requested fresh branch
  would disturb it, use a separate worktree from the requested base.
- Prefer native WordPress APIs, storage and components. Add helpers only for a
  meaningful domain rule, useful reuse, or an intentional override point.
  Avoid speculative registries, migration layers and broad style refactors.
- Apply the repo's PHP/JS standards when changing that language; see
  `.cursor/rules/wordpress-coding-standards/`. The optional personal
  `artpi-wp-php-style` skill supplements the repo's actual lint configuration.
  Do not impose WordPress formatting on the separate legacy Next.js app.
- Match validation to the changed behavior. UI work needs a browser check of
  the interaction and resulting state; activation or a build alone is not UI
  verification. See the development guide for commands and test boundaries.
- Carry authorization through the requested deliverable: a request to create
  or update a PR includes scoped commits and pushes. A review-only request does
  not. Report the branch, push status, PR URL, validation and remaining blockers.
  Do not infer deployment or merging from a request to open a PR.
- Private deployment helpers and artifacts belong in ignored `tools/local/`.
  Inspect them only when needed for an explicitly requested deployment; keep
  host details, backups and credentials out of tracked documentation.

## Maintaining instructions

Update the existing rule when a decision changes and remove the superseded
rule. Add only durable guidance not already documented. Put package behavior
in its technical contract, shared behavior in the shared contracts, and session
progress or unresolved bugs in the issue/PR rather than a growing Lessons list.
For durable domain records, document stored versus derived fields, lifecycle,
API shape, permissions and regression checks in the package contract. Update the
routing table when adding a contract. Distinguish required behavior
from verified implementation; do not document an unfixed bug as already fixed.

`CLAUDE.md` points to this file. Repo skills live in `.agents/skills`, with
`.claude/skills` as the compatibility link; keep third-party skill bodies intact.
