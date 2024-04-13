/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { Icon, drafts } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from './block.json';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
const selfURL = new URL( document.baseURI );
const regex = /post\.php\?post=([0-9]+)\&action=edit/i;
registerBlockType( metadata.name, {
	icon: <Icon icon={ drafts } />,
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	/**
	 * @see ./save.js
	 */
	save,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/group' ],
				transform: ( attributes, innerBlocks ) => {
					return createBlock( metadata.name, {
						note_id: 0,
					}, innerBlocks );
				},
			},
			{
				type: 'raw',
				isMatch: ( node ) => {
					if ( node.nodeName !== 'P' ) {
						return false;
					}
					const content = node.textContent;
					if ( ! content.includes( selfURL.origin ) ) {
						return false;
					}
					// Now we are talking. This may be it.
					return regex.test( content );
				},
				transform: ( node ) => {
					// TODO: Disallow embedding other post types
					const match = node.textContent.match( regex );
					const note_id = parseInt( match[1] );
					return createBlock( metadata.name, {
						note_id,
					} );
				},
			},
		]
	}
} );