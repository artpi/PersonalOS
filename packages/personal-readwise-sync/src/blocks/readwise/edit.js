/**
 * WordPress dependencies
 */

import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import './index.css';

const Edit = ( props ) => {
	const {
		attributes: { content, readwise_url: readwiseUrl },
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
			{ readwiseUrl && (
				<InspectorControls>
					<PanelBody title={ 'Readwise' }>
						<p>
							<a
								target="_blank"
								href={ readwiseUrl }
								rel="noreferrer"
							>
								Open on Readwise
							</a>
						</p>
					</PanelBody>
				</InspectorControls>
			) }
		</Fragment>
	);
};
export default Edit;
