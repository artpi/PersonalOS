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

To create a new prompt:

- Create a new **Note**.
- Assign it to the `prompts-chat` notebook.
- Write the prompt text in the note content (this becomes the system instructions).
- (Optional) Set a `pos_model` custom field (for example `gpt-4o` or `o1-preview`) to force a specific model when this prompt is active.

In the `src-chatbot` UI:

- These prompts are exposed via `window.config.chat_prompts`.
- The selected prompt slug is sent to the backend as `selectedChatModel`.
- The OpenAI module resolves the prompt note and uses its content as the system prompt for the Responses API call.

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
