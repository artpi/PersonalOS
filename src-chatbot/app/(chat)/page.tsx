// import { cookies } from 'next/headers'; // Removed for static export: caused "used headers" error

import { Chat } from '@/components/chat';
import { DEFAULT_CHAT_MODEL } from '@/lib/ai/models';
import { generateUUID } from '@/lib/utils';
import { DataStreamHandler } from '@/components/data-stream-handler';
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

  const id = generateUUID();

  // For static export, cookie reading is disabled. Always use default model.
  // const cookieStore = await cookies(); // Call to cookies() disabled
  // const modelIdFromCookie = cookieStore.get('chat-model');
  console.warn('Cookie reading in app/(chat)/page.tsx disabled for static export. Using default chat model.');

  return (
    <>
      <Chat
        key={id}
        id={id}
        initialMessages={[]}
        selectedChatModel={DEFAULT_CHAT_MODEL}
        selectedVisibilityType="private"
        isReadonly={false} // For static export, assume not readonly as it's a new chat for a mock guest
        session={session} // Pass the mock session
      />
      <DataStreamHandler id={id} />
    </>
  );
}
