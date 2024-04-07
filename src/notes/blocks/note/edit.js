/**
 * WordPress dependencies
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useEffect } from "@wordpress/element";
import { useDispatch, useSelect } from '@wordpress/data';
import { serialize } from '@wordpress/blocks';


import './index.css';

const Edit = ( props ) => {
	const {
		attributes: { note_id },
		setAttributes,
	} = props;

	const blockProps = useBlockProps();
	const { children, ...innerBlocksProps } = useInnerBlocksProps();

	const { saveEntityRecord, editEntityRecord } = useDispatch( 'core' );

	const { childrenBlocks } = useSelect( ( select ) => {
		const { getBlocksByClientId } = select( 'core/block-editor' );
		const childrenBlocks = getBlocksByClientId( children?.props?.clientId )[0]?.innerBlocks || [];
		return { childrenBlocks };
	}, [ children ] );


	useEffect( () => {
		if ( ! note_id ) {
			saveEntityRecord( 'postType', 'notes', { title: 'Embedded Note', status: 'draft' } ).then( note => {
				setAttributes( { note_id: note.id } );
			} );
		}
	}, [note_id] );

	useEffect( () => {
		if ( childrenBlocks && childrenBlocks.length ) {
			if ( ! note_id ) {
				return;
			}
			const content = serialize( childrenBlocks );
			if ( ! content ) {
				return;
			}
			editEntityRecord( 'postType', 'notes', note_id, { content } );
		}
	}, [ childrenBlocks, note_id ] );

	
	
	return (
		<div { ...blockProps }>
			<div {...innerBlocksProps}>
				{ children }
			</div>
		</div>
	);
};
export default Edit;