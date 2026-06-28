/**
 * WordPress dependencies
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const Save = () => {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save();
	return (
		<div { ...blockProps }>
			<div { ...innerBlocksProps } />
		</div>
	);
};
export default Save;
