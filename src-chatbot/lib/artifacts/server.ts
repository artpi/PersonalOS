import { codeDocumentHandler } from '@/artifacts/code/server';
import { imageDocumentHandler } from '@/artifacts/image/server';
import { sheetDocumentHandler } from '@/artifacts/sheet/server';
import { textDocumentHandler } from '@/artifacts/text/server';
import { ArtifactKind } from '@/components/artifact';
import { DataStreamWriter } from 'ai';
// import { Document } from '../db/schema'; // Ensure this line is commented or removed
// import { saveDocument } from '../db/queries'; // Ensure this line is commented or removed
// import { Session } from 'next-auth'; // Ensure this line is commented or removed

// Define MockSession and related types locally
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

// Define local Document type
interface Document {
  id: string;
  userId?: string;
  title: string;
  content?: string; // Making content optional as it might not always be present or used in this context
  kind: ArtifactKind;
  createdAt?: Date;
  updatedAt?: Date;
}

export interface SaveDocumentProps { // This interface itself might be unused now
  id: string;
  title: string;
  kind: ArtifactKind;
  content: string;
  userId: string;
}

export interface CreateDocumentCallbackProps {
  id: string;
  title: string;
  dataStream: DataStreamWriter;
  session: MockSession; // Changed to MockSession
}

export interface UpdateDocumentCallbackProps {
  document: Document; // Uses local Document
  description: string;
  dataStream: DataStreamWriter;
  session: MockSession; // Changed to MockSession
}

export interface DocumentHandler<T = ArtifactKind> {
  kind: T;
  onCreateDocument: (args: CreateDocumentCallbackProps) => Promise<void>;
  onUpdateDocument: (args: UpdateDocumentCallbackProps) => Promise<void>;
}

export function createDocumentHandler<T extends ArtifactKind>(config: {
  kind: T;
  onCreateDocument: (params: CreateDocumentCallbackProps) => Promise<string>; // string is draftContent
  onUpdateDocument: (params: UpdateDocumentCallbackProps) => Promise<string>; // string is draftContent
}): DocumentHandler<T> {
  return {
    kind: config.kind,
    onCreateDocument: async (args: CreateDocumentCallbackProps) => {
      await config.onCreateDocument({
        id: args.id,
        title: args.title,
        dataStream: args.dataStream,
        session: args.session,
      });
      console.warn('saveDocument call removed from createDocumentHandler.onCreateDocument');
      return;
    },
    onUpdateDocument: async (args: UpdateDocumentCallbackProps) => {
      await config.onUpdateDocument({
        document: args.document,
        description: args.description,
        dataStream: args.dataStream,
        session: args.session,
      });
      console.warn('saveDocument call removed from createDocumentHandler.onUpdateDocument');
      return;
    },
  };
}

/*
 * Use this array to define the document handlers for each artifact kind.
 */
export const documentHandlersByArtifactKind: Array<DocumentHandler> = [
  textDocumentHandler,
  codeDocumentHandler,
  imageDocumentHandler,
  sheetDocumentHandler,
];

export const artifactKinds = ['text', 'code', 'image', 'sheet'] as const;
