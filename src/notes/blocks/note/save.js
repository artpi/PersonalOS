/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

const Save = ( props ) => {
	let blockProps = useBlockProps.save();
    blockProps = { ...blockProps, attributes: { note_id: 100 } };
    console.log( 'save', blockProps, props );
	return <div { ...blockProps }></div>;
};
export default Save;
