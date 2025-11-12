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
  console.log('[ModelSelector] RENDER START - selectedModelId:', selectedModelId);
  
  const [open, setOpen] = useState(false);
  
  // Get config only when needed, memoized to prevent re-renders
  const config = useMemo(() => {
    const cfg = getConfig();
    console.log('[ModelSelector] getConfig() called, pos_last_chat_model:', cfg.pos_last_chat_model);
    return cfg;
  }, []);
  
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
    console.log('[ModelSelector] Computing valid model ID...');
    const configModelId = typeof window !== 'undefined' && window.config?.pos_last_chat_model
      ? window.config.pos_last_chat_model.trim()
      : '';
    
    console.log('[ModelSelector] configModelId from window:', configModelId);
    
    // Determine which model ID to use
    let targetModelId: string;
    if (configModelId) {
      targetModelId = configModelId;
    } else {
      targetModelId = selectedModelId;
    }
    
    console.log('[ModelSelector] targetModelId:', targetModelId);
    
    // Validate that the model exists in available models
    const availableModelIds = availableChatModels.map(m => m.id);
    console.log('[ModelSelector] availableModelIds:', availableModelIds);
    const isValid = availableModelIds.includes(targetModelId);
    console.log('[ModelSelector] isValid:', isValid);
    
    let result: string;
    if (isValid) {
      result = targetModelId;
    } else {
      // Fallback to first available model or prop
      result = availableModelIds.length > 0 ? availableModelIds[0] : selectedModelId;
      console.log('[ModelSelector] Model invalid, falling back to:', result);
    }
    
    console.log('[ModelSelector] computeValidModelId result:', result);
    return result;
  }, [selectedModelId, availableChatModels]);
  
  // Use state only for user selections, initialize from computed value
  const [optimisticModelId, setOptimisticModelId] = useState(() => {
    console.log('[ModelSelector] useState initializer, computeValidModelId:', computeValidModelId);
    return computeValidModelId;
  });
  
  console.log('[ModelSelector] Current optimisticModelId:', optimisticModelId);
  
  // Sync state only when computed value changes (but not on every render)
  const prevComputedRef = useRef(computeValidModelId);
  useEffect(() => {
    console.log('[ModelSelector] useEffect triggered - computeValidModelId:', computeValidModelId, 'prev:', prevComputedRef.current, 'optimisticModelId:', optimisticModelId);
    if (computeValidModelId !== prevComputedRef.current) {
      console.log('[ModelSelector] computeValidModelId changed, updating ref');
      prevComputedRef.current = computeValidModelId;
      if (computeValidModelId !== optimisticModelId) {
        console.log('[ModelSelector] Syncing model - calling setOptimisticModelId:', {
          from: optimisticModelId,
          to: computeValidModelId
        });
        setOptimisticModelId(computeValidModelId);
      } else {
        console.log('[ModelSelector] computeValidModelId changed but optimisticModelId already matches, skipping update');
      }
    } else {
      console.log('[ModelSelector] computeValidModelId unchanged, skipping');
    }
  }, [computeValidModelId, optimisticModelId]); // Include optimisticModelId to log it

  // Debug: log available models (only once when they change)
  useEffect(() => {
    if (typeof window !== 'undefined' && chatPromptsFromConfig.length > 0) {
      console.log('Available chat prompts from config:', chatPromptsFromConfig);
      console.log('Available chat models:', availableChatModels);
    }
  }, [chatPromptsFromConfig, availableChatModels]);

  const selectedChatModel = useMemo(() => {
    console.log('[ModelSelector] Finding selectedChatModel for optimisticModelId:', optimisticModelId);
    const found = availableChatModels.find(
      (chatModel) => chatModel.id === optimisticModelId,
    );
    console.log('[ModelSelector] Found model:', found?.name || 'NOT FOUND', 'id:', found?.id);
    return found;
  }, [optimisticModelId, availableChatModels]);
  
  // Debug: log when selected model changes
  useEffect(() => {
    console.log('[ModelSelector] Selected model changed:', {
      optimisticModelId,
      availableModelIds: availableChatModels.map(m => m.id),
      found: selectedChatModel?.name || 'NOT FOUND',
      selectedChatModelId: selectedChatModel?.id
    });
  }, [optimisticModelId, availableChatModels, selectedChatModel]);
  
  console.log('[ModelSelector] RENDER END - optimisticModelId:', optimisticModelId, 'selectedChatModel:', selectedChatModel?.name);

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
              data-active={id === optimisticModelId}
            >
              <div className="gap-4 group/item flex flex-row justify-between items-center w-full">
                <div className="flex flex-col gap-1 items-start">
                  <div>{chatModel.name}</div>
                  <div className="text-xs text-muted-foreground">
                    {chatModel.description}
                  </div>
                </div>

                <div className="text-foreground dark:text-foreground opacity-0 group-data-[active=true]/item:opacity-100">
                  <CheckCircleFillIcon />
                </div>
              </div>
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
