// import { cookies } from 'next/headers'; // Removed for static export: caused "used headers" error

import { Chat } from '@/components/chat';
import { DEFAULT_CHAT_MODEL } from '@/lib/ai/models';
import { generateUUID } from '@/lib/utils';
import { DataStreamHandler } from '@/components/data-stream-handler';
import { getConfig } from '@/lib/constants';
import type { UIMessage } from 'ai';
// import { auth } from '../(auth)/auth'; // auth() call disabled for static export
// import { redirect } from 'next/navigation'; // Redirect disabled for static export
// import type { Session } from 'next-auth'; // Removed as next-auth is uninstalled
// import { type UserType } from '../(auth)/auth'; // Import UserType from its definition -> ../(auth)/auth.ts deleted

// Define UserType locally as ../(auth)/auth.ts was deleted
export type UserType = 'guest' | 'regular';

// Define a local mock session type
interface MockSessionUser {
  id: string;
  type: UserType;
  name?: string | null;
  email?: string | null;
  image?: string | null;
}

interface MockSession {
  user: MockSessionUser;
  expires: string;
}

export default async function Page() {
  // const sessionFromAuth = await auth(); // auth() call disabled for static export
  console.warn('auth() call in app/(chat)/page.tsx disabled. Using mock session.');

  const mockUser: MockSessionUser = {
    id: 'static-guest-id',
    type: 'guest' as UserType, // Use imported UserType
    name: 'Static Guest',
    email: 'guest@static.local',
    image: null,
  };

  const session: MockSession = {
    user: mockUser,
    expires: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(), // Mock expires (e.g., 30 days from now)
  };

  // if (!sessionFromAuth) { // Redirect logic disabled for static export
  //   redirect('/api/auth/guest');
  // }

  // Use conversation_id from PHP config if available (generated fresh on each page load),
  // otherwise generate one (fallback for static export or if config is missing)
  const config = getConfig();
  // Ensure id is string
  const id = config.conversation_id ? String(config.conversation_id) : generateUUID();
  
  // Convert messages from PHP format (with 'content') to UIMessage format (with 'parts')
  const convertMessagesToUIMessages = (messages: Array<any>): Array<UIMessage> => {
    return messages.map((message) => {
      const content = message.content || '';
      const parts = message.content
        ? [{ type: 'text' as const, text: message.content }]
        : message.parts || [];
      return {
        id: message.id,
        role: message.role as UIMessage['role'],
        parts,
        content, // Still required by UIMessage type even though deprecated
        createdAt: message.createdAt ? new Date(message.createdAt) : new Date(),
        experimental_attachments: message.experimental_attachments || [],
      };
    });
  };
  
  const initialMessages = config.conversation_messages
    ? convertMessagesToUIMessages(config.conversation_messages)
    : [];

  // Use pos_last_chat_model from config if available, otherwise fall back to first prompt or default
  const defaultModel = config.pos_last_chat_model && config.pos_last_chat_model.trim() !== ''
    ? config.pos_last_chat_model
    : config.chat_prompts && config.chat_prompts.length > 0
    ? config.chat_prompts[0].id
    : DEFAULT_CHAT_MODEL;

  return (
    <>
      <Chat
        key={id}
        id={id}
        initialMessages={initialMessages}
        selectedChatModel={defaultModel}
        selectedVisibilityType="private"
        isReadonly={false} // For static export, assume not readonly as it's a new chat for a mock guest
        session={session} // Pass the mock session
      />
      <DataStreamHandler id={id} />
    </>
  );
}
