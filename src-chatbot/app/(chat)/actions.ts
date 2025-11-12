// 'use server'; // Disabled for static export

import { generateText, type UIMessage } from 'ai';
// import { cookies } from 'next/headers'; // Disabled for static export
/* // DB function imports disabled for static export
import {
  deleteMessagesByChatIdAfterTimestamp,
  getMessageById,
  updateChatVisiblityById,
} from '@/lib/db/queries';
*/
import type { VisibilityType } from '@/components/visibility-selector';
import { myProvider } from '@/lib/ai/providers';

export async function saveChatModelAsCookie(model: string) {
  // const cookieStore = await cookies(); // Disabled for static export
  // cookieStore.set('chat-model', model); // Disabled for static export
  console.warn('saveChatModelAsCookie is disabled for static export');
}

/**
 * Save the chat model to WordPress user meta via REST API.
 *
 * @param model The model ID to save.
 */
export async function saveChatModelToUserMeta(model: string): Promise<void> {
  // Get config from window (client-side only)
  if (typeof window === 'undefined') {
    console.warn('saveChatModelToUserMeta can only be called on the client side');
    return;
  }

  const config = (window as any).config;
  if (!config) {
    console.error('window.config is not available');
    return;
  }

  try {
    const response = await fetch(`${config.rest_api_url}wp/v2/users/me`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce,
      },
      body: JSON.stringify({
        meta: {
          pos_last_chat_model: model,
        },
      }),
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    console.log('Chat model saved to user meta:', data.meta?.pos_last_chat_model);
  } catch (error) {
    console.error('Error saving chat model to user meta:', error);
    throw error;
  }
}

export async function generateTitleFromUserMessage({
  message,
}: {
  message: UIMessage;
}) {
  const { text: title } = await generateText({
    model: myProvider.languageModel('title-model'),
    system: `\n
    - you will generate a short title based on the first message a user begins a conversation with
    - ensure it is not more than 80 characters long
    - the title should be a summary of the user's message
    - do not use quotes or colons`,
    prompt: JSON.stringify(message),
  });

  return title;
}

export async function deleteTrailingMessages({ id }: { id: string }) {
  // const [message] = await getMessageById({ id }); // DB call disabled
  // await deleteMessagesByChatIdAfterTimestamp({ // DB call disabled
  //   chatId: message.chatId,
  //   timestamp: message.createdAt,
  // });
  console.warn('deleteTrailingMessages in app/(chat)/actions.ts disabled for static export');
}

export async function updateChatVisibility({
  chatId,
  visibility,
}: {
  chatId: string;
  visibility: VisibilityType;
}) {
  // await updateChatVisiblityById({ chatId, visibility }); // DB call disabled
  console.warn('updateChatVisibility in app/(chat)/actions.ts disabled for static export');
}
