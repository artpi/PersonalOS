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
