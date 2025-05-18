// 'use server'; // Disabled for static export

// import { getSuggestionsByDocumentId } from '@/lib/db/queries'; // Disabled for static export

export async function getSuggestions({ documentId }: { documentId: string }) {
  // const suggestions = await getSuggestionsByDocumentId({ documentId }); // DB call disabled
  console.warn('getSuggestions in artifacts/actions.ts disabled for static export');
  return []; // Always return empty array for static export
}
