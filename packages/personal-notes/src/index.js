import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	render,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import './style.css';

const DEFAULT_TERMS = [
	{ label: __( 'Inbox', 'personal-notes' ), value: 'inbox' },
	{ label: __( 'Now', 'personal-notes' ), value: 'now' },
	{ label: __( 'Later', 'personal-notes' ), value: 'later' },
	{ label: __( 'Follow Up', 'personal-notes' ), value: 'follow-up' },
];

const SYSTEM_TERMS = [
	'artifact',
	'note',
	'daily-note',
	'todo',
	'conversation',
	'memory',
	'skill',
	'manual',
	'synced',
	'readwise',
	'evernote',
	'ai-chat',
	'personalos',
];

const emptyDraft = {
	id: 0,
	title: '',
	content: '',
	term: 'inbox',
};

function termOptionsFromResponse( terms ) {
	const options = terms
		.filter( ( term ) => ! SYSTEM_TERMS.includes( term.slug ) )
		.map( ( term ) => ( {
			label: term.parent_slug
				? `${ term.name } (${ term.parent_slug })`
				: term.name,
			value: term.slug,
		} ) );

	return options.length ? options : DEFAULT_TERMS;
}

function pickEditableTerm( termSlugs, termOptions ) {
	const optionSlugs = termOptions.map( ( option ) => option.value );
	const match = termSlugs.find( ( slug ) => optionSlugs.includes( slug ) );

	return match || 'inbox';
}

function NotesAdmin() {
	const [ notes, setNotes ] = useState( [] );
	const [ terms, setTerms ] = useState( DEFAULT_TERMS );
	const [ draft, setDraft ] = useState( emptyDraft );
	const [ filter, setFilter ] = useState( 'all' );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const filterOptions = useMemo(
		() => [
			{ label: __( 'All', 'personal-notes' ), value: 'all' },
			...terms,
		],
		[ terms ]
	);

	const fetchTerms = useCallback( async () => {
		const response = await apiFetch( { path: '/personal-notes/v1/terms' } );
		setTerms( termOptionsFromResponse( response ) );
	}, [] );

	const fetchNotes = useCallback( async () => {
		setLoading( true );
		try {
			const query = {};
			if ( filter !== 'all' ) {
				query.term = filter;
			}
			if ( search.trim() ) {
				query.search = search.trim();
			}

			const response = await apiFetch( {
				path: addQueryArgs( '/personal-notes/v1/notes', query ),
			} );

			setNotes( response );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not load notes.', 'personal-notes' ),
			} );
		} finally {
			setLoading( false );
		}
	}, [ filter, search ] );

	useEffect( () => {
		document.body.classList.add( 'personal-notes-js' );
		fetchTerms().catch( ( error ) => {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not load terms.', 'personal-notes' ),
			} );
		} );
	}, [ fetchTerms ] );

	useEffect( () => {
		fetchNotes();
	}, [ fetchNotes ] );

	async function saveNote( event ) {
		event.preventDefault();
		setSaving( true );
		setNotice( null );

		try {
			const path = draft.id
				? `/personal-notes/v1/notes/${ draft.id }`
				: '/personal-notes/v1/notes';
			const method = draft.id ? 'PUT' : 'POST';
			const response = await apiFetch( {
				path,
				method,
				data: {
					title: draft.title,
					content: draft.content,
					terms: [ draft.term ],
				},
			} );

			setDraft( emptyDraft );
			setNotice( {
				status: 'success',
				message: draft.id
					? __( 'Note saved.', 'personal-notes' )
					: __( 'Note created.', 'personal-notes' ),
			} );
			setNotes( ( current ) => {
				const withoutSaved = current.filter(
					( note ) => note.id !== response.id
				);
				return [ response, ...withoutSaved ];
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not save note.', 'personal-notes' ),
			} );
		} finally {
			setSaving( false );
		}
	}

	function editNote( note ) {
		setDraft( {
			id: note.id,
			title: note.title,
			content: note.content,
			term: pickEditableTerm( note.terms, terms ),
		} );
	}

	return (
		<div className="personal-notes-admin__layout">
			<div
				className="personal-notes-admin__editor"
				role="region"
				aria-label={ __( 'Note editor', 'personal-notes' ) }
			>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }
				<form onSubmit={ saveNote }>
					<TextControl
						label={ __( 'Title', 'personal-notes' ) }
						value={ draft.title }
						onChange={ ( title ) =>
							setDraft( { ...draft, title } )
						}
					/>
					<TextareaControl
						label={ __( 'Content', 'personal-notes' ) }
						rows={ 9 }
						value={ draft.content }
						onChange={ ( content ) =>
							setDraft( { ...draft, content } )
						}
					/>
					<SelectControl
						label={ __( 'Collection', 'personal-notes' ) }
						value={ draft.term }
						options={ terms }
						onChange={ ( term ) => setDraft( { ...draft, term } ) }
					/>
					<div className="personal-notes-admin__actions">
						<Button
							variant="primary"
							type="submit"
							isBusy={ saving }
							disabled={ saving }
						>
							{ draft.id
								? __( 'Save', 'personal-notes' )
								: __( 'Create', 'personal-notes' ) }
						</Button>
						{ draft.id > 0 && (
							<Button
								variant="tertiary"
								type="button"
								onClick={ () => setDraft( emptyDraft ) }
							>
								{ __( 'Cancel', 'personal-notes' ) }
							</Button>
						) }
					</div>
				</form>
			</div>
			<div
				className="personal-notes-admin__list"
				role="region"
				aria-label={ __( 'Notes list', 'personal-notes' ) }
			>
				<div className="personal-notes-admin__filters">
					<TextControl
						label={ __( 'Search', 'personal-notes' ) }
						value={ search }
						onChange={ setSearch }
					/>
					<SelectControl
						label={ __( 'Filter', 'personal-notes' ) }
						value={ filter }
						options={ filterOptions }
						onChange={ setFilter }
					/>
				</div>
				{ loading ? (
					<Spinner />
				) : (
					<div className="personal-notes-admin__items">
						{ notes.map( ( note ) => (
							<button
								className="personal-notes-admin__item"
								key={ note.id }
								type="button"
								onClick={ () => editNote( note ) }
							>
								<span className="personal-notes-admin__item-title">
									{ note.title }
								</span>
								<span className="personal-notes-admin__item-meta">
									{ note.terms.join( ', ' ) }
								</span>
							</button>
						) ) }
						{ notes.length === 0 && (
							<p>{ __( 'No notes found.', 'personal-notes' ) }</p>
						) }
					</div>
				) }
			</div>
		</div>
	);
}

const mount = document.getElementById( 'personal-notes-admin-app' );

if ( mount ) {
	render( <NotesAdmin />, mount );
}
