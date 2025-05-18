import { z } from 'zod';
// import { Session } from 'next-auth'; // Removed
import { DataStreamWriter, streamObject, tool } from 'ai';
// import { getDocumentById, saveSuggestions } from '@/lib/db/queries'; // Removed as db queries are gone
// import { Suggestion } from '@/lib/db/schema'; // Removed as schema is gone
import { generateUUID } from '@/lib/utils';
import { myProvider } from '../providers';

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

// Define local Suggestion type based on usage in this file
interface Suggestion {
  id: string;
  documentId: string;
  originalText: string;
  suggestedText: string;
  description: string;
  isResolved: boolean;
  userId?: string; // Added for saveSuggestions, though that part will be removed
  createdAt?: Date; // Added for saveSuggestions
  documentCreatedAt?: Date; // Added for saveSuggestions
}


interface RequestSuggestionsProps {
  session: MockSession; // Changed to MockSession
  dataStream: DataStreamWriter;
}

export const requestSuggestions = ({
  session,
  dataStream,
}: RequestSuggestionsProps) =>
  tool({
    description: 'Request suggestions for a document',
    parameters: z.object({
      documentId: z
        .string()
        .describe('The ID of the document to request edits'),
    }),
    execute: async ({ documentId }) => {
      // const document = await getDocumentById({ id: documentId }); // DB call removed
      console.warn('getDocumentById call removed from requestSuggestions tool.');

      // if (!document || !document.content) { // Logic dependent on document removed
      //   return {
      //     error: 'Document not found (or content missing) - DB call removed',
      //   };
      // }

      const suggestionsToStream: Array<
        Omit<Suggestion, 'userId' | 'createdAt' | 'documentCreatedAt'> // Keep Omit for structure if needed
      > = [];

      // Mock or skip streaming if document.content is unavailable
      const mockDocumentContent = 'This is placeholder content as the document could not be fetched from the database.';
      console.warn('Using mock document content for requestSuggestions tool.');

      const { elementStream } = streamObject({
        model: myProvider.languageModel('artifact-model'),
        system:
          'You are a help writing assistant. Given a piece of writing, please offer suggestions to improve the piece of writing and describe the change. It is very important for the edits to contain full sentences instead of just words. Max 5 suggestions.',
        prompt: mockDocumentContent, // Using mock content
        output: 'array',
        schema: z.object({
          originalSentence: z.string().describe('The original sentence'),
          suggestedSentence: z.string().describe('The suggested sentence'),
          description: z.string().describe('The description of the suggestion'),
        }),
      });

      for await (const element of elementStream) {
        const suggestion = {
          originalText: element.originalSentence,
          suggestedText: element.suggestedSentence,
          description: element.description,
          id: generateUUID(),
          documentId: documentId,
          isResolved: false,
        };

        dataStream.writeData({
          type: 'suggestion',
          content: suggestion as any, // Cast to any to avoid issues with slightly different local Suggestion types if they arise
        });

        suggestionsToStream.push(suggestion);
      }

      // if (session.user?.id) { // saveSuggestions logic removed
      //   const userId = session.user.id;
      //   await saveSuggestions({
      //     suggestions: suggestionsToStream.map((suggestion) => ({
      //       ...suggestion,
      //       userId,
      //       createdAt: new Date(),
      //       documentCreatedAt: document.createdAt, // document.createdAt would be undefined
      //     })),
      //   });
      // }
      console.warn('saveSuggestions call removed from requestSuggestions tool.');

      return {
        id: documentId,
        // title: document.title, // document is undefined
        // kind: document.kind, // document is undefined
        title: 'Document (title unavailable)',
        kind: 'text', // Defaulting kind, document is undefined
        message: 'Suggestions have been streamed (mocked/limited due to DB removal)',
      };
    },
  });
