/**
 * Find a notebook by ID or slug
 *
 * @param {string|number} id      The notebook ID or slug to find.
 * @param {Array}        notebooks Array of notebook objects.
 * @return {Object|undefined}     The found notebook or undefined.
 */
export function getNotebook( id, notebooks ) {
	return notebooks.find(
		( notebook ) => notebook.id == id || notebook.slug == id
	);
} 