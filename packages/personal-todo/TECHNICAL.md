# Personal TODO Technical Contract

This document is the canonical storage and behavior contract for Personal TODO.
It describes how a TODO is represented in WordPress, which values are durable or
derived, and the lifecycle guarantees that the UI, REST API, Abilities API, ICS
feed, and tests must share.

## App interaction

Preserve compact quick-add, the DataViews list/filter/actions, and the full edit
modal with scheduling, recurrence, dependencies and history. Use shared Knowledge
Type terms in assignment controls instead of the old notebook controls.

## Runtime Dependencies

Personal TODO requires a WordPress Knowledge surface but does not require any
other Personal plugin.

The shared Knowledge bridge resolves these runtime identifiers:

| Concept | Identifier |
| --- | --- |
| Post type | `wp_knowledge` |
| Type taxonomy | `wp_knowledge_type` |
| Source meta | `knowledge_source` when registered, otherwise `_personalos_source` |

Package code must use the bridge rather than duplicate availability and metadata checks.

## Canonical Task Identity

One actionable task is one Knowledge post. A post is a Personal TODO task when:

1. Its post type is the bridge-resolved Knowledge post type.
2. It has the `todo` term in the bridge-resolved Knowledge type taxonomy.

Every task created by Personal TODO must also have the `artifact` term. Ordinary
tasks must not receive the `plan` or `personalos` terms.

At registration, the standalone plugin ensures that `artifact`, `todo`, `inbox`,
`now`, `later`, and `follow-up`, together with their required parent terms, exist
in the resolved taxonomy. Pending transitions must not depend on another plugin
having provisioned their destination terms first.

There is no package-specific TODO CPT, taxonomy, custom table, or aggregate post
containing multiple tasks.

## Durable Post Fields

| WordPress field | Type | Meaning |
| --- | --- | --- |
| `ID` | integer | Stable task identifier and target for relationships and cron arguments. |
| `post_type` | string | Bridge-resolved Knowledge post type. |
| `post_status` | string | Normally `private`; `trash` means completed. Queries may also include `publish` and `future` Knowledge rows that qualify as tasks. |
| `post_author` | integer | Owning WordPress user. User-created tasks are private and authored by that user. |
| `post_title` | string | Task title. |
| `post_excerpt` | string | Short task notes shown in DataViews and exported as the ICS description. |
| `post_content` | string | Optional extended task details for Knowledge-aware consumers. |
| `post_date` | datetime | Local counterpart of the task creation or scheduled-action time. |
| `post_date_gmt` | datetime | UTC counterpart used to schedule a future task action. |
| `post_modified_gmt` | datetime | Last modification time used by the app's default sorting. |

The source meta for package-created rows is `native:personal-todo`, stored under
the bridge-resolved source meta key.

## Knowledge Type Terms

Terms are stored in the bridge-resolved Knowledge type taxonomy.

| Term class | Examples | Rule |
| --- | --- | --- |
| Required identity | `artifact`, `todo` | Both must be present on package-created tasks. |
| Status | `inbox`, `now`, `later`, `follow-up` | Assignable child terms. A new task defaults to `inbox` when no assignable terms are supplied. |
| Organization | project, area, and resource child terms | Optional and shared with other Knowledge consumers. |
| Containers | `status`, `project`, `area`, `resource`, `archive` | Query-only hierarchy nodes; never assign directly to a task. |
| Other system terms | `note`, `conversation`, `memory`, `skill`, sync-source terms | Not offered by the TODO assignment UI. |

REST term filters use term slugs. Parent-level queries are expanded through the
shared vocabulary, and multiple supplied terms use AND semantics.

On update, a supplied `term` or `terms` value replaces the task's assignable term
set while preserving the required `artifact` and `todo` identity terms. The app
sends `inbox` when its editor would otherwise submit no assignable term.

## Task Meta

Task behavior is stored in registered post meta on the resolved Knowledge post
type.

| Meta key | Type | Default | Meaning |
| --- | --- | --- | --- |
| `url` | string URL | `''` | Optional action URL associated with the task. |
| `reminders_id` | string | `''` | Reserved identity for Reminders interoperability; currently dormant. |
| `pos_recurring_days` | non-negative integer | `0` | Days after completion when a replacement task should become actionable. Zero disables recurrence. |
| `pos_blocked_by` | non-negative integer | `0` | ID of another TODO Knowledge post that blocks this task. Zero or absent means unblocked. |
| `pos_blocked_pending_term` | term slug string | `''` | Destination Knowledge type term appended when a schedule fires or a blocker completes. Recurrence requires a non-empty valid destination. |

Scheduling is not stored in separate meta. It is represented by `post_date_gmt`
plus a single WP-Cron event named `personal_todo_scheduled` with the task ID as
its only argument.

The shared row helper assigns Knowledge terms after `wp_insert_post()`. Any flow
that creates a pre-scheduled task, including recurrence, must therefore invoke
the scheduling side effect after the helper returns and the `todo` term is
available. The scheduling operation is idempotent for an existing task event.

## Derived Values

These response fields must be derived rather than duplicated into post meta:

| Field | Derivation |
| --- | --- |
| `terms` | Current Knowledge type term slugs. |
| `blocking` | IDs of readable TODO Knowledge posts whose `pos_blocked_by` equals this task ID. |
| `scheduled` | Next `personal_todo_scheduled` event for this task, formatted as an ISO 8601 UTC string; empty when absent. |
| `edit_url` | Native WordPress edit URL for the Knowledge post. |
| `history` | The 20 most recent approved `todo_note` comments, newest first. |

## Lifecycle

### Creation

Creation inserts a private Knowledge post for the current user, assigns
`artifact`, `todo`, and the requested assignable terms, and sets the source to
`native:personal-todo`. If no assignable term is supplied, creation adds
`inbox`.

If `scheduled_for` is supplied, it is parsed into `post_date` and
`post_date_gmt`. A future GMT date must produce one `personal_todo_scheduled`
single event.

### Editing And Rescheduling

Task fields, task meta, and assignable terms can be updated independently. If an
update includes `scheduled_for`, any existing task event is unscheduled before
the post date changes. A future replacement date creates a new event. An empty
`scheduled_for` clears the event and resets the post date to the current time.

### Scheduled Transition

When `personal_todo_scheduled` fires, the plugin appends the valid term named by
`pos_blocked_pending_term`. Existing terms are preserved. The pending destination
meta remains available for future recurrence; only `pos_blocked_by` is cleared.

### Dependencies

A blocked task stores the blocking task ID in `pos_blocked_by` and the destination
term in `pos_blocked_pending_term`. Completing the blocking task finds readable
tasks that reference its ID, appends each destination term, and removes each
`pos_blocked_by` value. Other terms remain assigned.

### Completion

Completion always uses `wp_trash_post()`. This is the domain operation, not a
plain status update. Completion side effects run on `trashed_post`, after core has
trashed pre-existing comments, so the new completion history entry remains
approved and visible. The completion hook must:

- add a `Completed task.` history entry;
- create the next recurrence when configured;
- release tasks blocked by this task; and
- remove any pending cron event for the completed task.

The UI's "Complete and stop recurring" action first sets
`pos_recurring_days` to `0`, then invokes the same completion route.

### Recurrence

When a task with `pos_recurring_days > 0` is completed, recurrence must create one
new Knowledge task owned by the same author. Recurrence also requires a valid,
non-empty `pos_blocked_pending_term`; without one, no replacement is created.

The replacement must:

- copy title, excerpt, content, previous active status, and all non-private meta;
- preserve all current Knowledge type terms except the pending destination term;
- retain `artifact`, `todo`, `pos_recurring_days`, and the pending destination;
- set its date to the completion time plus `pos_recurring_days` days;
- schedule one `personal_todo_scheduled` event for that date; and
- receive a `todo_note` history entry identifying the source task ID.

Removing the destination term keeps the replacement out of that actionable state
until its cron event appends the term again.

## History

History is stored as approved WordPress comments:

| Comment field | Contract |
| --- | --- |
| `comment_post_ID` | Task Knowledge post ID. |
| `comment_type` | `todo_note`. |
| `comment_approved` | `1`. |
| `user_id` | Current user when the event is recorded. |
| `comment_content` | Sanitized HTML containing the timestamp and change list. |

History records title, notes, extended-content, and Knowledge type changes, plus
scheduling, completion, and recurrence-copy events. It is not duplicated into
post meta.

## REST API

All package routes use the `personal-todo/v1` namespace.

| Method and route | Behavior |
| --- | --- |
| `GET /tasks` | Lists readable open tasks with search, term, status, ordering, and pagination arguments. |
| `POST /tasks` | Creates a task. |
| `GET /tasks/{id}` | Returns one readable task. |
| `PUT/PATCH /tasks/{id}` | Updates task fields, meta, terms, or schedule. |
| `POST /tasks/{id}/complete` | Completes through the trash workflow. |
| `GET /ics?token=...` | Returns the token owner's scheduled tasks as raw `text/calendar`. |

The canonical formatted task object is:

```json
{
  "id": 123,
  "title": "Prepare release",
  "excerpt": "Check the changelog and package ZIPs.",
  "content": "",
  "url": "https://example.com/release",
  "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit",
  "terms": [ "artifact", "todo", "now", "project-personalos" ],
  "post_status": "private",
  "author": 1,
  "date_gmt": "2026-08-06 10:00:00",
  "modified_gmt": "2026-08-06 10:05:00",
  "pos_blocked_by": 0,
  "pos_blocked_pending_term": "now",
  "pos_recurring_days": 7,
  "blocking": [],
  "scheduled": "2026-08-13T10:00:00+00:00",
  "history": []
}
```

Collection pagination currently returns a JSON array without custom total-count
headers. The app fetches pages of 100 until it receives a short page, then applies
DataViews filtering, sorting, and pagination locally.

## Permissions And User Scope

- Listing requires an authenticated user.
- Users without `edit_others_posts` are restricted to their own tasks.
- Individual reads use `current_user_can( 'read_post', $id )`.
- Updates and completion use `current_user_can( 'edit_post', $id )`.
- Creation requires `edit_posts`.
- Private Knowledge row permissions remain the source of truth.

## Abilities API

When the WordPress Abilities API is available, the plugin registers:

- `personal-todo/list-tasks`
- `personal-todo/create-task`
- `personal-todo/update-task`
- `personal-todo/complete-task`

Ability callbacks reuse REST mutation behavior so completion, recurrence,
dependencies, terms, history, and permissions cannot diverge between interfaces.
The list ability returns all readable open tasks unless a term is supplied. The
create ability defaults to `inbox` unless its caller supplies a term.

## ICS Feed

The feed token is stored per user in user meta as
`personal_todo_ics_token`. Tokens shorter than 12 characters, unknown tokens, and
missing tokens are rejected.

The feed includes only scheduled task events owned by the resolved token user.
Each event contains a stable task UID, UTC start time, title, optional notes,
Knowledge type names as categories, and optional action URL.

## Compatibility And Retention

- Old `todo` CPT rows and `notebook` terms are not read or migrated.
- The package does not depend on Personal Notes or another Personal plugin.
- Deactivation or uninstall does not delete Knowledge tasks, task meta, history
  comments, or ICS tokens.
- Consumers should identify tasks by the resolved Knowledge post type plus the
  `todo` term, not by package PHP classes or REST routes.

## Required Regression Tests

The behavioral contract requires tests for:

1. Creation defaults and required identity terms.
2. User ownership and private-row permissions.
3. REST create, list, filter, update, and completion.
4. History comments for content, term, scheduling, and completion changes.
5. Scheduled transitions, schedule clearing, and rescheduling.
6. Dependency release and preservation of existing terms.
7. Recurrence copying, pending-term removal, cron creation, and cron transition.
8. "Complete and stop recurring" behavior.
9. ICS token ownership, user isolation, and event contents.
10. Ability parity with the REST lifecycle.
