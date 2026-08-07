/**
 * WordPress dependencies
 */

import {
	useBlockProps,
	InspectorControls,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import {
	TextareaControl,
	Button,
	Panel,
	PanelBody,
	Spinner,
} from '@wordpress/components';
import { getBlockContent, rawHandler } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './index.css';

const systemPromptDefault = `You are a fantastic Book Summarizer, functioning as an adept editor, guiding users in creating the book summaries they desire for their blog posts, specifically for publication on a blog.
The summary you generate readers' memories by emphasizing notes and highlights that resonated.
When provided, follow and extend outlines as necessary. 
Limit interactions, only return summaries ready to post
summaries balance clarity, conciseness, and humor. 

Try to start with recapping the book structure in bullet points.
Please use some headings and formattting through your summary.

Do not refer to "this post". Talk about the book and its subject.
Do not offer recommendations or superlatives about the book itself. 
Please use simple language and simple words.
Please output simple HTML.`;

const Edit = ( props ) => {
	const blockProps = useBlockProps();
	const { children, ...innerBlocksProps } = useInnerBlocksProps();

	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	const [ prompt, setPrompt ] = useState( '' );
	const [ systemPrompt, setSystemPrompt ] = useState( systemPromptDefault );
	const [ generating, setGenerating ] = useState( false );
	const [ error, setError ] = useState( '' );
	const { postTitle, highlights, postContent } = useSelect( ( select ) => {
		const blocks = select( 'core/block-editor' ).getBlocks();
		return {
			postTitle:
				select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			postContent: blocks
				.filter(
					( block ) =>
						block.name !== 'pos/readwise' &&
						block.name !== 'pos/book-summary'
				)
				.map( ( block ) => getBlockContent( block ) )
				.join( '\n' ),
			highlights: blocks
				.filter( ( block ) => block.name === 'pos/readwise' )
				.map( ( block ) => block?.attributes?.content )
				.filter( Boolean )
				.join( '\n-------\n' ),
		};
	} );

	useEffect( () => {
		let promptText = `Please write a summary of a book titled "${ postTitle }"\n\n`;
		if ( postContent.length > 5 ) {
			promptText += `Here is a draft of the summary that I already started: \n\n${ postContent }\n\n`;
		}
		promptText += `Here are the highlights from the book that I have selected based on their importance to what I want to convey in my summary:\n-----\n${ highlights }\n`;
		setPrompt( promptText );
	}, [ postTitle, highlights, postContent ] );

	function generateSummary() {
		setGenerating( true );
		setError( '' );
		apiFetch( {
			path: '/personal-readwise-sync/v1/book-summary',
			method: 'POST',
			data: {
				prompt,
				system_prompt: systemPrompt,
			},
		} )
			.then( ( data ) => {
				const blocks = rawHandler( {
					HTML: data.summary || '',
				} );
				replaceInnerBlocks( props?.clientId, blocks );
				setGenerating( false );
			} )
			.catch( ( fetchError ) => {
				setError(
					fetchError.message ||
						__(
							'Could not generate summary.',
							'personal-readwise-sync'
						)
				);
				setGenerating( false );
			} );
	}

	return (
		<div { ...blockProps }>
			<Panel className="pos-book-summary__panel">
				<TextareaControl
					label={ __(
						'Generate a book summary with a prompt',
						'personal-readwise-sync'
					) }
					onChange={ ( newContent ) => setPrompt( newContent ) }
					value={ prompt }
					disabled={ generating }
				></TextareaControl>
				<Button
					variant="primary"
					disabled={ generating }
					onClick={ generateSummary }
				>
					{ __( 'Generate Summary', 'personal-readwise-sync' ) }
				</Button>
				{ generating && <Spinner /> }
				{ error && (
					<p className="pos-book-summary__error">{ error }</p>
				) }
			</Panel>
			<div { ...innerBlocksProps }>{ children }</div>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Book Summarizer Config',
						'personal-readwise-sync'
					) }
				>
					<TextareaControl
						label={ __(
							'System Prompt',
							'personal-readwise-sync'
						) }
						value={ systemPrompt }
						onChange={ ( newContent ) =>
							setSystemPrompt( newContent )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</div>
	);
};
export default Edit;
