import { createItemSlug } from '../identity';

test( 'uses a UUID without deriving it from an item title', () => {
	const randomUUID = jest.fn( () => '123e4567-e89b-42d3-a456-426614174000' );

	expect( createItemSlug( { randomUUID } ) ).toBe(
		'123e4567-e89b-42d3-a456-426614174000'
	);
	expect( randomUUID ).toHaveBeenCalledTimes( 1 );
} );

test( 'creates an RFC 4122 version 4 UUID with getRandomValues fallback', () => {
	const getRandomValues = jest.fn( ( bytes ) => {
		bytes.fill( 0xab );
		return bytes;
	} );

	expect( createItemSlug( { getRandomValues } ) ).toBe(
		'abababab-abab-4bab-abab-abababababab'
	);
} );
