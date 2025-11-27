import './index.css';
import { registerBlockType } from '@wordpress/blocks';
import { 
	InspectorControls,
	useBlockProps 
} from '@wordpress/block-editor';
import { 
	PanelBody, 
	SelectControl, 
	TextControl 
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import Showdown from 'showdown';
import metadata from './block.json';

// Configure showdown converter with better settings for line breaks and code blocks
const converter = new Showdown.Converter({
	tables: true,
	strikethrough: true,
	tasklists: true,
	ghCodeBlocks: true,
	ghMentions: false,
	simpleLineBreaks: false, // Use false for better paragraph handling
	requireSpaceBeforeHeadingText: true,
	openLinksInNewWindow: true,
	backslashEscapesHTMLTags: true,
	underline: true,
	emoji: true,
	splitAdjacentBlockquotes: true,
	noHeaderId: true, // Disable header IDs for cleaner HTML
	parseImgDimensions: true,
	headerLevelStart: 1,
	smoothLivePreview: true
});

// Add custom extension for better line break handling
converter.addExtension({
	type: 'output',
	filter: function (text) {
		// Convert double line breaks to paragraphs properly
		text = text.replace(/\n\n/g, '</p><p>');
		// Wrap content in paragraph tags if not already wrapped
		if (!text.startsWith('<p>') && !text.startsWith('<h') && !text.startsWith('<ul>') && !text.startsWith('<ol>') && !text.startsWith('<blockquote>') && !text.startsWith('<pre>')) {
			text = '<p>' + text + '</p>';
		}
		// Clean up empty paragraphs
		text = text.replace(/<p><\/p>/g, '');
		return text;
	}
}, 'lineBreakHandler');

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

		// Convert markdown to HTML using showdown
		const htmlContent = useMemo( () => {
			if ( ! content ) {
				return '';
			}
			try {
				// Convert escaped newlines back to actual newlines for markdown rendering
				const unescapedContent = content.replace( /\\n/g, '\n' ).replace( /\\r/g, '\r' );
				return converter.makeHtml( unescapedContent );
			} catch ( error ) {
				console.warn( 'Markdown parsing error:', error );
				// Fallback to plain text with basic formatting
				return content.replace( /\\n/g, '<br>' ).replace( /\n/g, '<br>' );
			}
		}, [ content ] );

		console.log( 'HTML', htmlContent );
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
						{ content ? (
							<div 
								className="markdown-content"
								dangerouslySetInnerHTML={ { __html: htmlContent } }
							/>
						) : (
							<div className="empty-placeholder">
								{ __( 'No content (populated via API)', 'personalos' ) }
							</div>
						) }
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