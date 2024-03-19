/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';


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
registerBlockType( metadata.name, {
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	/**
	 * @see ./save.js
	 */
	save,
	icon: {
		src: (
			<svg version="1.0" xmlns="http://www.w3.org/2000/svg"
			width="400.000000pt" height="400.000000pt" viewBox="0 0 400.000000 400.000000"
			preserveAspectRatio="xMidYMid meet">
		
		<g transform="translate(0.000000,400.000000) scale(0.100000,-0.100000)"
		fill="#000000" stroke="none">
		<path d="M1014 3017 c-3 -8 -4 -36 -2 -63 l3 -48 66 -11 c36 -6 78 -19 93 -28
		57 -38 56 -17 56 -866 0 -697 -2 -785 -16 -821 -18 -45 -47 -65 -109 -75 -103
		-17 -95 -10 -95 -76 l0 -59 465 0 465 0 0 59 c0 66 8 59 -95 76 -58 10 -92 34
		-110 77 -12 32 -15 98 -15 388 l0 350 73 0 72 0 284 -475 284 -475 306 2 306
		3 3 57 c3 56 2 58 -25 63 -67 15 -121 41 -160 79 -26 26 -128 181 -268 410
		-124 202 -225 369 -223 371 2 1 34 10 72 19 201 50 326 161 371 332 20 76 21
		270 1 341 -46 170 -156 269 -362 327 -153 44 -220 48 -841 53 -516 4 -594 3
		-599 -10z m1305 -339 c0 -101 23 -324 37 -370 3 -10 1 -26 -5 -36 -9 -17 -27
		-20 -128 -26 -200 -12 -420 -58 -471 -99 -48 -38 16 37 272 320 160 177 292
		323 292 323 1 0 2 -51 3 -112z"/>
		</g>
		</svg>
	   ),
	},
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ 'core/paragraph' ],
				transform: ( { content } ) => {
					return createBlock( 'core/paragraph', {
						content,
					} );
				},
			},
		]
	}
} );