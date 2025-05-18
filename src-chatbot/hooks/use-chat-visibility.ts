'use client';

import { useMemo } from 'react';
import useSWR, { useSWRConfig } from 'swr';
// import { unstable_serialize } from 'swr/infinite'; // Removed as its usage is removed
import { updateChatVisibility } from '@/app/(chat)/actions';
// import {
//   getChatHistoryPaginationKey, // Removed
//   type ChatHistory, // Removed
// } from '@/components/sidebar-history';
import type { VisibilityType } from '@/components/visibility-selector';

export function useChatVisibility({
  chatId,
  initialVisibility,
}: {
  chatId: string;
  initialVisibility: VisibilityType;
}) {
  const { mutate, cache } = useSWRConfig();
  // const history: ChatHistory = cache.get('/api/history')?.data; // Removed as ChatHistory and /api/history cache are gone

  const { data: localVisibility, mutate: setLocalVisibility } = useSWR(
    `${chatId}-visibility`,
    null,
    {
      fallbackData: initialVisibility,
    },
  );

  const visibilityType = useMemo(() => {
    // if (!history) return localVisibility; // Simplified: always use localVisibility as history is removed
    // const chat = history.chats.find((chat) => chat.id === chatId);
    // if (!chat) return 'private'; // Default or rely on localVisibility
    // return chat.visibility;
    return localVisibility;
  // }, [history, chatId, localVisibility]);
  }, [localVisibility]); // Dependency array updated

  const setVisibilityType = (updatedVisibilityType: VisibilityType) => {
    setLocalVisibility(updatedVisibilityType);
    // mutate(unstable_serialize(getChatHistoryPaginationKey)); // Removed
    console.warn('Chat history SWR mutation removed from useChatVisibility hook.');

    updateChatVisibility({
      chatId: chatId,
      visibility: updatedVisibilityType,
    });
  };

  return { visibilityType, setVisibilityType };
}
