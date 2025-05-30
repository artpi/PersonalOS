// import { cookies } from 'next/headers'; // Removed for static export
import { notFound, redirect } from 'next/navigation';

// import { auth } from '@/app/(auth)/auth'; // @/app/(auth)/auth.ts deleted
import { Chat } from '@/components/chat';
// import { getChatById, getMessagesByChatId } from '@/lib/db/queries'; // lib/db/queries.ts deleted
import { DataStreamHandler } from '@/components/data-stream-handler';
import { DEFAULT_CHAT_MODEL } from '@/lib/ai/models';
// import type { DBMessage } from '@/lib/db/schema'; // lib/db/schema.ts deleted
import type { Attachment, UIMessage } from 'ai';

// Define UserType and MockSession locally as auth and db are removed
export type UserType = 'guest' | 'regular';
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
interface MockChat {
  id: string;
  visibility: 'public' | 'private';
  userId: string;
}
interface MockDBMessage {
  id: string;
  parts: any; // UIMessage['parts']
  role: 'user' | 'assistant' | 'system' | 'function' | 'tool'; // UIMessage['role']
  createdAt: Date;
  attachments?: Array<Attachment>;
}


// export const dynamic = 'error'; // Keep this commented out for now

export function generateStaticParams() {
  return [];
}

// Reverting to Promise-based params for async page component
export default async function ChatPage(props: { params: Promise<{ id: string }> }) {
  const params = await props.params; // Await the params
  const { id } = params;
  // const chat = await getChatById({ id }); // DB calls will fail at runtime, ok per plan
  console.warn('getChatById call in app/(chat)/chat/[id]/page_disabled.tsx disabled.');
  const chat: MockChat | null = { id, visibility: 'private', userId: 'static-guest-id' }; // Mock chat data

  if (!chat) {
    notFound();
  }

  // const session = await auth(); // Auth calls will fail at runtime, ok per plan
  console.warn('auth call in app/(chat)/chat/[id]/page_disabled.tsx disabled.');
  const mockUser: MockSessionUser = { id: 'static-guest-id', type: 'guest', name: 'Static Guest', email: 'guest@static.local' };
  const session: MockSession | null = { user: mockUser, expires: new Date().toISOString() };


  if (!session) {
    // redirect('/api/auth/guest'); // API routes are non-functional
    console.warn('Redirect to /api/auth/guest disabled in app/(chat)/chat/[id]/page_disabled.tsx.');
    notFound(); // Or handle appropriately for a disabled page
  }

  if (chat.visibility === 'private') {
    if (!session.user) {
      return notFound();
    }

    if (session.user.id !== chat.userId) {
      return notFound();
    }
  }

  // const messagesFromDb = await getMessagesByChatId({ id }); // DB calls will fail, ok
  console.warn('getMessagesByChatId call in app/(chat)/chat/[id]/page_disabled.tsx disabled.');
  const messagesFromDb: Array<MockDBMessage> = []; // Mock messages

  function convertToUIMessages(messages: Array<MockDBMessage>): Array<UIMessage> {
    return messages.map((message) => ({
      id: message.id,
      parts: message.parts as UIMessage['parts'],
      role: message.role as UIMessage['role'],
      // Note: content will soon be deprecated in @ai-sdk/react
      content: '',
      createdAt: message.createdAt,
      experimental_attachments:
        (message.attachments as Array<Attachment>) ?? [],
    }));
  }

  // const cookieStore = await cookies(); // Disabled for static export
  // const chatModelFromCookie = cookieStore.get('chat-model'); // Disabled for static export
  // const chatModelFromCookie = null; // Default to null for static export -> This was already here
  console.warn('Cookie reading in app/(chat)/chat/[id]/page_disabled.tsx disabled. Using default chat model.');

  // Simplified logic: always use default or the one from (now null) cookie
  // Effectively, this will always use DEFAULT_CHAT_MODEL due to the null assignment
  const selectedChatModelToUse = DEFAULT_CHAT_MODEL; // Directly use default for static export

  return (
    <>
      <Chat
        id={chat.id}
        initialMessages={convertToUIMessages(messagesFromDb)}
        selectedChatModel={selectedChatModelToUse}
        selectedVisibilityType={chat.visibility}
        isReadonly={session?.user?.id !== chat.userId}
        session={session}
      />
      <DataStreamHandler id={id} />
    </>
  );
}
