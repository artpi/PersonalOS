import { PreviewMessage } from './message';
import { useScrollToBottom } from './use-scroll-to-bottom';
import { UIMessage } from 'ai';
import { memo } from 'react';
import { UIArtifact } from './artifact';
import { UseChatHelpers } from '@ai-sdk/react';

// Vote interface removed as it's no longer used
// interface Vote {
//   id: string;
//   chatId: string;
//   messageId: string;
//   isUpvoted: boolean;
//   userId: string;
//   createdAt?: Date;
// }

interface ArtifactMessagesProps {
  chatId: string;
  status: UseChatHelpers['status'];
  // votes: Array<Vote> | undefined; // Removed as unused
  messages: Array<UIMessage>;
  setMessages: UseChatHelpers['setMessages'];
  reload: UseChatHelpers['reload'];
  isReadonly: boolean;
  artifactStatus: UIArtifact['status'];
}

function PureArtifactMessages({
  chatId,
  status,
  // votes, // Removed as unused
  messages,
  setMessages,
  reload,
  isReadonly,
}: ArtifactMessagesProps) {
  const [messagesContainerRef, messagesEndRef] =
    useScrollToBottom<HTMLDivElement>();

  return (
    <div
      ref={messagesContainerRef}
      className="flex flex-col gap-4 h-full items-center overflow-y-scroll px-4 pt-20"
    >
      {messages.map((message, index) => (
        <PreviewMessage
          chatId={chatId}
          key={message.id}
          message={message}
          isLoading={status === 'streaming' && index === messages.length - 1}
          setMessages={setMessages}
          reload={reload}
          isReadonly={isReadonly}
        />
      ))}

      <div
        ref={messagesEndRef}
        className="shrink-0 min-w-[24px] min-h-[24px]"
      />
    </div>
  );
}

function areEqual(
  prevProps: ArtifactMessagesProps,
  nextProps: ArtifactMessagesProps,
) {
  if (
    prevProps.artifactStatus === 'streaming' &&
    nextProps.artifactStatus === 'streaming'
  )
    return true;

  if (prevProps.status !== nextProps.status) return false;
  if (prevProps.messages.length !== nextProps.messages.length) return false;
  // if (!equal(prevProps.votes, nextProps.votes)) return false; // Already removed

  return true;
}

export const ArtifactMessages = memo(PureArtifactMessages, areEqual);
