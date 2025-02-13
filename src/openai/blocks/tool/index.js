import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, RichText, MediaUpload } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SelectControl, Placeholder, TextControl } from '@wordpress/components';
registerBlockType( 'pos/ai-tool', {
	edit: EditComponent,
	save: SaveComponent,
} );

function EditComponent( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const [ tools, setTools ] = useState( [] );

	useEffect( () => {
		apiFetch( {
			path: '/pos/v1/openai/chat/tools',
		} ).then( ( tools ) => {
			setTools( tools );
		} );
	}, [] );

	const handleToolChange = ( toolName ) => {
		setAttributes( { tool: toolName } );
		
		// Reset tool parameters when changing tools
		if ( attributes.parameters ) {
			setAttributes( { parameters: {} } );
		}
	};

	const renderParameterFields = () => {
		const selectedTool = tools.find( ( t ) => t.function.name === attributes.tool );
		if ( ! selectedTool?.function?.parameters?.properties ) {
			return null;
		}

		const parameters = selectedTool.function.parameters.properties;

		const parameterFields = Object.entries( parameters ).map( ( [ key, param ] ) => {
			let inputField;

			if ( param.enum ) {
				inputField = (
					<SelectControl
						label={ key }
						help={ param.description }
						value={ attributes.parameters?.[ key ] || '' }
						options={ param.enum.map( ( option ) => ( {
							label: option,
							value: option,
						} ) ) }
						onChange={ ( value ) => {
							setAttributes( {
								parameters: {
									...attributes.parameters,
									[ key ]: value,
								},
							} );
						} }
					/>
				);
			} else if ( param.type === 'string' ) {
				inputField = (
					<TextControl
						label={ key }
						help={ param.description }
						value={ attributes.parameters?.[ key ] || '' }
						onChange={ ( value ) => {
							setAttributes( {
								parameters: {
									...attributes.parameters,
									[ key ]: value,
								},
							} );
						} }
					/>
				);
			} else if ( param.type === 'integer' ) {
				inputField = (
					<TextControl
						type="number"
						label={ key }
						help={ param.description }
						value={ attributes.parameters?.[ key ] || '' }
						onChange={ ( value ) => {
							setAttributes( {
								parameters: {
									...attributes.parameters,
									[ key ]: parseInt( value, 10 ),
								},
							} );
						} }
					/>
				);
			}

			return (
				<div key={ key } className="pos-ai-tool__parameter">
					{ inputField }
				</div>
			);
		} );

		return [
			<div key="description">
				{ selectedTool.function.description }
			</div>,
		].concat( parameterFields );
	};

	return (
		<div
			{ ...blockProps }
			className="wp-block pos-ai-tool"
		>
			<Placeholder
				icon="smiley"
				label={ attributes.tool ? attributes.tool : __( 'AI Tool', 'pos' ) }
				instructions={ __( 'Output the result of an AI Tool.', 'pos' ) }
			>
				<div style={ { display: 'flex', flexDirection: 'column', gap: '10px' } }>
					<SelectControl
						label={ __( 'AI Tool to render', 'pos' ) }
						options={ tools.map( ( tool ) => ( {
							label: `${ tool.function.name }`,
							value: tool.function.name,
						} ) ).concat( {
							label: __( 'None', 'pos' ),
							value: '',
						} ) }
						value={ attributes.tool }
						onChange={ handleToolChange }
					/>
					{ renderParameterFields() }
				</div>
			</Placeholder>
		</div>
	);
}

function SaveComponent( { attributes } ) {
	const blockProps = useBlockProps.save();

	return (
		<div
			{ ...blockProps }
			className="wp-block pos-ai-tool"
		>
			<p>This is a static block.</p>
		</div>
	);
}
