import { createBlock, parse, serialize } from '@wordpress/blocks';

export function itemContent( content ) {
	const document = new window.DOMParser().parseFromString(
		content,
		'text/html'
	);
	return {
		description: document.body.textContent.trim(),
		photos: Array.from( document.querySelectorAll( 'img' ) ).map(
			( img ) => ( {
				url: img.getAttribute( 'src' ),
				srcSet: img.getAttribute( 'srcset' ) || '',
				alt: img.getAttribute( 'alt' ) || '',
			} )
		),
	};
}

export function appendPhotos( blocks, photos ) {
	const images = photos.map( ( photo ) =>
		createBlock( 'core/image', {
			id: photo.id,
			url: photo.url || photo.source_url,
			alt: photo.alt_text || '',
			sizeSlug: 'full',
			linkDestination: 'none',
		} )
	);
	const last = blocks[ blocks.length - 1 ];
	return last?.name === 'core/gallery'
		? [
				...blocks.slice( 0, -1 ),
				{ ...last, innerBlocks: [ ...last.innerBlocks, ...images ] },
		  ]
		: [
				...blocks,
				createBlock( 'core/gallery', { linkTo: 'none' }, images ),
		  ];
}

// Keep the original post for non-content edits. Gutenberg's serializer retains
// originalContent for unavailable custom blocks when surrounding blocks change.
export function contentSession( raw ) {
	const blocks = parse( raw );
	const originals = new Map(
		blocks.map( ( block ) => [
			block.clientId,
			{ block, serialized: serialize( [ block ] ) },
		] )
	);
	return {
		blocks,
		serialize: ( next ) => {
			if (
				next.length === blocks.length &&
				next.every( ( block, i ) => block === blocks[ i ] )
			) {
				return raw;
			}
			// The block parser preserves unknown blocks' originalContent, including
			// nested content. Avoid reparsing the post during unrelated field edits.
			return next
				.map( ( block ) =>
					originals.get( block.clientId )?.block === block
						? originals.get( block.clientId ).serialized
						: serialize( [ block ] )
				)
				.join( '\n\n' );
		},
	};
}
