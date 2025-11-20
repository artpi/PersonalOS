'use client';

import Link from 'next/link';
import { useWindowSize } from 'usehooks-ts';

import { ModelSelector } from '@/components/model-selector';
import { SidebarToggle } from '@/components/sidebar-toggle';
import { Button } from '@/components/ui/button';
import { PlusIcon } from './icons';
import { useSidebar } from './ui/sidebar';
import { memo } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from './ui/tooltip';
import { type VisibilityType, VisibilitySelector } from './visibility-selector';

// Define MockSession and MockSessionUser locally
export type UserType = 'guest' | 'regular'; // Assuming this definition is consistent
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

function PureChatHeader({
  chatId,
  selectedModelId,
  onModelChange,
  selectedVisibilityType,
  isReadonly,
  session, // Type will be MockSession now
}: {
  chatId: string;
  selectedModelId: string;
  onModelChange?: (modelId: string) => void;
  selectedVisibilityType: VisibilityType;
  isReadonly: boolean;
  session: MockSession; // Changed from Session to MockSession
}) {
  const { open } = useSidebar();

  const { width: windowWidth } = useWindowSize();
  
  const handleNewChat = () => {
    // Navigate to admin.php?page=personalos-chatbot without id parameter
    if (typeof window !== 'undefined') {
      window.location.href = 'admin.php?page=personalos-chatbot';
    }
  };

  return (
    <header className="flex sticky top-0 bg-background py-1.5 items-center px-2 md:px-2 gap-2">
      <SidebarToggle />

      {(!open || windowWidth < 768) && (
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="outline"
              className="order-2 md:order-1 md:px-2 px-2 md:h-fit ml-auto md:ml-0"
              onClick={handleNewChat}
            >
              <PlusIcon />
              <span className="md:sr-only">New Chat</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>New Chat</TooltipContent>
        </Tooltip>
      )}

      {!isReadonly && (
        <ModelSelector
          session={session}
          selectedModelId={selectedModelId}
          onModelChange={onModelChange}
          className="order-1 md:order-2"
        />
      )}

      {/* {!isReadonly && (
        <VisibilitySelector
          chatId={chatId}
          selectedVisibilityType={selectedVisibilityType}
          className="order-1 md:order-3"
        />
      )} */}
    </header>
  );
}

export const ChatHeader = memo(PureChatHeader, (prevProps, nextProps) => {
  return prevProps.selectedModelId === nextProps.selectedModelId;
});
