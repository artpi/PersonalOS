/**
 * WordPress dependencies
 */

import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { useEffect } from "@wordpress/element";
import { useDispatch, useSelect } from '@wordpress/data';
import { serialize, parse } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';

import './index.css';

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

	const remoteEmbedded = useSelect( ( select ) => {
		if ( ! note_id ) {
			return null;
		}
		return select('core').getEntityRecord( 'postType', 'notes', note_id );
	}, [ note_id ] );

	const remoteEmbeddedContent = remoteEmbedded?.content?.raw;
	useEffect( () => {
		if ( ! note_id ) {
			saveEntityRecord( 'postType', 'notes', { title: 'Embedded Note', status: 'draft' } ).then( note => {
				setAttributes( { note_id: note.id } );
			} );
		} else if ( remoteEmbedded && remoteEmbedded.content ) {
			replaceInnerBlocks( props?.clientId, parse( remoteEmbeddedContent ) );
		}
	}, [note_id, remoteEmbeddedContent ] );

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
		if( ! remoteEmbeddedContent ) {
			return;
		}
		console.log( 'saving new content', localEmbeddedContent, remoteEmbeddedContent );
		editEntityRecord( 'postType', 'notes', note_id, { id: note_id, content: localEmbeddedContent } );
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
				</PanelBody>
			</InspectorControls> ) }
			<div { ...innerBlocksProps }>
				{ children }
			</div>
		</div>
	);
};
export default Edit;