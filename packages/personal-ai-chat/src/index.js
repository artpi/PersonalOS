import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { render, useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import './style.css';

const emptyMessage = {
	role: 'user',
	content: '',
};

function extractMessages( content ) {
	const messages = [];
	const pattern =
		/<!-- wp:pos\/ai-message (\{.*?\}) -->([\s\S]*?)<!-- \/wp:pos\/ai-message -->/g;
	let match = pattern.exec( content || '' );

	while ( match ) {
		try {
			const attributes = JSON.parse( match[ 1 ] );
			messages.push( {
				role: attributes.role || 'user',
				content:
					attributes.content ||
					match[ 2 ].replace( /(<([^>]+)>)/gi, '' ).trim(),
			} );
		} catch {
			messages.push( {
				role: 'assistant',
				content: match[ 2 ].replace( /(<([^>]+)>)/gi, '' ).trim(),
			} );
		}

		match = pattern.exec( content || '' );
	}

	if ( messages.length === 0 && content ) {
		messages.push( {
			role: 'assistant',
			content: content.replace( /<!--[\s\S]*?-->/g, '' ).trim(),
		} );
	}

	return messages;
}

function AiChatAdmin() {
	const [ conversations, setConversations ] = useState( [] );
	const [ active, setActive ] = useState( null );
	const [ title, setTitle ] = useState( '' );
	const [ message, setMessage ] = useState( emptyMessage );
	const [ abilities, setAbilities ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const fetchConversations = useCallback( async () => {
		setLoading( true );
		try {
			const response = await apiFetch( {
				path: '/personal-ai-chat/v1/conversations',
			} );
			setConversations( response );
			if ( ! active && response.length ) {
				setActive( response[ 0 ] );
				setTitle( response[ 0 ].title );
			}
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not load conversations.', 'personal-ai-chat' ),
			} );
		} finally {
			setLoading( false );
		}
	}, [ active ] );

	useEffect( () => {
		document.body.classList.add( 'personal-ai-chat-js' );
		fetchConversations();
		apiFetch( { path: '/personal-ai-chat/v1/abilities' } )
			.then( setAbilities )
			.catch( () => setAbilities( [] ) );
	}, [ fetchConversations ] );

	async function createConversation() {
		setSaving( true );
		setNotice( null );

		try {
			const response = await apiFetch( {
				path: '/personal-ai-chat/v1/conversations',
				method: 'POST',
				data: {
					title:
						title ||
						__( 'Untitled Conversation', 'personal-ai-chat' ),
					content: '',
				},
			} );
			setActive( response );
			setTitle( response.title );
			setConversations( ( current ) => [ response, ...current ] );
			setNotice( {
				status: 'success',
				message: __( 'Conversation created.', 'personal-ai-chat' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not create conversation.', 'personal-ai-chat' ),
			} );
		} finally {
			setSaving( false );
		}
	}

	async function saveTitle() {
		if ( ! active ) {
			return;
		}

		const response = await apiFetch( {
			path: `/personal-ai-chat/v1/conversations/${ active.id }`,
			method: 'PUT',
			data: {
				title,
			},
		} );
		setActive( response );
		setConversations( ( current ) =>
			current.map( ( item ) =>
				item.id === response.id ? response : item
			)
		);
	}

	async function appendMessage( event ) {
		event.preventDefault();

		if ( ! active || ! message.content.trim() ) {
			return;
		}

		setSaving( true );
		setNotice( null );

		try {
			const isUserMessage = message.role === 'user';
			const path = `/personal-ai-chat/v1/conversations/${ active.id }/${
				isUserMessage ? 'generate' : 'messages'
			}`;
			const data = isUserMessage ? { message: message.content } : message;
			const response = await apiFetch( {
				path,
				method: 'POST',
				data,
			} );
			setActive( response );
			setMessage( emptyMessage );
			setConversations( ( current ) =>
				current.map( ( item ) =>
					item.id === response.id ? response : item
				)
			);
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not send message.', 'personal-ai-chat' ),
			} );
		} finally {
			setSaving( false );
		}
	}

	const messages = extractMessages( active?.content || '' );

	return (
		<div className="personal-ai-chat-admin__layout">
			<div
				className="personal-ai-chat-admin__sidebar"
				role="region"
				aria-label={ __( 'Conversations', 'personal-ai-chat' ) }
			>
				<div className="personal-ai-chat-admin__new">
					<TextControl
						label={ __( 'Title', 'personal-ai-chat' ) }
						value={ title }
						onChange={ setTitle }
					/>
					<Button
						variant="primary"
						type="button"
						isBusy={ saving && ! active }
						disabled={ saving }
						onClick={ createConversation }
					>
						{ __( 'New', 'personal-ai-chat' ) }
					</Button>
				</div>
				{ loading ? (
					<Spinner />
				) : (
					<div className="personal-ai-chat-admin__conversations">
						{ conversations.map( ( conversation ) => (
							<button
								key={ conversation.id }
								type="button"
								className="personal-ai-chat-admin__conversation"
								onClick={ () => {
									setActive( conversation );
									setTitle( conversation.title );
								} }
							>
								<span>{ conversation.title }</span>
								<small>{ conversation.modified_gmt }</small>
							</button>
						) ) }
					</div>
				) }
			</div>
			<div
				className="personal-ai-chat-admin__main"
				role="region"
				aria-label={ __( 'Transcript', 'personal-ai-chat' ) }
			>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }
				{ active ? (
					<>
						<div className="personal-ai-chat-admin__title">
							<TextControl
								label={ __(
									'Conversation title',
									'personal-ai-chat'
								) }
								value={ title }
								onChange={ setTitle }
							/>
							<Button
								variant="secondary"
								type="button"
								onClick={ saveTitle }
							>
								{ __( 'Save', 'personal-ai-chat' ) }
							</Button>
						</div>
						<div className="personal-ai-chat-admin__messages">
							{ messages.map( ( item, index ) => (
								<div
									key={ index }
									className={ `personal-ai-chat-admin__message is-${ item.role }` }
								>
									<strong>{ item.role }</strong>
									<p>{ item.content }</p>
								</div>
							) ) }
							{ messages.length === 0 && (
								<p>
									{ __(
										'No messages yet.',
										'personal-ai-chat'
									) }
								</p>
							) }
						</div>
						<form
							className="personal-ai-chat-admin__composer"
							onSubmit={ appendMessage }
						>
							<SelectControl
								label={ __( 'Role', 'personal-ai-chat' ) }
								value={ message.role }
								options={ [
									{ label: 'User', value: 'user' },
									{ label: 'Assistant', value: 'assistant' },
									{ label: 'System', value: 'system' },
								] }
								onChange={ ( role ) =>
									setMessage( { ...message, role } )
								}
							/>
							<TextareaControl
								label={ __( 'Message', 'personal-ai-chat' ) }
								rows={ 5 }
								value={ message.content }
								onChange={ ( content ) =>
									setMessage( { ...message, content } )
								}
							/>
							<Button
								variant="primary"
								type="submit"
								isBusy={ saving }
								disabled={ saving }
							>
								{ message.role === 'user'
									? __( 'Send', 'personal-ai-chat' )
									: __( 'Append', 'personal-ai-chat' ) }
							</Button>
						</form>
						<div className="personal-ai-chat-admin__abilities">
							{ abilities.slice( 0, 6 ).map( ( ability ) => (
								<span key={ ability.name }>
									{ ability.name }
								</span>
							) ) }
						</div>
					</>
				) : (
					<p>
						{ __(
							'Create a conversation to start.',
							'personal-ai-chat'
						) }
					</p>
				) }
			</div>
		</div>
	);
}

const mount = document.getElementById( 'personal-ai-chat-admin-app' );

if ( mount ) {
	render( <AiChatAdmin />, mount );
}
