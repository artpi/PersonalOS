---
name: Multi-User Permission System for PersonalOS
overview: ""
todos:
  - id: ad878f02-5372-4ed3-99d8-e8a985857fb3
    content: Add use_personalos and admin_personalos caps in fix_versions()
    status: completed
  - id: 2dd8d984-d5ab-40bc-b4af-25b9c5d61a31
    content: Add map_meta_cap filter to check caps based on post ownership
    status: completed
  - id: 12c6aeee-d7e8-4cfc-b802-ebff48011291
    content: Test new capabilities and if the default test user (admin) has them, run tests
    status: completed
  - id: 5f94f234-29a0-431c-9a63-3a9e37b1e87c
    content: Update create() methods to default to private status
    status: completed
  - id: 8c16b0d5-2312-492e-8a2f-deeeebd5eb7c
    content: Write test for create methods in each module, run tests
    status: completed
  - id: 6fb6e7ca-a46b-4222-8307-dd11f0b1fb75
    content: Update POS_Settings to handle scope flag (global vs user)
    status: completed
  - id: 26501cfc-218c-4ba1-b832-ce23d9301685
    content: Write tests for the scoped settings, test if proper users have acces to them, run tests
    status: completed
  - id: d38f77a0-d9c0-46c1-8baa-d02598d1c30b
    content: Add scope flags to all module settings declarations
    status: completed
  - id: 8e06cd9d-2ebd-4858-b9ba-01f3381a237e
    content: Update specific modules tests for settings declarations, run tests
    status: completed
  - id: 41fef6f7-835a-434f-8f71-780c6a603f04
    content: Update Evernote/Readwise sync to loop through configured users
    status: pending
  - id: a8109789-2f8f-4afd-b730-09e9ded7192a
    content: Write tests if possible,run them
    status: pending
  - id: 3703e080-4827-49b5-af36-beff42a08260
    content: Update IMAP to match emails to users by WP email address
    status: pending
  - id: 570ada98-80b8-4d80-92de-3d68448e5090
    content: write tests for imap users if possible and email matching
    status: pending
  - id: 0dc0199a-3930-491f-b0b9-5499601325b5
    content: Implement token-to-user mapping for ollama/podcast access tokens, write tests and test
    status: pending
  - id: 64230487-7d37-4883-bcae-67bbeb32ec92
    content: Filter dashboard widgets to show only current user's content
    status: pending
  - id: 71aad706-688a-430c-8294-670b1842987b
    content: Verify REST API respects new capability checks, write tests and test
    status: pending
---

# Multi-User Permission System for PersonalOS

## Implementation Plan

### 1. Simplified Capability System

**Two capabilities:**

| Capability | Roles | What it allows |
|------------|-------|----------------|
| `use_personalos` | Editor, Administrator | Use PersonalOS for your own notes & todos |
| `admin_personalos` | Administrator only | Access other users' private notes & todos |

**Files to modify:**

- [modules/class-pos-module.php](modules/class-pos-module.php) - Add `map_meta_cap` filter
- [personalos.php](personalos.php) - Add capabilities in `fix_versions()` on first install

### 2.

###  Private Post Status by Default

**Files to modify:**

- [modules/notes/class-notes-module.php](modules/notes/class-notes-module.php)
- [modules/todo/class-todo-module.php](modules/todo/class-todo-module.php)

**Changes:**

- `create()` methods default to `post_status => 'private'`
- Starter content (prompts) can remain `publish` for sharing
- Update `autopublish_drafts()` to publish as private

### 3. Setting Scope System (Global vs User)

**Files to modify:**

- [class-pos-settings.php](class-pos-settings.php) - Handle scope-based storage and UI
- [modules/class-pos-module.php](modules/class-pos-module.php) - Update `get_setting()`
- All module files - Add `'scope' => 'user'` or `'scope' => 'global'`

**Storage:**

- `scope === 'user'` → user meta: `pos_{module_id}_{setting_id}`
- `scope === 'global'` → wp_options: `{module_id}_{setting_id}`

### 4. Per-User Sync Jobs (Evernote, Readwise)

**Files to modify:**

- [modules/class-pos-module.php](modules/class-pos-module.php) - `External_Service_Module`
- [modules/notes/class-notes-module.php](modules/notes/class-notes-module.php) - Remove `user` setting
- [modules/evernote/class-evernote-module.php](modules/evernote/class-evernote-module.php)
- [modules/readwise/class-readwise.php](modules/readwise/class-readwise.php)

**Changes:**

- Single cron job loops through all users with configured tokens
- Try/catch per user (one failure doesn't block others)
- Remove `notes_user` setting and `switch_to_user()` method

### 5. IMAP: Shared Inbox with User Matching

**Files to modify:**

- [modules/imap/class-imap-module.php](modules/imap/class-imap-module.php)

**Behavior:**

- IMAP credentials are global (admin-configured)
- Match email recipient to WordPress user by email address
- Create content as the matched user

### 6. Access Token → User Mapping

For access tokens (ollama, podcast), need to identify which user owns the token:

- Option A: Query users by meta to find matching token
- Option B: Encode user ID in token format

### 7. Dashboard Widgets Filter

**Files to modify:**

- [modules/notes/class-notes-module.php](modules/notes/class-notes-module.php) - `notebook_admin_widget()`

**Changes:**

- Add `'author' => get_current_user_id()` to queries (unless admin)

### 8. REST API Permission Updates

**Files to modify:**

- [modules/class-pos-module.php](modules/class-pos-module.php) - `POS_CPT_Rest_Controller`

## Setting Scope Reference

| Module | Setting | Scope |
|--------|---------|-------|
| **OpenAI** | `api_key` | global |
| **OpenAI** | `prompt_describe_image` | global |
| **Evernote** | `token` | user |
| **Evernote** | `synced_notebooks` | user |
| **Evernote** | `active` | user |
| **AI Podcast** | `token` | user |
| **AI Podcast** | `tts_service` | global |
| **AI Podcast** | `elevenlabs_voice` | global |
| **Readwise** | `token` | user |
| **IMAP** | all settings | global |
| **Ollama** | `ollama_auth_token` | user |

## Follow-up Items (Not in Initial Scope)

- **Mine/Team toggle** - UX improvement for admins to filter their own content
- **Slack module** - needs same per-user treatment
- **Daily module** - needs user context for content creation
- **Transcription module** - needs to be user-aware
- **Taxonomy privacy** - notebooks are shared for now
- **WP Admin list tables** - `pre_get_posts` filter for notes CPT