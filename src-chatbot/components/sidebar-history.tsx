'use client';

import { useState, useEffect } from 'react';
import {
  SidebarGroup,
  SidebarGroupContent,
  // SidebarMenu, // Not used in active code
  // useSidebar, // Not used in active code
} from '@/components/ui/sidebar';
// import type { Chat } from '@/lib/db/schema'; // Removed
// import { fetcher } from '@/lib/utils'; // Not used if SWR is removed
// import { ChatItem } from './sidebar-history-item'; // Not used if history items not rendered
// import useSWRInfinite from 'swr/infinite'; // Removed
// import { LoaderIcon } from './icons'; // Not used if loading state is removed
import type { MockSessionUser } from './sidebar-user-nav'; // Import MockSessionUser
import { getConfig } from '@/lib/constants';

// Types GroupedChats, ChatHistory and functions groupChatsByDate, getChatHistoryPaginationKey are unused due to SWR logic removal

// Note type definition
interface Note {
  id: number;
  title: {
    rendered: string;
  };
  date: string;
  modified: string;
  slug: string;
  status: string;
}


export function SidebarHistory({ user }: { user: MockSessionUser | undefined }) {
  // const { setOpenMobile } = useSidebar(); // Not used in active logic
  // const { id } = useParams(); // Not used in active logic

  const [notes, setNotes] = useState<Note[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      setLoading(false);
      return;
    }

    const fetchNotes = async () => {
      try {
        const currentConfig = getConfig();
        if (!currentConfig) {
          throw new Error('Configuration not available');
        }

        const response = await fetch(currentConfig.rest_api_url + 'pos/v1/notes?status[]=private&status[]=publish&notebook=132', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': currentConfig.nonce,
          },
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        
        // Defensive programming: ensure data is valid
        if (!data) {
          setNotes([]);
          return;
        }
        
        // Handle both array and object responses
        let notesArray: any[] = [];
        if (Array.isArray(data)) {
          notesArray = data;
        } else if (data.data && Array.isArray(data.data)) {
          notesArray = data.data;
        } else if (typeof data === 'object' && data !== null) {
          // If it's a single note object, wrap it in an array
          notesArray = [data];
        }
        
        // Validate each note has required fields and matches WordPress post structure
        const validNotes = notesArray.filter((note: any) => 
          note && 
          typeof note === 'object' && 
          note.id && 
          (typeof note.id === 'number' || typeof note.id === 'string') &&
          note.title &&
          typeof note.title === 'object' &&
          note.title.rendered
        ).map((note: any) => ({
          id: Number(note.id),
          title: {
            rendered: String(note.title.rendered || 'Untitled Note')
          },
          content: note.content ? {
            rendered: String(note.content.rendered || ''),
            protected: Boolean(note.content.protected)
          } : undefined,
          date: String(note.date || new Date().toISOString()),
          modified: String(note.modified || note.date || new Date().toISOString()),
          slug: String(note.slug || ''),
          status: String(note.status || 'publish'),
        }));
        
        setNotes(validNotes);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to fetch notes');
        console.error('Error fetching notes:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchNotes();
  }, [user]);

  if (!user) {
    return (
      <SidebarGroup>
        <SidebarGroupContent>
          <div className="px-2 text-zinc-500 w-full flex flex-row justify-center items-center text-sm gap-2">
            Login to save and revisit previous chats!
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  if (loading) {
    return (
      <SidebarGroup>
        <div className="px-2 py-1 text-xs text-sidebar-foreground/50">
          Notes
        </div>
        <SidebarGroupContent>
          <div className="flex flex-col">
            {[44, 32, 28, 64, 52].map((item) => (
              <div
                key={item}
                className="rounded-md h-8 flex gap-2 px-2 items-center"
              >
                <div
                  className="h-4 rounded-md flex-1 max-w-[--skeleton-width] bg-sidebar-accent-foreground/10"
                  style={
                    {
                      '--skeleton-width': `${item}%`,
                    } as React.CSSProperties
                  }
                />
              </div>
            ))}
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  if (error) {
    return (
      <SidebarGroup>
        <SidebarGroupContent>
          <div className="px-2 text-red-500 w-full flex flex-row justify-center items-center text-sm gap-2">
            Error loading notes: {error}
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  if (notes.length === 0) {
    return (
      <SidebarGroup>
        <SidebarGroupContent>
          <div className="px-2 text-zinc-500 w-full flex flex-row justify-center items-center text-sm gap-2">
            Your notes will appear here once you create some!
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  return (
    <SidebarGroup>
      <div className="px-2 py-1 text-xs text-sidebar-foreground/50">
        Notes ({notes.length})
      </div>
      <SidebarGroupContent>
        <div className="flex flex-col gap-1">
          {notes.map((note) => {
            // Additional safety check
            if (!note || typeof note !== 'object' || !note.id) {
              return null;
            }
            
            return (
              <div
                key={note.id}
                className="rounded-md px-2 py-2 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground cursor-pointer transition-colors"
              >
                <div className="font-medium text-sm truncate">
                  {note.title.rendered || 'Untitled Note'}
                </div>
                <div className="text-xs text-sidebar-foreground/60">
                  {(() => {
                    try {
                      return new Date(note.modified).toLocaleDateString();
                    } catch {
                      return 'Invalid date';
                    }
                  })()}
                </div>
              </div>
            );
          })}
        </div>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}
