import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ComboboxControl,
	DateTimePicker,
	FormTokenField,
	Icon,
	Modal,
	Notice,
	Panel,
	PanelBody,
	PanelRow,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import {
	createRoot,
	RawHTML,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	check,
	edit,
	external,
	notAllowed,
	pending,
	rotateLeft,
	scheduled,
} from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

import './style.css';

const settings = window.personalTodoSettings || {};

const DEFAULT_VIEW = {
	type: 'list',
	search: '',
	page: 1,
	perPage: 20,
	titleField: 'title',
	descriptionField: 'description',
	fields: [ 'flags', 'knowledgeTypes' ],
	filters: [],
	sort: { field: 'modified', direction: 'desc' },
	layout: {},
};

const DEFAULT_LAYOUTS = {
	list: { layout: {} },
	table: {
		layout: {
			density: 'balanced',
			styles: { modified: { width: '150px' } },
		},
	},
	grid: {
		layout: {
			badgeFields: [ 'flags', 'knowledgeTypes' ],
			previewSize: 260,
		},
	},
};

const STATUS_TERMS = [ 'inbox', 'now', 'later', 'follow-up' ];
const SYSTEM_TERMS = [
	'artifact',
	'todo',
	'note',
	'daily-note',
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
const CONTAINER_TERMS = [ 'status', 'project', 'area', 'resource', 'archive' ];

const EMPTY_DRAFT = {
	id: 0,
	title: '',
	excerpt: '',
	url: '',
	terms: [ 'inbox' ],
	recurringDays: 0,
	blockedBy: 0,
	pendingTerm: 'now',
	scheduledFor: '',
};

async function fetchAllTasks() {
	const tasks = [];
	let page = 1;

	while ( true ) {
		const response = await apiFetch( {
			path: addQueryArgs( '/personal-todo/v1/tasks', {
				page,
				per_page: 100,
				status: [ 'private', 'publish', 'future' ],
			} ),
		} );
		tasks.push( ...response );
		if ( response.length < 100 ) {
			return tasks;
		}
		page += 1;
	}
}

async function fetchAllTerms() {
	const firstResponse = await apiFetch( {
		path: addQueryArgs( settings.taxonomyRestPath, {
			context: 'view',
			hide_empty: false,
			page: 1,
			per_page: 100,
		} ),
		parse: false,
	} );
	const firstPage = await firstResponse.json();
	const totalPages = Number(
		firstResponse.headers.get( 'X-WP-TotalPages' ) || 1
	);

	if ( totalPages === 1 ) {
		return firstPage;
	}

	const remainingPages = await Promise.all(
		Array.from( { length: totalPages - 1 }, ( value, index ) =>
			apiFetch( {
				path: addQueryArgs( settings.taxonomyRestPath, {
					context: 'view',
					hide_empty: false,
					page: index + 2,
					per_page: 100,
				} ),
			} )
		)
	);

	return firstPage.concat( ...remainingPages );
}

function getFlags( task ) {
	return [
		task.scheduled && 'scheduled',
		task.pos_blocked_by > 0 && 'blocked',
		task.pos_recurring_days > 0 && 'recurring',
		task.blocking?.length > 0 && 'blocking',
		task.url && 'url',
	].filter( Boolean );
}

function draftFromTask( task ) {
	return {
		id: task.id,
		title: task.title,
		excerpt: task.excerpt,
		url: task.url || '',
		terms: task.terms.filter(
			( slug ) =>
				! SYSTEM_TERMS.includes( slug ) &&
				! CONTAINER_TERMS.includes( slug )
		),
		recurringDays: task.pos_recurring_days || 0,
		blockedBy: task.pos_blocked_by || 0,
		pendingTerm: task.pos_blocked_pending_term || 'now',
		scheduledFor: task.scheduled || '',
	};
}

function TaskForm( {
	draft,
	onChange,
	onSave,
	onCancel,
	saving,
	tasks,
	terms,
	full = false,
} ) {
	const expanded = full || draft.title.length > 0;
	const assignableTerms = terms.filter(
		( term ) =>
			! SYSTEM_TERMS.includes( term.slug ) &&
			! CONTAINER_TERMS.includes( term.slug )
	);
	const termsByName = new Map(
		assignableTerms.map( ( term ) => [ term.name, term ] )
	);
	const chosenNames = draft.terms
		.map( ( slug ) =>
			assignableTerms.find( ( term ) => term.slug === slug )
		)
		.filter( Boolean )
		.map( ( term ) => term.name );
	const destinationOptions = assignableTerms
		.filter( ( term ) => STATUS_TERMS.includes( term.slug ) )
		.map( ( term ) => ( { label: term.name, value: term.slug } ) );

	return (
		<form className="personal-todo-form" onSubmit={ onSave }>
			<TextControl
				label={ expanded ? __( 'Task', 'personal-todo' ) : undefined }
				placeholder={ __( 'New TODO in Inbox', 'personal-todo' ) }
				value={ draft.title }
				onChange={ ( title ) => onChange( { ...draft, title } ) }
				__nextHasNoMarginBottom
			/>
			{ expanded && (
				<>
					<div className="personal-todo-form__main">
						<TextareaControl
							label={ __( 'Notes', 'personal-todo' ) }
							value={ draft.excerpt }
							onChange={ ( excerpt ) =>
								onChange( { ...draft, excerpt } )
							}
							rows={ 3 }
						/>
						<TextControl
							label={ __( 'Action URL', 'personal-todo' ) }
							type="url"
							value={ draft.url }
							onChange={ ( url ) =>
								onChange( { ...draft, url } )
							}
							placeholder="https://..."
						/>
						<FormTokenField
							label={ __( 'Knowledge Type', 'personal-todo' ) }
							value={ chosenNames }
							suggestions={ assignableTerms.map(
								( term ) => term.name
							) }
							onChange={ ( names ) =>
								onChange( {
									...draft,
									terms: names
										.map(
											( name ) =>
												termsByName.get( name )?.slug
										)
										.filter( Boolean ),
								} )
							}
						/>
					</div>
					<Panel className="personal-todo-form__rules">
						<PanelBody
							title={ __( 'Schedule task', 'personal-todo' ) }
							initialOpen={ Boolean( draft.scheduledFor ) }
						>
							<PanelRow>
								<DateTimePicker
									currentDate={
										draft.scheduledFor || undefined
									}
									onChange={ ( scheduledFor ) =>
										onChange( { ...draft, scheduledFor } )
									}
								/>
							</PanelRow>
							{ draft.scheduledFor && (
								<Button
									variant="tertiary"
									onClick={ () =>
										onChange( {
											...draft,
											scheduledFor: '',
										} )
									}
								>
									{ __( 'Clear schedule', 'personal-todo' ) }
								</Button>
							) }
						</PanelBody>
						<PanelBody
							title={ __( 'Recurring', 'personal-todo' ) }
							initialOpen={ draft.recurringDays > 0 }
						>
							<PanelRow className="personal-todo-form__wide-row">
								<TextControl
									label={ __(
										'Repeat after days',
										'personal-todo'
									) }
									type="number"
									min="0"
									value={ String( draft.recurringDays ) }
									onChange={ ( recurringDays ) =>
										onChange( { ...draft, recurringDays } )
									}
								/>
							</PanelRow>
						</PanelBody>
						<PanelBody
							title={ __(
								'This TODO depends on',
								'personal-todo'
							) }
							initialOpen={ draft.blockedBy > 0 }
						>
							<PanelRow className="personal-todo-form__wide-row">
								<ComboboxControl
									label={ __(
										'Blocked by',
										'personal-todo'
									) }
									value={ draft.blockedBy || '' }
									options={ tasks
										.filter(
											( task ) => task.id !== draft.id
										)
										.map( ( task ) => ( {
											label: `#${ task.id } ${ task.title }`,
											value: task.id,
										} ) ) }
									onChange={ ( blockedBy ) =>
										onChange( {
											...draft,
											blockedBy: Number( blockedBy ) || 0,
										} )
									}
								/>
							</PanelRow>
						</PanelBody>
					</Panel>
					{ ( draft.scheduledFor ||
						draft.recurringDays > 0 ||
						draft.blockedBy > 0 ) && (
						<SelectControl
							label={ __(
								'When ready, move to',
								'personal-todo'
							) }
							value={ draft.pendingTerm }
							options={ destinationOptions }
							onChange={ ( pendingTerm ) =>
								onChange( { ...draft, pendingTerm } )
							}
						/>
					) }
					<div className="personal-todo-form__footer">
						{ onCancel && (
							<Button variant="tertiary" onClick={ onCancel }>
								{ __( 'Cancel', 'personal-todo' ) }
							</Button>
						) }
						<Button
							variant="primary"
							type="submit"
							icon={ check }
							isBusy={ saving }
							disabled={ saving || ! draft.title.trim() }
						>
							{ draft.id
								? __( 'Save', 'personal-todo' )
								: __( 'Add task', 'personal-todo' ) }
						</Button>
					</div>
				</>
			) }
		</form>
	);
}

function TodoAdmin() {
	const [ tasks, setTasks ] = useState( [] );
	const [ terms, setTerms ] = useState( [] );
	const [ draft, setDraft ] = useState( EMPTY_DRAFT );
	const [ editedTask, setEditedTask ] = useState( null );
	const [ editDraft, setEditDraft ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const assignableTerms = useMemo(
		() =>
			terms.filter(
				( term ) =>
					! SYSTEM_TERMS.includes( term.slug ) &&
					! CONTAINER_TERMS.includes( term.slug )
			),
		[ terms ]
	);
	const termsBySlug = useMemo(
		() => new Map( terms.map( ( term ) => [ term.slug, term ] ) ),
		[ terms ]
	);

	useEffect( () => {
		document.body.classList.add( 'personal-todo-js' );
		Promise.all( [ fetchAllTasks(), fetchAllTerms() ] )
			.then( ( [ fetchedTasks, fetchedTerms ] ) => {
				setTasks( fetchedTasks );
				setTerms( fetchedTerms );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					message:
						error.message ||
						__( 'Could not load tasks.', 'personal-todo' ),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	const termNames = useCallback(
		( task ) =>
			task.terms
				.map( ( slug ) => termsBySlug.get( slug ) )
				.filter(
					( term ) =>
						term &&
						! SYSTEM_TERMS.includes( term.slug ) &&
						! CONTAINER_TERMS.includes( term.slug )
				)
				.map( ( term ) => term.name ),
		[ termsBySlug ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Title', 'personal-todo' ),
				type: 'text',
				enableHiding: false,
				enableSorting: true,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.title,
			},
			{
				id: 'description',
				label: __( 'Notes', 'personal-todo' ),
				type: 'text',
				enableSorting: false,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.excerpt,
				render: ( { item } ) => (
					<span className="personal-todo-admin__description">
						{ item.excerpt }
					</span>
				),
			},
			{
				id: 'flags',
				label: __( 'Flags', 'personal-todo' ),
				type: 'text',
				enableSorting: false,
				elements: [
					{
						label: __( 'Scheduled', 'personal-todo' ),
						value: 'scheduled',
					},
					{
						label: __( 'Blocked', 'personal-todo' ),
						value: 'blocked',
					},
					{
						label: __( 'Recurring', 'personal-todo' ),
						value: 'recurring',
					},
					{
						label: __( 'Blocking', 'personal-todo' ),
						value: 'blocking',
					},
					{ label: __( 'Has URL', 'personal-todo' ), value: 'url' },
				],
				filterBy: { operators: [ 'isAny', 'isNone' ], isPrimary: true },
				getValue: ( { item } ) => getFlags( item ),
				render: ( { item } ) => (
					<span className="personal-todo-admin__badges">
						{ item.scheduled && (
							<span className="personal-todo-admin__badge">
								<Icon icon={ scheduled } />
								{ sprintf(
									/* translators: %s: scheduled date */
									__( 'Scheduled %s', 'personal-todo' ),
									new Date(
										item.scheduled
									).toLocaleDateString()
								) }
							</span>
						) }
						{ item.pos_blocked_by > 0 && (
							<span className="personal-todo-admin__badge">
								<Icon icon={ pending } />
								{ sprintf(
									/* translators: %d: blocking task ID */
									__( 'After #%d', 'personal-todo' ),
									item.pos_blocked_by
								) }
							</span>
						) }
						{ item.pos_recurring_days > 0 && (
							<span className="personal-todo-admin__badge">
								<Icon icon={ rotateLeft } />
								{ sprintf(
									/* translators: %d: recurrence interval in days */
									__( 'Every %d days', 'personal-todo' ),
									item.pos_recurring_days
								) }
							</span>
						) }
						{ item.blocking?.length > 0 && (
							<span className="personal-todo-admin__badge">
								<Icon icon={ notAllowed } />
								{ sprintf(
									/* translators: %d: number of tasks blocked */
									__( 'Blocking %d', 'personal-todo' ),
									item.blocking.length
								) }
							</span>
						) }
					</span>
				),
			},
			{
				id: 'knowledgeTypes',
				label: __( 'Knowledge Type', 'personal-todo' ),
				type: 'text',
				enableSorting: false,
				elements: assignableTerms.map( ( term ) => ( {
					label: term.name,
					value: term.slug,
				} ) ),
				filterBy: { operators: [ 'isAny', 'isAll' ], isPrimary: true },
				getValue: ( { item } ) =>
					item.terms.filter( ( slug ) => termsBySlug.has( slug ) ),
				render: ( { item } ) => termNames( item ).join( ', ' ),
			},
			{
				id: 'modified',
				label: __( 'Modified', 'personal-todo' ),
				type: 'datetime',
				enableSorting: true,
				getValue: ( { item } ) => `${ item.modified_gmt }Z`,
				render: ( { item } ) =>
					new Intl.DateTimeFormat( undefined, {
						dateStyle: 'medium',
					} ).format( new Date( `${ item.modified_gmt }Z` ) ),
			},
		],
		[ assignableTerms, termNames, termsBySlug ]
	);

	const { data: shownTasks, paginationInfo } = useMemo(
		() => filterSortAndPaginate( tasks, view, fields ),
		[ fields, tasks, view ]
	);

	async function saveTask( event, taskDraft = draft ) {
		event?.preventDefault();
		setSaving( true );
		setNotice( null );
		try {
			const task = await apiFetch( {
				path: taskDraft.id
					? `/personal-todo/v1/tasks/${ taskDraft.id }`
					: '/personal-todo/v1/tasks',
				method: taskDraft.id ? 'PUT' : 'POST',
				data: {
					title: taskDraft.title.trim(),
					excerpt: taskDraft.excerpt,
					url: taskDraft.url,
					terms: taskDraft.terms.length
						? taskDraft.terms
						: [ 'inbox' ],
					pos_recurring_days:
						parseInt( taskDraft.recurringDays, 10 ) || 0,
					pos_blocked_by: Number( taskDraft.blockedBy ) || 0,
					pos_blocked_pending_term: taskDraft.pendingTerm || 'now',
					scheduled_for: taskDraft.scheduledFor || '',
				},
			} );

			setTasks( ( current ) => [
				task,
				...current.filter( ( item ) => item.id !== task.id ),
			] );
			setDraft( EMPTY_DRAFT );
			setEditedTask( null );
			setEditDraft( null );
			setNotice( {
				status: 'success',
				message: taskDraft.id
					? __( 'Task saved.', 'personal-todo' )
					: __( 'Task created.', 'personal-todo' ),
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

	async function completeTasks( items, stopRecurring = false ) {
		setNotice( null );
		try {
			await Promise.all(
				items.map( async ( item ) => {
					if ( stopRecurring ) {
						await apiFetch( {
							path: `/personal-todo/v1/tasks/${ item.id }`,
							method: 'PUT',
							data: { pos_recurring_days: 0 },
						} );
					}
					return apiFetch( {
						path: `/personal-todo/v1/tasks/${ item.id }/complete`,
						method: 'POST',
					} );
				} )
			);
			const completedIds = new Set( items.map( ( item ) => item.id ) );
			setTasks( ( current ) =>
				current.filter( ( item ) => ! completedIds.has( item.id ) )
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

	async function moveTasks( items, destination ) {
		try {
			const updates = await Promise.all(
				items.map( ( item ) =>
					apiFetch( {
						path: `/personal-todo/v1/tasks/${ item.id }`,
						method: 'PUT',
						data: {
							terms: [
								destination,
								...item.terms.filter(
									( slug ) =>
										! STATUS_TERMS.includes( slug ) &&
										! SYSTEM_TERMS.includes( slug )
								),
							],
						},
					} )
				)
			);
			const updatesById = new Map(
				updates.map( ( item ) => [ item.id, item ] )
			);
			setTasks( ( current ) =>
				current.map( ( item ) => updatesById.get( item.id ) || item )
			);
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not move task.', 'personal-todo' ),
			} );
		}
	}

	function openEditor( task ) {
		setEditedTask( task );
		setEditDraft( draftFromTask( task ) );
	}

	const actions = [
		{
			id: 'complete',
			label: __( 'Complete', 'personal-todo' ),
			icon: check,
			isPrimary: true,
			callback: ( items ) => completeTasks( items ),
		},
		{
			id: 'edit',
			label: __( 'Edit', 'personal-todo' ),
			icon: edit,
			callback: ( items ) => openEditor( items[ 0 ] ),
		},
		{
			id: 'open-url',
			label: __( 'Open URL', 'personal-todo' ),
			icon: external,
			isEligible: ( item ) => Boolean( item.url ),
			callback: ( items ) => window.open( items[ 0 ].url, '_blank' ),
		},
		{
			id: 'stop-recurring',
			label: __( 'Complete and stop recurring', 'personal-todo' ),
			icon: check,
			isEligible: ( item ) => item.pos_recurring_days > 0,
			callback: ( items ) => completeTasks( items, true ),
		},
		...STATUS_TERMS.filter( ( slug ) => termsBySlug.has( slug ) ).map(
			( slug ) => ( {
				id: `move-${ slug }`,
				label: sprintf(
					/* translators: %s: Knowledge type name */
					__( 'Move to %s', 'personal-todo' ),
					termsBySlug.get( slug ).name
				),
				callback: ( items ) => moveTasks( items, slug ),
			} )
		),
	];

	return (
		<div className="personal-todo-admin__layout">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<div className="personal-todo-admin__quick-add">
				<TaskForm
					draft={ draft }
					onChange={ setDraft }
					onSave={ saveTask }
					saving={ saving }
					tasks={ tasks }
					terms={ terms }
				/>
			</div>
			<div className="personal-todo-admin__dataviews">
				{ loading ? (
					<div className="personal-todo-admin__loading">
						<Spinner />
					</div>
				) : (
					<DataViews
						data={ shownTasks }
						fields={ fields }
						view={ view }
						onChangeView={ setView }
						getItemId={ ( item ) => item.id.toString() }
						paginationInfo={ paginationInfo }
						actions={ actions }
						defaultLayouts={ DEFAULT_LAYOUTS }
					/>
				) }
			</div>
			{ editedTask && editDraft && (
				<Modal
					title={ sprintf(
						/* translators: %s: task title */
						__( 'Edit %s', 'personal-todo' ),
						editedTask.title
					) }
					onRequestClose={ () => setEditedTask( null ) }
					className="personal-todo-admin__modal"
				>
					<TaskForm
						draft={ editDraft }
						onChange={ setEditDraft }
						onSave={ ( event ) => saveTask( event, editDraft ) }
						onCancel={ () => setEditedTask( null ) }
						saving={ saving }
						tasks={ tasks }
						terms={ terms }
						full
					/>
					{ editedTask.history?.length > 0 && (
						<section className="personal-todo-admin__history">
							<h2>{ __( 'Recent history', 'personal-todo' ) }</h2>
							{ editedTask.history.map( ( entry ) => (
								<div key={ entry.id }>
									<RawHTML>{ entry.content }</RawHTML>
									<time>
										{ new Date(
											`${ entry.date_gmt }Z`
										).toLocaleString() }
									</time>
								</div>
							) ) }
						</section>
					) }
				</Modal>
			) }
		</div>
	);
}

const mount = document.getElementById( 'personal-todo-admin-app' );

if ( mount ) {
	createRoot( mount ).render( <TodoAdmin /> );
}
