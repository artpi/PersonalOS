// Adapted from Artur Piszek's Stuff search index.
const normalizeSearchText = ( value = '' ) =>
	String( value )
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( /ł/g, 'l' )
		.replace( /Ł/g, 'L' )
		.toLowerCase()
		.replace( /\s+/g, ' ' )
		.trim();
const parseTags = ( value ) =>
	Array.isArray( value ) ? value : String( value || '' ).split( ',' );

function boundedDistance( left, right, maximum = 2 ) {
	if ( Math.abs( left.length - right.length ) > maximum ) return maximum + 1;
	const previous = Array.from(
		{ length: right.length + 1 },
		( _, index ) => index
	);
	for ( let leftIndex = 1; leftIndex <= left.length; leftIndex += 1 ) {
		const current = [ leftIndex ];
		let rowMinimum = current[ 0 ];
		for (
			let rightIndex = 1;
			rightIndex <= right.length;
			rightIndex += 1
		) {
			const insertion = current[ rightIndex - 1 ] + 1;
			const deletion = previous[ rightIndex ] + 1;
			const substitution =
				previous[ rightIndex - 1 ] +
				( left[ leftIndex - 1 ] === right[ rightIndex - 1 ] ? 0 : 1 );
			current[ rightIndex ] = Math.min(
				insertion,
				deletion,
				substitution
			);
			rowMinimum = Math.min( rowMinimum, current[ rightIndex ] );
		}
		if ( rowMinimum > maximum ) return maximum + 1;
		previous.splice( 0, previous.length, ...current );
	}
	return previous[ right.length ];
}

function tokenMatches( queryToken, candidateToken ) {
	if ( candidateToken === queryToken ) return 1;
	if ( candidateToken.startsWith( queryToken ) ) return 0.82;
	if ( candidateToken.includes( queryToken ) ) return 0.64;
	if (
		queryToken.length >= 4 &&
		boundedDistance( queryToken, candidateToken, 2 ) <= 2
	)
		return 0.48;
	return 0;
}

function fieldScore( query, tokens, field, weight ) {
	if ( ! field ) return 0;
	if ( field === query ) return weight * 1.5;
	if ( field.startsWith( query ) ) return weight * 1.25;
	const candidates = field.split( ' ' );
	return tokens.reduce( ( score, token ) => {
		const best = candidates.reduce(
			( maximum, candidate ) =>
				Math.max( maximum, tokenMatches( token, candidate ) ),
			0
		);
		return score + best * weight;
	}, 0 );
}

export class SearchIndex {
	#entries = [];

	rebuild( items, placesById = new Map() ) {
		this.#entries = items.map( ( item, order ) => {
			const place = placesById.get( item.placeId );
			const location = normalizeSearchText(
				item.location || place?.path || ''
			);
			return {
				item,
				order,
				name: normalizeSearchText( item.name ),
				description: normalizeSearchText( item.description ),
				tags: normalizeSearchText( parseTags( item.tags ).join( ' ' ) ),
				location,
			};
		} );
	}

	search( query, { placeIds = null, photo = 'all' } = {} ) {
		const normalized = normalizeSearchText( query );
		const tokens = normalized ? normalized.split( ' ' ) : [];
		return this.#entries
			.filter(
				( { item } ) => ! placeIds || placeIds.has( item.placeId )
			)
			.filter(
				( { item } ) =>
					photo === 'all' ||
					( photo === 'with'
						? Number( item.photoCount ) > 0
						: Number( item.photoCount ) === 0 )
			)
			.map( ( entry ) => ( {
				item: entry.item,
				order: entry.order,
				score: normalized
					? fieldScore( normalized, tokens, entry.name, 12 ) +
					  fieldScore( normalized, tokens, entry.tags, 8 ) +
					  fieldScore( normalized, tokens, entry.location, 6 ) +
					  fieldScore( normalized, tokens, entry.description, 3 )
					: 1,
			} ) )
			.filter( ( { score } ) => score > 0 )
			.sort(
				( left, right ) =>
					right.score - left.score || left.order - right.order
			)
			.map( ( { item } ) => item );
	}
}

export { boundedDistance };
