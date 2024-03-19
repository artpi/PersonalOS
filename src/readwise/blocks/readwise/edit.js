/**
 * WordPress dependencies
 */

import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

const Edit = ( props ) => {
	const {
		attributes: { content, readwise_url },
		setAttributes,
	} = props;

	const blockProps = useBlockProps();

	const onChangeContent = ( newContent ) => {
		setAttributes( { content: newContent } );
	};
	return (
		<Fragment>
			<RichText
				{ ...blockProps }
				tagName="p"
				onChange={ onChangeContent }
				value={ content }
			/>
			{ readwise_url && ( <InspectorControls>
				<PanelBody title={ 'Readwise' }>
					<p>
						<a
							target="_blank"
							href={ readwise_url }
						>
							Open on Readwise
						</a>
					</p>
				</PanelBody>
			</InspectorControls> ) }
		</Fragment>
	);
};
export default Edit;