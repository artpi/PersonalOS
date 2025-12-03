<!-- eec4a786-52c4-4a8d-827d-d72be28830de 7db33e64-9560-4b21-9ac5-f9fced49efd4 -->
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

### To-dos

- [ ] Add use_personalos and admin_personalos caps in fix_versions()
- [ ] Add map_meta_cap filter to check caps based on post ownership
- [ ] Test new capabilities and if the default test user (admin) has them, run tests
- [ ] Update create() methods to default to private status
- [ ] Write test for create methods in each module, run tests
- [ ] Update POS_Settings to handle scope flag (global vs user)
- [ ] Write tests for the scoped settings, test if proper users have acces to them, run tests
- [ ] Add scope flags to all module settings declarations
- [ ] Update specific modules tests for settings declarations, run tests
- [ ] Update Evernote/Readwise sync to loop through configured users
- [ ] Write tests if possible,run them
- [ ] Update IMAP to match emails to users by WP email address
- [ ] write tests for imap users if possible and email matching
- [ ] Implement token-to-user mapping for ollama/podcast access tokens, write tests and test
- [ ] Filter dashboard widgets to show only current user's content
- [ ] Verify REST API respects new capability checks, write tests and test