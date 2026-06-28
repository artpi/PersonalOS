import './index.css';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useState, useMemo } from '@wordpress/element';
import Showdown from 'showdown';
import metadata from './block.json';

// Configure showdown converter with better settings for line breaks and code blocks
const converter = new Showdown.Converter( {
	tables: true,
	strikethrough: true,
	tasklists: true,
	ghCodeBlocks: true,
	ghMentions: false,
	simpleLineBreaks: false,
	requireSpaceBeforeHeadingText: true,
	openLinksInNewWindow: true,
	backslashEscapesHTMLTags: true,
	underline: true,
	emoji: true,
	splitAdjacentBlockquotes: true,
	noHeaderId: true,
	parseImgDimensions: true,
	headerLevelStart: 1,
	smoothLivePreview: true,
} );

// Add custom extension for better line break handling
converter.addExtension(
	{
		type: 'output',
		filter( text ) {
			text = text.replace( /\n\n/g, '</p><p>' );
			if (
				! text.startsWith( '<p>' ) &&
				! text.startsWith( '<h' ) &&
				! text.startsWith( '<ul>' ) &&
				! text.startsWith( '<ol>' ) &&
				! text.startsWith( '<blockquote>' ) &&
				! text.startsWith( '<pre>' )
			) {
				text = '<p>' + text + '</p>';
			}
			text = text.replace( /<p><\/p>/g, '' );
			return text;
		},
	},
	'lineBreakHandler'
);

function MessageEdit( { attributes, setAttributes, isSelected } ) {
	const { content, role } = attributes;
	const [ isEditing, setIsEditing ] = useState( false );
	const blockProps = useBlockProps( {
		className: `message-role-${ role }`,
	} );

	const roleIcons = {
		user: '👤',
		assistant: '🤖',
		system: '⚙️',
	};

	const htmlContent = useMemo( () => {
		if ( ! content ) {
			return '';
		}
		try {
			return converter.makeHtml( content );
		} catch {
			return content.replace( /\n/g, '<br>' );
		}
	}, [ content ] );

	if ( ! isSelected && isEditing ) {
		setIsEditing( false );
	}

	return (
		<div { ...blockProps }>
			<div className="openai-message-block">
				<div className="message-header">
					<span className="message-role-icon">
						{ roleIcons[ role ] }
					</span>
					<span className="message-role-label">{ role }</span>
				</div>
				<div className="message-content">
					{ isEditing ? (
						<textarea
							className="message-editor"
							value={ content }
							onChange={ ( e ) =>
								setAttributes( { content: e.target.value } )
							}
							placeholder={ __(
								'Enter message content (markdown supported)…',
								'personal-ai-chat'
							) }
							rows={ 10 }
						/>
					) : (
						<div
							className="markdown-content"
							onClick={ () => setIsEditing( true ) }
							role="button"
							tabIndex={ 0 }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' || e.key === ' ' ) {
									setIsEditing( true );
								}
							} }
						>
							{ content ? (
								<div
									dangerouslySetInnerHTML={ {
										__html: htmlContent,
									} }
								/>
							) : (
								<div className="empty-placeholder">
									{ __(
										'Click to edit message…',
										'personal-ai-chat'
									) }
								</div>
							) }
						</div>
					) }
				</div>
			</div>
		</div>
	);
}

registerBlockType( metadata, {
	edit: MessageEdit,
	save: ( { attributes } ) => {
		const { content } = attributes;
		return <span className="ai-message-text">{ content }</span>;
	},
} );
