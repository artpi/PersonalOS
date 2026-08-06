export const SYSTEM_TERMS = [
	'artifact',
	'note',
	'daily-note',
	'todo',
	'conversation',
	'memory',
	'skill',
	'manual',
	'synced',
	'readwise',
	'evernote',
	'ai-chat',
	'personalos',
];

export const CONTAINER_TERMS = [
	'status',
	'project',
	'area',
	'resource',
	'archive',
];

export function isAssignableKnowledgeType( term ) {
	return (
		! SYSTEM_TERMS.includes( term.slug ) &&
		! CONTAINER_TERMS.includes( term.slug )
	);
}
