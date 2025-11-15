'use client';

// import { isToday, isYesterday, subMonths, subWeeks } from 'date-fns'; // Not used in active code
// import { useParams, useRouter } from 'next/navigation'; // useRouter not used in active code, useParams not used if history items not rendered
// import type { User } from 'next-auth'; // Removed
// import { useState } from 'react'; // Not used if delete dialog logic is removed
// import { toast } from 'sonner'; // Not used if delete logic is removed
// import { motion } from 'framer-motion'; // Not used if history items not rendered
// import {
//   AlertDialog,
//   AlertDialogAction,
//   AlertDialogCancel,
//   AlertDialogContent,
//   AlertDialogDescription,
//   AlertDialogFooter,
//   AlertDialogHeader,
//   AlertDialogTitle,
// } from '@/components/ui/alert-dialog'; // Not used if delete dialog logic is removed
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

// Types GroupedChats, ChatHistory and functions groupChatsByDate, getChatHistoryPaginationKey are unused due to SWR logic removal

export function SidebarHistory({ user }: { user: MockSessionUser | undefined }) {
  // const { setOpenMobile } = useSidebar(); // Not used in active logic
  // const { id } = useParams(); // Not used in active logic

  // All SWR and delete logic removed as API calls are non-functional
  // const {
  //   data: paginatedChatHistories,
  //   setSize,
  //   isValidating,
  //   isLoading,
  //   mutate,
  // } = useSWRInfinite<ChatHistory>(getChatHistoryPaginationKey, fetcher, {
  //   fallbackData: [],
  // });
  // const router = useRouter();
  // const [deleteId, setDeleteId] = useState<string | null>(null);
  // const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  // const hasReachedEnd = false; // Default for static view
  // const hasEmptyChatHistory = true; // Default for static view
  // const handleDelete = async () => {};

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

  if (false) {
    return (
      <SidebarGroup>
        <div className="px-2 py-1 text-xs text-sidebar-foreground/50">
          Today
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

  if (true) {
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

  // Original return with rendering logic is removed as it depends on non-functional API calls
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
