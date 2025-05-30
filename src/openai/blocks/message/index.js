import './index.css';
import { registerBlockType } from '@wordpress/blocks';
import { 
	InspectorControls,
	useBlockProps,
	RichText 
} from '@wordpress/block-editor';
import { 
	PanelBody, 
	SelectControl, 
	TextControl 
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

// Register the message block
registerBlockType( metadata, {
	edit: ( { attributes, setAttributes } ) => {
		const { content, role, id } = attributes;
		const blockProps = useBlockProps( {
			className: `message-role-${ role }`
		} );

		const roleOptions = [
			{ label: __( 'User', 'personalos' ), value: 'user' },
			{ label: __( 'Assistant', 'personalos' ), value: 'assistant' },
			{ label: __( 'System', 'personalos' ), value: 'system' }
		];

		const roleIcons = {
			user: '👤',
			assistant: '🤖',
			system: '⚙️'
		};

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Message Settings', 'personalos' ) }>
						<SelectControl
							label={ __( 'Role', 'personalos' ) }
							value={ role }
							options={ roleOptions }
							onChange={ ( newRole ) => setAttributes( { role: newRole } ) }
						/>
						<TextControl
							label={ __( 'Message ID', 'personalos' ) }
							value={ id }
							onChange={ ( newId ) => setAttributes( { id: newId } ) }
							help={ __( 'Optional unique identifier for this message', 'personalos' ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div className="openai-message-block">
					<div className="message-header">
						<span className="message-role-icon">{ roleIcons[ role ] }</span>
						<span className="message-role-label">{ role }</span>
						{ id && <span className="message-id">ID: { id }</span> }
					</div>
					<div className="message-content">
						<RichText
							tagName="div"
							value={ content }
							onChange={ ( newContent ) => setAttributes( { content: newContent } ) }
							placeholder={ __( 'Enter message content...', 'personalos' ) }
							allowedFormats={ [ 'core/bold', 'core/italic', 'core/code' ] }
						/>
					</div>
				</div>
			</div>
		);
	},
	save: () => {
		// No frontend rendering - editor only
		return null;
	}
} ); 