/**
 * WordPress dependencies
 */

import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { useEffect, useState } from "@wordpress/element";
import { useDispatch, useSelect } from '@wordpress/data';
import { serialize, parse } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Icon, drafts } from '@wordpress/icons';
import { createBlock } from '@wordpress/blocks';
import { TextControl } from '@wordpress/components';

import './index.css';


const NoteCompleter = {
	name: 'links',
	className: 'block-editor-autocompleters__link',
	triggerPrefix: '[[',
	options: async ( letters ) => {
		let options = await apiFetch( {
			path: addQueryArgs( '/pos/v1/notes', {
				per_page: 10,
				search: letters,
			} ),
		} );
	
		options = options.map( ( { id, title, type, excerpt } ) => ( {
			id,
			title: title.rendered,
			type,
			excerpt: excerpt.rendered.replace( /(<([^>]+)>)/gi, '' ).substring( 0, 100 ),
		} ) );

		return options;
	},
	getOptionKeywords( item ) {
		const expansionWords = item.title.split( /\s+/ );
		const experptWords = item.excerpt.split( /\s+/ );
		return [ ...expansionWords, ...experptWords ];
	},
	getOptionLabel( item ) {
		return (
			<>
				<Icon
					key="icon"
					icon={ drafts }
				/>
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
		}
	},
}

function mergeCompleters( completers ) {
	return {
		name: completers[0].name,
		className: completers[0].className,
		triggerPrefix: completers[0].triggerPrefix,
		options: async ( letters ) => {
			const completerResults = await Promise.all(
				completers.map( completer => completer.options( letters ) )
			);
			const opt = completerResults.map( ( completer, completerId ) => completer.map( option => ( { ...option, completer: completerId } ) ) ).flat();
			return opt;
		},
		getOptionKeywords: ( item ) => completers[item.completer].getOptionKeywords( item ),
		getOptionLabel: ( item ) => completers[item.completer].getOptionLabel( item ),
		getOptionCompletion: ( item ) => completers[item.completer].getOptionCompletion( item ),
	}
}


function appendMergedCompleter( completers, blockName ) {
	const linksCompleter = completers.find( ( { name } ) => name === 'links' );
	const allCompleters = completers.filter( ( { name } ) => name !== 'links' );
    return [ mergeCompleters( [ linksCompleter, NoteCompleter ] ), ...allCompleters ]
}


// Adding the filter
wp.hooks.addFilter(
    'editor.Autocomplete.completers',
    'pos/autocompleters/links-and-notes',
    appendMergedCompleter
);


const Edit = ( props ) => {
	const {
		attributes: { note_id },
		setAttributes,
	} = props;

	const blockProps = useBlockProps();
	const { children, ...innerBlocksProps } = useInnerBlocksProps();

	const { saveEntityRecord, editEntityRecord } = useDispatch( 'core' );
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	const localEmbeddedContent = useSelect( ( select ) => {
		const { getBlocksByClientId } = select( 'core/block-editor' );
		const childrenBlocks = getBlocksByClientId( children?.props?.clientId )[0]?.innerBlocks || [];
		return childrenBlocks ? serialize( childrenBlocks ) : "";
	}, [ children ] );

	const currentPostTitle = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostAttribute('title') );

	const remoteEmbedded = useSelect( ( select ) => {
		if ( ! note_id ) {
			return null;
		}
		return select('core').getEntityRecord( 'postType', 'notes', note_id );
	}, [ note_id ] );

	const [ titleEdited, editTitle ] = useState( '' );

	const remoteEmbeddedContent = remoteEmbedded?.content?.raw;
	useEffect( () => {
		if ( ! note_id ) {
			const today = ( new Date() ).toISOString().split( 'T' )[0];
			saveEntityRecord( 'postType', 'notes', { title: `Note from ${currentPostTitle} ${today}`, status: 'draft', content: localEmbeddedContent } ).then( note => {
				setAttributes( { note_id: note.id } );
				editTitle( note.title.raw );
			} );
		} else if ( remoteEmbedded && remoteEmbedded.content ) {
			replaceInnerBlocks( props?.clientId, parse( remoteEmbeddedContent ) );
			editTitle( remoteEmbedded.title.raw );
		}
	}, [ note_id, remoteEmbeddedContent ] );

	useEffect( () => {
		if( ! note_id ) {
			return;
		}
		if ( remoteEmbeddedContent === localEmbeddedContent ) {
			return;
		}
		if ( ! localEmbeddedContent || localEmbeddedContent.length < 5 ) {
			return;
		}
		if( typeof remoteEmbeddedContent !== 'string' ) {
			return;
		}
		editEntityRecord( 'postType', 'notes', note_id, { content: localEmbeddedContent } );
	}, [ localEmbeddedContent, remoteEmbeddedContent, note_id ] );


	return (
		<div { ...blockProps }>
			{ note_id && ( <InspectorControls>
				<PanelBody title={ 'Note' }>
					<p>
						<a
							target="_blank"
							href={ `/wp-admin/post.php?post=${ note_id }&action=edit` }
						>
							Open original note
						</a>
					</p>
					{ note_id && remoteEmbedded && ( <TextControl
						label="Note title"
						value={ titleEdited }
						onChange={ ( value ) => {
							editEntityRecord( 'postType', 'notes', note_id, { title: value } );
							editTitle( value );
						} }
					/> ) }
				</PanelBody>
			</InspectorControls> ) }
			<div className='wp-block-pos-note__title'>{ remoteEmbedded?.title.raw }</div>
			<div { ...innerBlocksProps }>
				{ children }
			</div>
		</div>
	);
};
export default Edit;