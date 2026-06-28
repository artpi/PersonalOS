/**
 * WordPress dependencies
 */
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { createBlock, parse, serialize } from '@wordpress/blocks';
import { PanelBody, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { Icon, drafts } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

import './index.css';

const NoteCompleter = {
	name: 'links',
	className: 'block-editor-autocompleters__link',
	triggerPrefix: '[[',
	options: async ( letters ) => {
		const options = await apiFetch( {
			path: addQueryArgs( '/personal-notes/v1/notes', {
				per_page: 10,
				search: letters,
			} ),
		} );

		return options.map( ( { id, title, excerpt } ) => ( {
			id,
			title,
			excerpt: excerpt.replace( /(<([^>]+)>)/gi, '' ).substring( 0, 100 ),
		} ) );
	},
	getOptionKeywords( item ) {
		const expansionWords = item.title.split( /\s+/ );
		const excerptWords = item.excerpt.split( /\s+/ );
		return [ ...expansionWords, ...excerptWords ];
	},
	getOptionLabel( item ) {
		return (
			<>
				<Icon key="icon" icon={ drafts } />
				{ item.title || item.excerpt }
			</>
		);
	},
	getOptionCompletion( item ) {
		return {
			action: 'replace',
			value: createBlock( 'pos/note', {
				note_id: item.id,
			} ),
		};
	},
};

function mergeCompleters( completers ) {
	return {
		name: completers[ 0 ].name,
		className: completers[ 0 ].className,
		triggerPrefix: completers[ 0 ].triggerPrefix,
		options: async ( letters ) => {
			const completerResults = await Promise.all(
				completers.map( ( completer ) => completer.options( letters ) )
			);
			return completerResults
				.map( ( completer, completerId ) =>
					completer.map( ( option ) => ( {
						...option,
						completer: completerId,
					} ) )
				)
				.flat();
		},
		getOptionKeywords: ( item ) =>
			completers[ item.completer ].getOptionKeywords( item ),
		getOptionLabel: ( item ) =>
			completers[ item.completer ].getOptionLabel( item ),
		getOptionCompletion: ( item ) =>
			completers[ item.completer ].getOptionCompletion( item ),
	};
}

function appendMergedCompleter( completers ) {
	const linksCompleter = completers.find( ( { name } ) => name === 'links' );
	if ( ! linksCompleter ) {
		return completers;
	}

	const allCompleters = completers.filter( ( { name } ) => name !== 'links' );
	return [
		mergeCompleters( [ linksCompleter, NoteCompleter ] ),
		...allCompleters,
	];
}

addFilter(
	'editor.Autocomplete.completers',
	'personal-notes/autocompleters/links-and-notes',
	appendMergedCompleter
);

const Edit = ( props ) => {
	const {
		attributes: { note_id: noteId },
		clientId,
		setAttributes,
	} = props;

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps();
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const hydratedNoteId = useRef( 0 );
	const saveTimer = useRef( null );
	const creating = useRef( false );
	const [ remoteEmbedded, setRemoteEmbedded ] = useState( null );
	const [ titleEdited, editTitle ] = useState( '' );

	const localEmbeddedContent = useSelect(
		( select ) => {
			const { getBlocks } = select( 'core/block-editor' );
			return serialize( getBlocks( clientId ) || [] );
		},
		[ clientId ]
	);

	const currentPostTitle = useSelect( ( select ) =>
		select( 'core/editor' ).getCurrentPostAttribute( 'title' )
	);

	useEffect( () => {
		if ( noteId || creating.current ) {
			return;
		}

		creating.current = true;
		const today = new Date().toISOString().split( 'T' )[ 0 ];
		apiFetch( {
			path: '/personal-notes/v1/notes',
			method: 'POST',
			data: {
				title: `Note from ${ currentPostTitle || 'post' } ${ today }`,
				content: localEmbeddedContent,
				terms: [ 'inbox' ],
			},
		} )
			.then( ( note ) => {
				setAttributes( { note_id: note.id } );
				setRemoteEmbedded( note );
				editTitle( note.title );
			} )
			.finally( () => {
				creating.current = false;
			} );
	}, [ currentPostTitle, localEmbeddedContent, noteId, setAttributes ] );

	useEffect( () => {
		if ( ! noteId ) {
			return;
		}

		apiFetch( { path: `/personal-notes/v1/notes/${ noteId }` } ).then(
			( note ) => {
				setRemoteEmbedded( note );
				editTitle( note.title );

				if ( hydratedNoteId.current !== note.id ) {
					replaceInnerBlocks( clientId, parse( note.content || '' ) );
					hydratedNoteId.current = note.id;
				}
			}
		);
	}, [ clientId, noteId, replaceInnerBlocks ] );

	useEffect( () => {
		if ( ! noteId || ! remoteEmbedded ) {
			return;
		}
		if ( remoteEmbedded.content === localEmbeddedContent ) {
			return;
		}
		if ( ! localEmbeddedContent || localEmbeddedContent.length < 5 ) {
			return;
		}

		window.clearTimeout( saveTimer.current );
		saveTimer.current = window.setTimeout( () => {
			apiFetch( {
				path: `/personal-notes/v1/notes/${ noteId }`,
				method: 'PUT',
				data: {
					content: localEmbeddedContent,
				},
			} ).then( setRemoteEmbedded );
		}, 800 );
	}, [ localEmbeddedContent, noteId, remoteEmbedded ] );

	function updateTitle( value ) {
		editTitle( value );

		if ( ! noteId ) {
			return;
		}

		apiFetch( {
			path: `/personal-notes/v1/notes/${ noteId }`,
			method: 'PUT',
			data: {
				title: value,
			},
		} ).then( setRemoteEmbedded );
	}

	return (
		<div { ...blockProps }>
			{ noteId && (
				<InspectorControls>
					<PanelBody title="Note">
						<p>
							<a
								target="_blank"
								href={ `/wp-admin/post.php?post=${ noteId }&action=edit` }
								rel="noreferrer"
							>
								Open original note
							</a>
						</p>
						<TextControl
							label="Note title"
							value={ titleEdited }
							onChange={ updateTitle }
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<div className="wp-block-pos-note__title">
				{ remoteEmbedded?.title }
			</div>
			<div { ...innerBlocksProps } />
		</div>
	);
};
export default Edit;
