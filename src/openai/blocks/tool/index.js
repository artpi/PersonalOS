import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, RichText, MediaUpload } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SelectControl, Placeholder, TextControl, CheckboxControl } from '@wordpress/components';
registerBlockType( 'pos/ai-tool', {
	edit: EditComponent,
	save: SaveComponent,
} );

function EditComponent( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const [ abilities, setAbilities ] = useState( [] );

	useEffect( () => {
		apiFetch( {
			path: '/wp-abilities/v1/abilities?per_page=100',
		} ).then( ( response ) => {
			// Abilities API returns an array of ability objects with name, label, description, input_schema
			// Filter to show only non-destructive abilities
			const allAbilities = Array.isArray( response ) ? response : [];
			const nonDestructiveAbilities = allAbilities.filter(
				( ability ) => ! ability.meta?.annotations?.destructive
			);
			setAbilities( nonDestructiveAbilities );
		} ).catch( ( error ) => {
			console.error( 'Failed to fetch abilities:', error );
			setAbilities( [] );
		} );
	}, [] );

	const handleAbilityChange = ( abilityName ) => {
		setAttributes( { tool: abilityName } );

		// Reset ability parameters when changing abilities
		if ( attributes.parameters ) {
			setAttributes( { parameters: {} } );
		}

		// Reset output fields and format when changing abilities
		setAttributes( { outputFields: [], outputFormat: 'json' } );
	};

	const renderParameterFields = () => {
		const selectedAbility = abilities.find(
			( a ) => a.name === attributes.tool
		);
		if ( ! selectedAbility?.input_schema?.properties ) {
			return null;
		}

		const parameters = selectedAbility.input_schema.properties;

		const parameterFields = Object.entries( parameters ).map(
			( [ key, param ] ) => {
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
			}
		);

		return [
			<div key="description">{ selectedAbility.description }</div>,
		].concat( parameterFields );
	};

	// Extract output schema properties
	const getOutputSchemaProperties = () => {
		const selectedAbility = abilities.find(
			( a ) => a.name === attributes.tool
		);
		if ( ! selectedAbility?.output_schema ) {
			return null;
		}

		const outputSchema = selectedAbility.output_schema;

		// Handle object type output schema
		if ( outputSchema.type === 'object' && outputSchema.properties ) {
			return Object.keys( outputSchema.properties );
		}

		// Handle array type output schema (items.properties)
		if ( outputSchema.type === 'array' && outputSchema.items?.properties ) {
			return Object.keys( outputSchema.items.properties );
		}

		return null;
	};

	const outputSchemaFields = getOutputSchemaProperties();
	const hasOutputSchema = outputSchemaFields !== null;
	const hasMultipleFields = hasOutputSchema && outputSchemaFields.length > 1;

	// Initialize outputFields with all fields if not set and output schema exists
	useEffect( () => {
		if ( hasOutputSchema && outputSchemaFields && ( ! attributes.outputFields || attributes.outputFields.length === 0 ) ) {
			setAttributes( { outputFields: outputSchemaFields } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ attributes.tool ] );

	const renderOutputFields = () => {
		if ( ! hasOutputSchema || ! outputSchemaFields ) {
			return null;
		}

		const selectedFields = attributes.outputFields || [];
		const fieldCheckboxes = outputSchemaFields.map( ( fieldName ) => {
			const isChecked = selectedFields.includes( fieldName );
			return (
				<CheckboxControl
					key={ fieldName }
					label={ fieldName }
					checked={ isChecked }
					onChange={ ( checked ) => {
						const newFields = checked
							? [ ...selectedFields, fieldName ]
							: selectedFields.filter( ( f ) => f !== fieldName );
						setAttributes( { outputFields: newFields } );
					} }
				/>
			);
		} );

		return (
			<div key="output-fields" style={ { marginTop: '10px' } }>
				<strong>{ __( 'Output Fields', 'pos' ) }</strong>
				<div
					style={ {
						marginTop: '5px',
						display: 'flex',
						flexDirection: 'column',
						gap: '5px',
					} }
				>
					{ fieldCheckboxes }
				</div>
			</div>
		);
	};

	const renderOutputFormat = () => {
		if ( ! hasMultipleFields ) {
			return null;
		}

		return (
			<SelectControl
				key="output-format"
				label={ __( 'Output Format', 'pos' ) }
				value={ attributes.outputFormat || 'json' }
				options={ [
					{ label: 'JSON', value: 'json' },
					{ label: 'XML', value: 'xml' },
				] }
				onChange={ ( value ) => {
					setAttributes( { outputFormat: value } );
				} }
			/>
		);
	};

	return (
		<div { ...blockProps } className="wp-block pos-ai-tool">
			<Placeholder
				icon="smiley"
				label={
					attributes.tool ? attributes.tool : __( 'WP Ability', 'pos' )
				}
				instructions={ __( 'Output the result of a WordPress Ability.', 'pos' ) }
			>
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: '10px',
					} }
				>
					<SelectControl
						label={ __( 'WordPress Ability to render', 'pos' ) }
						options={ abilities
							.map( ( ability ) => ( {
								label: `${ ability.name }: ${ ability.label || ability.name }`,
								value: ability.name,
							} ) )
							.concat( {
								label: __( 'None', 'pos' ),
								value: '',
							} ) }
						value={ attributes.tool }
						onChange={ handleAbilityChange }
					/>
					{ renderParameterFields() }
					{ renderOutputFields() }
					{ renderOutputFormat() }
				</div>
			</Placeholder>
		</div>
	);
}

function SaveComponent( { attributes } ) {
	const blockProps = useBlockProps.save();

	return (
		<div { ...blockProps } className="wp-block pos-ai-tool">
			<p>This is a static block.</p>
		</div>
	);
}
