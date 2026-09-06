import { registerCoreBlocks } from '@wordpress/block-library';
import { parse, serialize } from '@wordpress/blocks';
import { parse as parseMarkup } from '@wordpress/block-serialization-default-parser';
import { appendPhotos, contentSession, itemContent } from '../content';
import { SearchIndex } from '../search';

beforeAll( () => registerCoreBlocks() );

test( 'title and taxonomy edits leave original content untouched', () => {
	const raw =
		'<!-- wp:paragraph -->\n<p>Camera <strong>kit</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:vendor/unknown {"value":42} --><aside>Keep me</aside><!-- /wp:vendor/unknown -->';
	const session = contentSession( raw );
	expect( session.serialize( session.blocks ) ).toBe( raw );
	const next = session.blocks.map( ( block, i ) =>
		i === 0
			? {
					...block,
					attributes: {
						...block.attributes,
						content: 'Changed description',
					},
			  }
			: block
	);
	const saved = session.serialize( next );
	const preserved = parseMarkup( saved ).find(
		( block ) => block.blockName === 'vendor/unknown'
	);
	expect( preserved.attrs ).toEqual( { value: 42 } );
	expect( preserved.innerHTML.trim() ).toBe( '<aside>Keep me</aside>' );
	expect( saved ).toContain( '<p>Changed description</p>' );
} );

test( 'photos serialize as native galleries and order determines cover', () => {
	const blocks = appendPhotos(
		[],
		[
			{ id: 4, source_url: 'https://example.org/a.jpg' },
			{ id: 5, source_url: 'https://example.org/b.jpg' },
		]
	);
	const raw = serialize( blocks );
	expect( raw ).toContain( '<!-- wp:gallery' );
	expect( parse( raw )[ 0 ].isValid ).toBe( true );
	expect(
		parse( raw )[ 0 ].innerBlocks.every( ( block ) => block.isValid )
	).toBe( true );
	expect( itemContent( raw ).photos.map( ( photo ) => photo.url ) ).toEqual( [
		'https://example.org/a.jpg',
		'https://example.org/b.jpg',
	] );
	const gallery = {
		...blocks[ 0 ],
		innerBlocks: [ ...blocks[ 0 ].innerBlocks ].reverse(),
	};
	expect( itemContent( serialize( [ gallery ] ) ).photos[ 0 ].url ).toBe(
		'https://example.org/b.jpg'
	);
} );

test( 'description changes preserve gallery captions and nested blocks', () => {
	const raw =
		'<!-- wp:paragraph -->\n<p>Old description</p>\n<!-- /wp:paragraph -->\n\n' +
		serialize(
			appendPhotos(
				[],
				[ { id: 4, source_url: 'https://example.org/a.jpg' } ]
			)
		);
	const session = contentSession( raw );
	const next = session.blocks.map( ( block, i ) =>
		i === 0
			? {
					...block,
					attributes: {
						...block.attributes,
						content: 'New description',
					},
			  }
			: block
	);
	expect(
		parse( session.serialize( next ) )[ 1 ].innerBlocks[ 0 ].attributes.id
	).toBe( 4 );
} );

test( 'search normalizes accents and searches ancestor paths with typo tolerance', () => {
	const index = new SearchIndex();
	index.rebuild( [
		{
			name: 'Łódź camera',
			location: 'Home / Garage / Box',
			tags: [ 'travel' ],
			description: '',
			photoCount: 1,
			placeId: 4,
		},
	] );
	expect( index.search( 'lodz' ) ).toHaveLength( 1 );
	expect( index.search( 'camra' ) ).toHaveLength( 1 );
	expect( index.search( 'garage' ) ).toHaveLength( 1 );
	expect( index.search( 'camera', { photo: 'without' } ) ).toHaveLength( 0 );
	expect(
		index.search( 'camera', { placeIds: new Set( [ 8 ] ) } )
	).toHaveLength( 0 );
} );
