import {
	PluginSidebar,
	PluginDocumentSettingPanel,
} from '@wordpress/edit-post';
import { drafts } from '@wordpress/icons';
import { TextControl, Popover,Draggable } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, RawHTML } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useDispatch } from '@wordpress/data';
import { createBlock, serialize } from '@wordpress/blocks';

const SearchResult = ( { post } ) => {
	const [ modalOpen, setModalOpen ] = useState( false );

	const textContent =
		post.content.rendered
			.replace( /(<([^>]+)>)/gi, '' )
			.substring( 0, 100 ) + '...';
	return (
		<div id={ `note-sidebar-${post.id}` } style={ { borderBottom: '1px solid black' } }>
			<Draggable
				elementId={ `note-sidebar-${post.id}` }
				__experimentalTransferDataType="wp-blocks"
				transferData={{}}
				onDragStart={ ( event ) => {
					const block = createBlock( 'pos/note', {
						note_id: post.id,
					} )
					event.dataTransfer.setData(
						'text/html',
						serialize( [ block ] )
					);
				} }
			>
				{ ( { onDraggableStart, onDraggableEnd } ) => (
					<div
						draggable
						onDragStart={ onDraggableStart }
						onDragEnd={ onDraggableEnd }
					>
						<h2 onClick={ () => setModalOpen( ! modalOpen ) }>
						{ post.title.rendered }
						</h2>
						<p>{ textContent }</p>
					</div>
				) }
			</Draggable>
			{ modalOpen && (
				<Popover>
					<RawHTML style={{ minWidth: '500px'}}>{ post.content.rendered }</RawHTML>
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
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'notebook'
		) ?? [];
	} );

	const debouncedAPIFetch = useDebounce( ( search ) => {
		apiFetch( { path: '/pos/v1/notes?' + search.toString() } ).then(
			( posts ) => setResults( posts )
		);
	}, 3000 );

	useEffect( () => {
		let params = new URLSearchParams();
		if ( search.length ) {
			params.append( 'search', search );
		}
		if ( selectedTaxonomies.length ) {
			params.append( 'notebook', selectedTaxonomies.join( ',' ) );
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

	const postId = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPostId();
	} );

	const isAutosaving = useSelect( ( select ) => {
		return select( 'core/editor' ).isAutosavingPost();
	} );
	const { savePost } = useDispatch( 'core/editor' );

	useEffect( () => {
		// We are catching a transition from saving to not-autosaving anymore.
		if ( ! isAutosaving ) {
			console.log( 'Just auto saved' );
			savePost();
		}
	}, [ isAutosaving ] );


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
			{ metaFields[ 'evernote_guid' ] && (
				<PluginDocumentSettingPanel
					name="notes-evernote"
					title="Evernote"
				>
					<p>
						<a
							target="_blank"
							href={ '/?rest_route=/pos/v1/evernote-redirect/' + postId }
						>
							Open in an evernote app
						</a>
					</p>
				</PluginDocumentSettingPanel>
			) }
			<PluginSidebar name="pos-notes" title="Notes" icon={ drafts }>
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
