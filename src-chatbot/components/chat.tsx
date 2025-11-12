'use client';

import type { Attachment, UIMessage } from 'ai';
import { useChat } from '@ai-sdk/react';
import { useState, useEffect, useRef } from 'react';
import { ChatHeader } from '@/components/chat-header';
import { generateUUID } from '@/lib/utils';
import { Artifact } from './artifact';
import { MultimodalInput } from './multimodal-input';
import { Messages } from './messages';
import type { VisibilityType } from './visibility-selector';
import { useArtifactSelector } from '@/hooks/use-artifact';
import { toast } from './toast';
import { getConfig } from '@/lib/constants';

// Define local types as original schemas/auth types are removed
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

export function Chat({
  id: initialId,
  initialMessages,
  selectedChatModel,
  selectedVisibilityType,
  isReadonly,
  session, // Type will be MockSession now
}: {
  id: string;
  initialMessages: Array<UIMessage>;
  selectedChatModel: string;
  selectedVisibilityType: VisibilityType;
  isReadonly: boolean;
  session: MockSession; // Changed from Session to MockSession
}) {
  const currentConfig = getConfig();
  
  // Use conversation_id from PHP config (generated fresh on each page load and injected into window.config)
  // This is just a constant from the page - no need to store it anywhere
  const id = currentConfig.conversation_id || initialId;

  // Manage selected model state so it can be updated by ModelSelector
  // Initialize from config if available (client-side), otherwise use prop
  const [currentSelectedModel, setCurrentSelectedModel] = useState(() => {
    // On client side, check window.config for pos_last_chat_model
    if (typeof window !== 'undefined' && window.config?.pos_last_chat_model) {
      const configModel = window.config.pos_last_chat_model.trim();
      if (configModel !== '') {
        return configModel;
      }
    }
    return selectedChatModel;
  });

  // Use ref to track previous values to prevent loops
  const prevSelectedChatModelRef = useRef(selectedChatModel);
  const prevConfigModelRef = useRef<string | null>(null);

  // Sync state when prop changes or when config becomes available
  useEffect(() => {
    // Check if config has a saved model that's different from current
    if (typeof window !== 'undefined' && window.config?.pos_last_chat_model) {
      const configModel = window.config.pos_last_chat_model.trim();
      if (configModel !== '' && configModel !== prevConfigModelRef.current) {
        prevConfigModelRef.current = configModel;
        if (configModel !== currentSelectedModel) {
          setCurrentSelectedModel(configModel);
          return;
        }
      }
    }
    // Otherwise sync with prop only if it changed
    if (selectedChatModel !== prevSelectedChatModelRef.current) {
      prevSelectedChatModelRef.current = selectedChatModel;
      if (selectedChatModel !== currentSelectedModel) {
        setCurrentSelectedModel(selectedChatModel);
      }
    }
  }, [selectedChatModel, currentSelectedModel]);

  const {
    messages,
    setMessages,
    handleSubmit,
    input,
    setInput,
    append,
    status,
    stop,
    reload,
  } = useChat({
    id,
    initialMessages,
    experimental_throttle: 100,
    sendExtraMessageFields: true,
	headers: {
		'X-WP-Nonce': currentConfig.nonce,
	},
	// onToolCall: (toolCall) => {
	// 	console.log('toolCall', toolCall);
	// },
	api: currentConfig.rest_api_url + 'pos/v1/openai/vercel/chat',
    generateId: generateUUID,
    experimental_prepareRequestBody: (body) => {
		return ({
		id,
		message: body.messages.at(-1),
        selectedChatModel: currentSelectedModel,
      });
    },
    onError: (error) => {
      toast({
        type: 'error',
        description: error.message,
      });
    },
  });

  const [attachments, setAttachments] = useState<Array<Attachment>>([]);
  const isArtifactVisible = useArtifactSelector((state) => state.isVisible);

  return (
    <>
      <div className="flex flex-col min-w-0 h-dvh bg-background">
        <ChatHeader
          chatId={id}
          selectedModelId={currentSelectedModel}
          onModelChange={setCurrentSelectedModel}
          selectedVisibilityType={selectedVisibilityType}
          isReadonly={isReadonly}
          session={session}
        />

        <Messages
          chatId={id}
          status={status}
          messages={messages}
          setMessages={setMessages}
          reload={reload}
          isReadonly={isReadonly}
          isArtifactVisible={isArtifactVisible}
        />

        <form className="flex mx-auto px-4 bg-background pb-4 md:pb-6 gap-2 w-full md:max-w-3xl">
          {!isReadonly && (
            <MultimodalInput
              chatId={id}
              input={input}
              setInput={setInput}
              handleSubmit={handleSubmit}
              status={status}
              stop={stop}
              attachments={attachments}
              setAttachments={setAttachments}
              messages={messages}
              setMessages={setMessages}
              append={append}
            />
          )}
        </form>
      </div>

      <Artifact
        chatId={id}
        input={input}
        setInput={setInput}
        handleSubmit={handleSubmit}
        status={status}
        stop={stop}
        attachments={attachments}
        setAttachments={setAttachments}
        append={append}
        messages={messages}
        setMessages={setMessages}
        reload={reload}
        isReadonly={isReadonly}
      />
    </>
  );
}
