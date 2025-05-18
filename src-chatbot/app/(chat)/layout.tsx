// import { cookies } from 'next/headers'; // Removed for static export

import { AppSidebar } from '@/components/app-sidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
// import { auth } from '../(auth)/auth'; // auth() call disabled for static export
// import { type UserType } from '../(auth)/auth'; // Import UserType -> ../(auth)/auth.ts deleted
// import type { Session } from 'next-auth'; // Removed as next-auth is uninstalled
import Script from 'next/script';

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

// interface MockSession { // Not strictly needed here as only session.user is used directly
//   user: MockSessionUser;
//   expires: string;
// }

export const experimental_ppr = true;

export default async function Layout({
  children,
}: {
  children: React.ReactNode;
}) {
  // const sessionFromAuth = await auth(); // cookies() call removed, auth() call disabled
  console.warn('auth() call in app/(chat)/layout.tsx disabled. Using mock session.');
  
  const mockUser: MockSessionUser = {
    id: 'static-guest-id',
    type: 'guest' as UserType,
    name: 'Static Guest',
    email: 'guest@static.local',
    image: null,
  };
  // const session: MockSession = { // Full session object not strictly needed if only user is passed down
  //   user: mockUser,
  //   expires: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
  // };

  const isCollapsed = true; // Default to collapsed for static export
  console.warn('Sidebar cookie state in app/(chat)/layout.tsx disabled for static export. Defaulting to collapsed.');

  return (
    <>
      <Script
        src="https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js"
        strategy="beforeInteractive"
      />
      <SidebarProvider defaultOpen={!isCollapsed}>
        <AppSidebar user={mockUser} /> {/* Pass mockUser directly */}
        <SidebarInset>{children}</SidebarInset>
      </SidebarProvider>
    </>
  );
}
