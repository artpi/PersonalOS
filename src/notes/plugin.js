import {
	PluginSidebar,
	PluginDocumentSettingPanel,
} from '@wordpress/edit-post';
import { pencil } from '@wordpress/icons';
import { TextControl, Popover } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, RawHTML } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';

const SearchResult = ( { post } ) => {
	const [ modalOpen, setModalOpen ] = useState( false );
	const textContent =
		post.content.rendered
			.replace( /(<([^>]+)>)/gi, '' )
			.substring( 0, 100 ) + '...';
	return (
		<div style={ { borderBottom: '1px solid black' } }>
			<h2 onClick={ () => setModalOpen( ! modalOpen ) }>
				{ post.title.rendered }
			</h2>
			<p>{ textContent }</p>
			{ modalOpen && (
				<Popover>
					<RawHTML>{ post.content.rendered }</RawHTML>
				</Popover>
			) }
		</div>
	);
};

export default function NotesPlugin() {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ selectedTaxonomies, setSelectedTaxonomies ] = useState( [] );

	const allTags = useSelect( ( select ) => {
		const tags = select( 'core' ).getEntityRecords(
			'taxonomy',
			'post_tag'
		);
		const cats = select( 'core' ).getEntityRecords(
			'taxonomy',
			'category'
		);
		return [ ...( cats ?? [] ), ...( tags ?? [] ) ];
	} );

	const debouncedAPIFetch = useDebounce( ( search ) => {
		apiFetch( { path: '/pos/notes/notes?' + search.toString() } ).then(
			( posts ) => setResults( posts )
		);
	}, 3000 );

	useEffect( () => {
		let params = new URLSearchParams();
		if ( search.length ) {
			params.append( 'search', search );
		}
		if ( selectedTaxonomies.length ) {
			params.append( 'tags', selectedTaxonomies.join( ',' ) );
		}

		if ( params.toString().length ) {
			debouncedAPIFetch( params );
		} else {
			setResults( [] );
		}
	}, [ search, selectedTaxonomies ] );

	const metaFields = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) ?? {};
	} );
	console.log( metaFields );
	return (
		<>
			{ metaFields[ 'readwise_id' ] && (
				<PluginDocumentSettingPanel
					name="notes-readwise"
					title="Readwise"
				>
					<p>
						<a
							target="_blank"
							href={
								'https://readwise.io/bookreview/' +
								metaFields[ 'readwise_id' ]
							}
						>
							Open on Readwise
						</a>
					</p>
				</PluginDocumentSettingPanel>
			) }
			<PluginSidebar name="pos-notes" title="Notes" icon={ pencil }>
				<TextControl
					label="Search Notes"
					value={ search }
					onChange={ ( value ) => setSearch( value ) }
				/>
				<div>
					{ allTags.map( ( tag, index ) => (
						<div
							style={ {
								border: '1px solid black',
								fontSize: '10px',
								lineHeight: '10px',
								padding: '3px',
								margin: '3px',
								borderRadius: '3px',
								cursor: 'hand',
								display: 'inline-block',
								backgroundColor: selectedTaxonomies.includes(
									tag.id
								)
									? '#e5ebee'
									: 'white',
							} }
							onClick={ () =>
								setSelectedTaxonomies(
									selectedTaxonomies.includes( tag.id )
										? selectedTaxonomies.filter(
												( sel ) => sel !== tag.id
										  )
										: [ ...selectedTaxonomies, tag.id ]
								)
							}
							key={ index }
						>
							{ tag.name }
						</div>
					) ) }
				</div>
				<div style={ { margin: '10px' } }>
					{ results.map( ( post, index ) => (
						<SearchResult key={ index } post={ post } />
					) ) }
				</div>
			</PluginSidebar>{ ' ' }
		</>
	);
};
