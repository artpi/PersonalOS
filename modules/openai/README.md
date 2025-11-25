# OpenAI Module

The OpenAI module powers AI features in PersonalOS:

- Conversational chat (via the `src-chatbot` Next.js app and the `/openai/vercel/chat` endpoint).
- Custom GPT integration (so ChatGPT can call back into your PersonalOS instance).
- System prompts stored as Notes, with per-prompt model selection.
- Audio transcription that turns recordings into Notes.

This file is **user-facing** documentation. For internal implementation details, see `modules/openai/TECHNICAL.md`.

## Custom GPT

PersonalOS integrates with OpenAI's Custom GPT so that a GPT running on `chatgpt.com` can:

- Browse and search your notes.
- Create and update todos.
- Work with notebooks and other PersonalOS data.

The plugin exposes a schema and system prompt that you can paste directly into your Custom GPT configuration:

- Go to **Tools → Custom GPT** in your WordPress admin.
- Copy the **system prompt** and **schema** shown on that page.
- Paste them into your Custom GPT configuration in ChatGPT.

[Open the Custom GPT configuration helper page](tools.php?page=pos-custom-gpt)

## Chat Prompts (personas)

You can create reusable system prompts to switch between different personas or workflows in the chatbot UI.

### Creating a Basic Prompt

- Create a new **Note**.
- Assign it to the `prompts-chat` notebook.
- Write the prompt text in the note content (this becomes the system instructions).
- (Optional) Set a `pos_model` custom field (for example `gpt-4o` or `o1-preview`) to force a specific model when this prompt is active.
- (Optional) Set the post slug (e.g., `prompt_default`) to control the URL identifier.

### Dynamic Prompts with the WP Ability Block

Prompts can include **dynamic content** by using the `pos/ai-tool` block. This block executes a WordPress Ability and outputs the result directly into the prompt at render time.

To add dynamic data to your prompt:

1. Edit your prompt Note in the WordPress block editor.
2. Add a **WP Ability** block (`pos/ai-tool`).
3. Select which ability to execute (e.g., `pos/get-notebooks`, `pos/todo-get-items`).
4. Configure parameters if needed.
5. Select which output fields to include.
6. Choose output format (JSON or XML).

**Example**: A prompt that includes your current TODOs:

```
You are my personal assistant. Here are my current tasks:

[WP Ability block: pos/todo-get-items]

Help me prioritize and complete these tasks.
```

When this prompt is used, the AI will see the actual TODO items rendered as JSON or XML.

### Available Abilities for Prompts

| Ability | Description |
|---------|-------------|
| `pos/system-state` | Current user info, system time |
| `pos/get-notebooks` | List of notebooks (filterable by flag) |
| `pos/todo-get-items` | TODO items from a specific notebook |
| `pos/get-ai-memories` | Previously stored AI memories |
| `pos/list-posts` | Blog posts and pages |

### Reusable Prompt Components

You can create modular prompts by embedding one Note inside another using the `pos/note` block. This is useful for:

- **Base prompts**: Create a "Default Prompt" with common instructions, then embed it in specialized prompts.
- **Shared context**: Define your user profile or preferences once, then include them in multiple prompts.

**Example structure**:

1. Create a "Default Prompt" Note with:
   - Your name and preferences
   - System state (`pos/system-state` ability block)
   - Notebook list (`pos/get-notebooks` ability block)
   - AI memories (`pos/get-ai-memories` ability block)

2. Create specialized prompts that embed the default:
   - "Helpful Assistant" prompt: Brief persona + `[pos/note: Default Prompt]`
   - "Creative Writer" prompt: Creative persona + `[pos/note: Default Prompt]`

This pattern keeps your prompts DRY (Don't Repeat Yourself) while allowing customization.

### Output Field Filtering

When using the WP Ability block, you can select which fields to include in the output. This helps reduce prompt token usage by excluding unnecessary data.

For example, with `pos/todo-get-items`:
- Include only `title` and `excerpt` for a summary view
- Include `ID` and `url` when you need the AI to reference specific items

### How Prompts Work Internally

In the `src-chatbot` UI:

- Prompts are exposed via `window.config.chat_prompts`.
- The selected prompt slug is sent to the backend as `selectedChatModel`.
- The OpenAI module resolves the prompt note and uses its content as the system prompt.
- All blocks (including `pos/ai-tool` and `pos/note`) are rendered through WordPress's `the_content` filter before being sent to the AI.

## Conversation History

Chat conversations are stored as regular Notes so that they can be searched, organized, and synced like any other content.

- Each conversation is a `notes` post.
- Messages are stored as `pos/ai-message` blocks inside the post content.
- Conversations are tagged into the `ai-chats` notebook by default.
- The chat UI passes a `conversation_id` to `/openai/vercel/chat`, and the backend appends new messages to that Note instead of creating a separate record.

You can browse and edit conversations from the standard Notes interface (filtering by the `ai-chats` notebook).

## Transcription

The module includes a transcription subsystem (`POS_Transcription`) that sends eligible audio attachments to OpenAI's `whisper-1` model:

- When an audio file is scheduled for transcription, the resulting text is stored on the attachment itself (`post_content` on the media item).
- If the attachment does **not** have a parent post, the transcription is also turned into a new **Note**:
  - The note uses the `notes` post type (same as chat conversations).
  - Content includes an embedded audio block plus the transcript text.
- If the attachment already belongs to another post (for example a daily note), the transcription is merged back into that parent post instead.

This means transcriptions become part of your Note system and can be searched, tagged, and organized alongside chat conversations and manual notes.
