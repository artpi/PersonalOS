# Saving conversation history

Conversation history is stored in the WordPress database using the `notes` Custom Post Type (provided by the Notes module). Each conversation is a single Post, and individual messages are stored as Gutenberg blocks within the post content.

## Data Model

- **Post Type**: `notes`
- **Taxonomy**: `notebook` (conversations are assigned to the `ai-chats` notebook by default).
- **Block Type**: `pos/ai-message`.
- **Meta**: 
    - `pos_last_response_id`: Stores the ID of the last response from the OpenAI Responses API to maintain conversation context.
    - `pos_chat_prompt_id`: Stores the ID of the system prompt used for the conversation.
    - Standard note meta from the Notes module (e.g. notebook assignment, URL, etc.).

## Storage Mechanism

The core logic resides in `OpenAI_Module::save_backscroll()`.

### 1. Message Format
Messages are converted into `pos/ai-message` blocks. Each block stores:
- `role`: 'user' or 'assistant'.
- `content`: The message content (text).
- `id`: A unique identifier for the message (used for React keys and mapping).

Tool calls and results are currently **not** saved to the conversation history in the database, only the visible conversation flow (User <-> Assistant).

### 2. Saving Process
`OpenAI_Module::complete_responses()` now owns the persistence workflow via its optional `$persistence` argument:

1. Pass `array( 'search_args' => array( 'ID' => $post_id ), 'append' => true )` to automatically persist a conversation.
2. User messages included in the `$messages` parameter are saved **before** the Responses API call, so intent is captured even if generation fails.
3. Assistant messages emitted by the Responses API are saved as soon as the API returns a `message` output item.
4. `pos_last_response_id` is updated when the API supplies a response ID, ensuring follow-up calls can continue with `previous_response_id`.
5. When persistence is enabled, the Responses API request sets `store => true`; stateless callers omit the persistence config and skip remote storage.

### 3. Title Generation
For new conversations (where the post is being created), `save_backscroll` triggers a side-effect to generate a title.
- It uses a lightweight model (`gpt-4o-mini`) to generate a 3-8 word title based on the first few messages.
- If generation fails, it falls back to a timestamp-based title.

## Context Management

The system uses the OpenAI Responses API `previous_response_id` feature.
- When a response is received, its `id` is stored in `pos_last_response_id` post meta.
- Subsequent requests for the same conversation include this ID.
- This allows the OpenAI API to maintain the conversation history server-side, reducing the need to send the full token-heavy backscroll with every request.
- The local database acts as a permanent record and UI source, while `previous_response_id` handles the AI context.

### 4. Transcriptions as Notes

The transcription subsystem (`POS_Transcription`) also feeds into the same notes-based storage model:

- Audio attachments scheduled for transcription are sent to the OpenAI transcription endpoint (`whisper-1`).
- The raw transcript is stored on the media attachment itself (`post_content` on the attachment).
- If the audio has **no parent post**, a new Note is created via `$this->notes->create()` with:
    - Title: typically `"Transcription"`.
    - Content: an audio block plus the transcript HTML.
    - Type: `notes` (the same CPT used for conversation history).
- This means transcriptions become regular notes and participate in all notebook / sync / search features alongside chat conversations.

## Frontend Integration (src-chat)

### 1. Session Bootstrapping
Currently, a conversation post is created **immediately on every page load** if no ID is provided in the URL.

- `chat-page.php` calls `save_backscroll` with an empty message list to initialize a new post.
- The ID of this new post is injected into the frontend configuration via `window.config.conversation_id`.
- **TODO**: Change this behavior to only create the post when the user sends the first message. This will prevent creating empty "ghost" conversations for every page visit.

### 2. Passing Conversation ID
The frontend application uses the Vercel AI SDK to manage the chat state.
- The `conversation_id` is read from `window.config` (or the URL) and passed to the `useChat` hook.
- During the API request to `/openai/vercel/chat`, the ID is included in the JSON body:
  ```json
  {
    "id": "123",
    "messages": [...],
    "selectedChatModel": "..."
  }
  ```
- The backend endpoint (`vercel_chat`) reads this ID to locate the existing `notes` post and passes it to `complete_responses(..., array( 'search_args' => array( 'ID' => $id ) ))`, which now handles both user and assistant persistence automatically.

## System Prompts

System prompts allow defining different personas or contexts for the chat.

- **Storage**: Stored as `notes` post type.
- **Notebook**: Assigned to the `prompts-chat` notebook.
- **Meta**:
    - `pos_model`: (Optional) Specifies which model to use (e.g., `gpt-4o`, `o1-preview`).
- **Selection**:
    - The frontend retrieves available prompts via `window.config.chat_prompts`.
    - When a user selects a prompt, its slug is sent as `selectedChatModel`.
    - The backend looks up the prompt note by slug and uses its content as the system instructions.
    - The prompt ID is saved to the conversation meta `pos_chat_prompt_id`.
