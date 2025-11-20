'use client';

import { useEffect, useState, useCallback, useRef } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  useSidebar,
} from '@/components/ui/sidebar';
import { ChatItem } from './sidebar-history-item';
import { LoaderIcon } from './icons';
import type { MockSessionUser } from './sidebar-user-nav';
import { getConfig } from '@/lib/constants';

interface Chat {
  id: string;
  title: string;
  visibility: 'public' | 'private';
  createdAt?: string;
}

export function SidebarHistory({ user }: { user: MockSessionUser | undefined }) {
  const { setOpenMobile, open, openMobile } = useSidebar();
  const searchParams = useSearchParams();
  const currentChatId = searchParams?.get('id');
  const [conversations, setConversations] = useState<Chat[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const prevOpenRef = useRef(false);
  const prevOpenMobileRef = useRef(false);

  const fetchConversations = useCallback(async () => {
    if (!user) {
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      setError(null);
      const config = getConfig();
      
      // Use the hardcoded 'ai-chats' notebook term ID from config
      const aiChatsNotebookId = config.ai_chats_notebook_id;

      if (!aiChatsNotebookId) {
        // If notebook doesn't exist, return empty array
        setConversations([]);
        return;
      }

      // Fetch notes filtered by the notebook term ID
      const notesResponse = await fetch(
        `${config.rest_api_url}pos/v1/notes?notebook=${aiChatsNotebookId}&per_page=50&orderby=date&order=desc&status=private,publish`,
        {
          headers: {
            'X-WP-Nonce': config.nonce,
          },
        }
      );

      if (!notesResponse.ok) {
        throw new Error(`Failed to fetch notes: ${notesResponse.status}`);
      }

      const notes = await notesResponse.json();
      
      // Transform notes to match Chat interface
      const formattedConversations: Chat[] = notes.map((note: {
        id: number;
        title: { rendered: string };
        date: string;
      }) => ({
        id: String(note.id),
        title: note.title?.rendered || 'Untitled Chat',
        visibility: 'private' as const,
        createdAt: note.date,
      }));

      setConversations(formattedConversations);
    } catch (err) {
      console.error('Error fetching conversations:', err);
      setError(err instanceof Error ? err.message : 'Failed to load conversations');
    } finally {
      setIsLoading(false);
    }
  }, [user]);

  // Fetch conversations on mount and when user changes
  useEffect(() => {
    fetchConversations();
  }, [fetchConversations]);

  // Refresh conversations when sidebar opens (desktop or mobile)
  // Only refresh when transitioning from closed to open
  useEffect(() => {
    const wasClosed = !prevOpenRef.current && !prevOpenMobileRef.current;
    const isNowOpen = open || openMobile;
    
    if (wasClosed && isNowOpen) {
      fetchConversations();
    }
    
    prevOpenRef.current = open;
    prevOpenMobileRef.current = openMobile;
  }, [open, openMobile, fetchConversations]);

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

  if (isLoading) {
    return (
      <SidebarGroup>
        <SidebarGroupContent>
          <div className="flex flex-col gap-2 px-2">
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
          <div className="px-2 text-zinc-500 w-full flex flex-row justify-center items-center text-sm gap-2">
            Error loading conversations
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  if (conversations.length === 0) {
    return (
      <SidebarGroup>
        <SidebarGroupContent>
          <div className="px-2 text-zinc-500 w-full flex flex-row justify-center items-center text-sm gap-2">
            Your conversations will appear here once you start chatting!
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    );
  }

  return (
    <SidebarGroup>
      <SidebarGroupContent>
        <SidebarMenu>
          {conversations.map((chat) => (
            <ChatItem
              key={chat.id}
              chat={chat}
              isActive={chat.id === currentChatId}
              setOpenMobile={setOpenMobile}
            />
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
  /*
  return (
    <>
      <SidebarGroup>
        <SidebarGroupContent>
          <SidebarMenu>
            {paginatedChatHistories &&
              (() => {
                const chatsFromHistory = paginatedChatHistories.flatMap(
                  (paginatedChatHistory) => paginatedChatHistory.chats,
                );

                const groupedChats = groupChatsByDate(chatsFromHistory);

                return (
                  <div className="flex flex-col gap-6">
                    {groupedChats.today.length > 0 && (
                      <div>
                        <div className="px-2 py-1 text-xs text-sidebar-foreground/50">
                          Today
                        </div>
                        {groupedChats.today.map((chat) => (
                          <ChatItem
                            key={chat.id}
                            chat={chat}
                            isActive={chat.id === id}
                            onDelete={(chatId) => {
                              setDeleteId(chatId);
                              setShowDeleteDialog(true);
                            }}
                            setOpenMobile={setOpenMobile}
                          />
                        ))}
                      </div>
                    )}
                    // ... other groups ... 
                  </div>
                );
              })()}
          </SidebarMenu>

          <motion.div
            onViewportEnter={() => {
              if (!isValidating && !hasReachedEnd) {
                setSize((size) => size + 1);
              }
            }}
          />

          {hasReachedEnd ? (
            <div className="px-2 text-zinc-500 w-full flex flex-row justify-center items-center text-sm gap-2 mt-8">
              You have reached the end of your chat history.
            </div>
          ) : (
            <div className="p-2 text-zinc-500 dark:text-zinc-400 flex flex-row gap-2 items-center mt-8">
              <div className="animate-spin">
                <LoaderIcon />
              </div>
              <div>Loading Chats...</div>
            </div>
          )}
        </SidebarGroupContent>
      </SidebarGroup>

      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete your
              chat and remove it from our servers.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete}>
              Continue
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
  */
}
