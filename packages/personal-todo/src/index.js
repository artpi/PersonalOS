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

const STATUS_OPTIONS = [
	{ label: __( 'Inbox', 'personal-todo' ), value: 'inbox' },
	{ label: __( 'Now', 'personal-todo' ), value: 'now' },
	{ label: __( 'Later', 'personal-todo' ), value: 'later' },
	{ label: __( 'Follow Up', 'personal-todo' ), value: 'follow-up' },
];

const emptyDraft = {
	id: 0,
	title: '',
	excerpt: '',
	term: 'inbox',
	recurringDays: 0,
};

function pickStatusTerm( termSlugs ) {
	const optionSlugs = STATUS_OPTIONS.map( ( option ) => option.value );
	const match = termSlugs.find( ( slug ) => optionSlugs.includes( slug ) );

	return match || 'inbox';
}

function TodoAdmin() {
	const [ tasks, setTasks ] = useState( [] );
	const [ draft, setDraft ] = useState( emptyDraft );
	const [ filter, setFilter ] = useState( 'all' );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const filterOptions = useMemo(
		() => [
			{ label: __( 'All', 'personal-todo' ), value: 'all' },
			...STATUS_OPTIONS,
		],
		[]
	);

	const fetchTasks = useCallback( async () => {
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
				path: addQueryArgs( '/personal-todo/v1/tasks', query ),
			} );

			setTasks( response );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not load tasks.', 'personal-todo' ),
			} );
		} finally {
			setLoading( false );
		}
	}, [ filter, search ] );

	useEffect( () => {
		document.body.classList.add( 'personal-todo-js' );
	}, [] );

	useEffect( () => {
		fetchTasks();
	}, [ fetchTasks ] );

	async function saveTask( event ) {
		event.preventDefault();
		setSaving( true );
		setNotice( null );

		try {
			const path = draft.id
				? `/personal-todo/v1/tasks/${ draft.id }`
				: '/personal-todo/v1/tasks';
			const method = draft.id ? 'PUT' : 'POST';
			const response = await apiFetch( {
				path,
				method,
				data: {
					title: draft.title,
					excerpt: draft.excerpt,
					terms: [ draft.term ],
					pos_recurring_days:
						parseInt( draft.recurringDays, 10 ) || 0,
				},
			} );

			setDraft( emptyDraft );
			setNotice( {
				status: 'success',
				message: draft.id
					? __( 'Task saved.', 'personal-todo' )
					: __( 'Task created.', 'personal-todo' ),
			} );
			setTasks( ( current ) => {
				const withoutSaved = current.filter(
					( task ) => task.id !== response.id
				);
				return [ response, ...withoutSaved ];
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not save task.', 'personal-todo' ),
			} );
		} finally {
			setSaving( false );
		}
	}

	async function completeTask( task ) {
		setNotice( null );
		try {
			await apiFetch( {
				path: `/personal-todo/v1/tasks/${ task.id }/complete`,
				method: 'POST',
			} );
			setTasks( ( current ) =>
				current.filter( ( item ) => item.id !== task.id )
			);
			setNotice( {
				status: 'success',
				message: __( 'Task completed.', 'personal-todo' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not complete task.', 'personal-todo' ),
			} );
		}
	}

	function editTask( task ) {
		setDraft( {
			id: task.id,
			title: task.title,
			excerpt: task.excerpt,
			term: pickStatusTerm( task.terms ),
			recurringDays: task.pos_recurring_days,
		} );
	}

	return (
		<div className="personal-todo-admin__layout">
			<div
				className="personal-todo-admin__editor"
				role="region"
				aria-label={ __( 'Task editor', 'personal-todo' ) }
			>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }
				<form onSubmit={ saveTask }>
					<TextControl
						label={ __( 'Task', 'personal-todo' ) }
						value={ draft.title }
						onChange={ ( title ) =>
							setDraft( { ...draft, title } )
						}
					/>
					<TextareaControl
						label={ __( 'Notes', 'personal-todo' ) }
						rows={ 4 }
						value={ draft.excerpt }
						onChange={ ( excerpt ) =>
							setDraft( { ...draft, excerpt } )
						}
					/>
					<SelectControl
						label={ __( 'Status', 'personal-todo' ) }
						value={ draft.term }
						options={ STATUS_OPTIONS }
						onChange={ ( term ) => setDraft( { ...draft, term } ) }
					/>
					<TextControl
						label={ __( 'Repeat days', 'personal-todo' ) }
						type="number"
						min="0"
						value={ String( draft.recurringDays ) }
						onChange={ ( recurringDays ) =>
							setDraft( { ...draft, recurringDays } )
						}
					/>
					<div className="personal-todo-admin__actions">
						<Button
							variant="primary"
							type="submit"
							isBusy={ saving }
							disabled={ saving }
						>
							{ draft.id
								? __( 'Save', 'personal-todo' )
								: __( 'Add', 'personal-todo' ) }
						</Button>
						{ draft.id > 0 && (
							<Button
								variant="tertiary"
								type="button"
								onClick={ () => setDraft( emptyDraft ) }
							>
								{ __( 'Cancel', 'personal-todo' ) }
							</Button>
						) }
					</div>
				</form>
			</div>
			<div
				className="personal-todo-admin__list"
				role="region"
				aria-label={ __( 'Task list', 'personal-todo' ) }
			>
				<div className="personal-todo-admin__filters">
					<TextControl
						label={ __( 'Search', 'personal-todo' ) }
						value={ search }
						onChange={ setSearch }
					/>
					<SelectControl
						label={ __( 'Filter', 'personal-todo' ) }
						value={ filter }
						options={ filterOptions }
						onChange={ setFilter }
					/>
				</div>
				{ loading ? (
					<Spinner />
				) : (
					<div className="personal-todo-admin__items">
						{ tasks.map( ( task ) => (
							<div
								className="personal-todo-admin__item"
								key={ task.id }
							>
								<button
									className="personal-todo-admin__item-main"
									type="button"
									onClick={ () => editTask( task ) }
								>
									<span className="personal-todo-admin__item-title">
										{ task.title }
									</span>
									<span className="personal-todo-admin__item-meta">
										{ task.terms.join( ', ' ) }
									</span>
								</button>
								<Button
									variant="secondary"
									type="button"
									onClick={ () => completeTask( task ) }
								>
									{ __( 'Complete', 'personal-todo' ) }
								</Button>
							</div>
						) ) }
						{ tasks.length === 0 && (
							<p>{ __( 'No tasks found.', 'personal-todo' ) }</p>
						) }
					</div>
				) }
			</div>
		</div>
	);
}

const mount = document.getElementById( 'personal-todo-admin-app' );

if ( mount ) {
	render( <TodoAdmin />, mount );
}
