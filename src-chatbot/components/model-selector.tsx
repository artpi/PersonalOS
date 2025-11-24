'use client';

import { startTransition, useEffect, useMemo, useRef, useState } from 'react';

import { saveChatModelAsCookie, saveChatModelToUserMeta } from '@/app/(chat)/actions';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { chatModels } from '@/lib/ai/models';
import { cn } from '@/lib/utils';
import { getConfig } from '@/lib/constants';

import { CheckCircleFillIcon, ChevronDownIcon } from './icons';
import { entitlementsByUserType } from '@/lib/ai/entitlements';

// Define MockSession and related types locally
export type UserType = 'guest' | 'regular'; // Ensure this is consistent with other definitions
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

export function ModelSelector({
  session, // Type will be MockSession now
  selectedModelId,
  onModelChange,
  className,
}: {
  session: MockSession; // Changed from Session to MockSession
  selectedModelId: string;
  onModelChange?: (modelId: string) => void;
} & React.ComponentProps<typeof Button>) {
  const [open, setOpen] = useState(false);
  
  // Get config only when needed, memoized to prevent re-renders
  const config = useMemo(() => getConfig(), []);
  
  const chatPromptsFromConfig = useMemo(() => config.chat_prompts || [], [config.chat_prompts]);
  
  // Use prompts from config if available, otherwise fall back to hardcoded models
  const availableChatModels = useMemo(() => {
    if (chatPromptsFromConfig.length > 0) {
      // Convert ChatPrompt to ChatModel format
      return chatPromptsFromConfig.map((prompt) => ({
        id: prompt.id,
        name: prompt.name,
        description: prompt.description,
      }));
    }
    // Fallback to hardcoded models
    const userType = session.user.type;
    const { availableChatModelIds } = entitlementsByUserType[userType];
    return chatModels.filter((chatModel) =>
      availableChatModelIds.includes(chatModel.id),
    );
  }, [chatPromptsFromConfig, session.user.type]);

  // Compute the valid model ID directly (no state updates during render)
  const computeValidModelId = useMemo(() => {
    const configModelId = typeof window !== 'undefined' && window.config?.pos_last_chat_model
      ? window.config.pos_last_chat_model.trim()
      : '';
    
    // Determine which model ID to use
    let targetModelId: string;
    if (configModelId) {
      targetModelId = configModelId;
    } else {
      targetModelId = selectedModelId;
    }
    
    // Validate that the model exists in available models
    const availableModelIds = availableChatModels.map(m => m.id);
    const isValid = availableModelIds.includes(targetModelId);
    
    if (isValid) {
      return targetModelId;
    } else {
      // Fallback to first available model or prop
      return availableModelIds.length > 0 ? availableModelIds[0] : selectedModelId;
    }
  }, [selectedModelId, availableChatModels]);
  
  // Use state only for user selections, initialize from computed value
  const [optimisticModelId, setOptimisticModelId] = useState(computeValidModelId);
  
  // Sync state only when computed value changes (but not on every render)
  const prevComputedRef = useRef(computeValidModelId);
  useEffect(() => {
    if (computeValidModelId !== prevComputedRef.current) {
      prevComputedRef.current = computeValidModelId;
      if (computeValidModelId !== optimisticModelId) {
        setOptimisticModelId(computeValidModelId);
      }
    }
  }, [computeValidModelId, optimisticModelId]);

  const selectedChatModel = useMemo(
    () =>
      availableChatModels.find(
        (chatModel) => chatModel.id === optimisticModelId,
      ),
    [optimisticModelId, availableChatModels],
  );

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger
        asChild
        className={cn(
          'w-fit data-[state=open]:bg-accent data-[state=open]:text-accent-foreground',
          className,
        )}
      >
        <Button
          data-testid="model-selector"
          variant="outline"
          className="md:px-2 md:h-[34px]"
        >
          {selectedChatModel?.name}
          <ChevronDownIcon />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="min-w-[300px]">
        {availableChatModels.map((chatModel) => {
          const { id } = chatModel;
          const isActive = id === optimisticModelId;

          return (
            <DropdownMenuItem
              data-testid={`model-selector-item-${id}`}
              key={id}
              onSelect={(e) => {
                e.preventDefault();
                setOpen(false);

                startTransition(() => {
                  setOptimisticModelId(id);
                  saveChatModelAsCookie(id);
                  // Save to WordPress user meta via REST API
                  saveChatModelToUserMeta(id).catch((error) => {
                    console.error('Failed to save chat model to user meta:', error);
                  });
                  // Notify parent component of model change
                  if (onModelChange) {
                    onModelChange(id);
                  }
                });
              }}
              data-active={isActive ? 'true' : 'false'}
              className="gap-4 group/item flex flex-row justify-between items-center"
            >
              <div className="flex flex-col gap-1 items-start">
                <div>{chatModel.name}</div>
                <div className="text-xs text-muted-foreground">
                  {chatModel.description}
                </div>
              </div>

              <div className="text-foreground dark:text-foreground opacity-0 group-data-[active=true]/item:opacity-100">
                <CheckCircleFillIcon />
              </div>
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
