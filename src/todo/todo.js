import {
	Icon,
	__experimentalHStack as HStack,
	Button,
	Card,
	CardBody,
	DropdownMenu,
} from '@wordpress/components';
import { useState, useMemo, createRoot, useEffect } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import {
	useEntityRecords,
	store as coreStore,
} from '@wordpress/core-data';
import '../notebooks/style.scss';
import {
	swatch,
	check,
	edit,
	external,
	starFilled,
	scheduled,
	pending,
	notAllowed,
	rotateLeft
} from '@wordpress/icons';
import { RawHTML } from '@wordpress/element';
import TodoForm from '../components/todo-form';
import { getNotebook } from '../utils/notebook';

const defaultView = {
	type: 'list',
	search: '',
	page: 1,
	perPage: 100,
	titleField: 'title',
	descriptionField: 'description',
	fields: [ 'flags', 'notebooks' ],
	layout: {},
	filters: [],
	sort: {
		order: 'asc',
		orderby: 'title',
	},
};

function TodoAdmin( props ) {
	let viewConfig = defaultView;
	const possibleFlags = props.possibleFlags;

	if ( props.view ) {
		viewConfig = { ...defaultView, ...props.view };
	}

	const [ view, setView ] = useState( viewConfig );
	const { deleteEntityRecord, saveEntityRecord } = useDispatch( coreStore );

	const { records: notebooks, isLoading: notebooksLoading } =
		useEntityRecords( 'taxonomy', 'notebook', {
			per_page: -1,
			page: 1,
			hide_empty: false,
		} );

	function filterByHash() {
		if ( window.location.hash.length > 1 ) {
			const notebookIds = window.location.hash
				.replace( '#', '' )
				.split( ',' )
				.map( ( slug ) => getNotebook( slug, notebooks )?.id );
			filterByNotebook( notebookIds, true );
			return true;
		}
		return false;
	}

	function filterByNotebook( noteBookIds, override = false ) {
		if ( ! Array.isArray( noteBookIds ) ) {
			noteBookIds = [ noteBookIds ];
		}
		const existingNotebookFilter = new Set(
			view.filters.find( ( filter ) => filter.field === 'notebooks' )
				?.value || []
		);

		const newNotebookFilter = new Set( noteBookIds );

		// Bail if sets the same
		if (
			existingNotebookFilter.size === newNotebookFilter.size &&
			Array.from( existingNotebookFilter ).every( ( id ) =>
				newNotebookFilter.has( id )
			)
		) {
			return;
		}

		let newFilters;

		if ( override ) {
			if ( noteBookIds.includes( 'all' ) ) {
				setView( ( old ) => ( { ...old, filters: [] } ) );
			} else {
				setView( ( old ) => ( {
					...old,
					filters: [
						{
							field: 'notebooks',
							operator: 'isAll',
							value: noteBookIds,
						},
					],
				} ) );
			}
			return;
		}

		if ( existingNotebookFilter.size > 0 ) {
			// If filter exists, toggle the notebookId in its values
			const values = Array.from( existingNotebookFilter );
			const newValues = noteBookIds.every( ( id ) =>
				values.includes( id )
			)
				? values.filter( ( id ) => ! noteBookIds.includes( id ) )
				: [ ...new Set( [ ...values, ...noteBookIds ] ) ];

			newFilters = view.filters.map( ( filter ) =>
				filter.field === 'notebooks'
					? { ...filter, value: newValues }
					: filter
			);
		} else {
			// If no filter exists, create new one
			newFilters = [
				...view.filters,
				{
					field: 'notebooks',
					operator: 'isAll',
					value: Array.from( newNotebookFilter ),
				},
			];
		}

		setView( ( old ) => ( { ...old, filters: newFilters } ) );
	}

	// Our setup in this custom taxonomy.
	const fields = [
		{
			label: __( 'ID', 'your-textdomain' ),
			id: 'id',
			enableHiding: true,
			enableGlobalSearch: true,
			type: 'string',
			render: ( { item } ) => {
				return '#' + item?.id;
			},
			getValue: ( { item } ) => {
				return '' + item?.id;
			},
		},
		{
			label: __( 'Done', 'your-textdomain' ),
			id: 'done',
			enableHiding: true,
			enableGlobalSearch: false,
			type: 'boolean',
			render: ( { item } ) => {
				return (
					<Icon icon={ item?.status === 'trash' ? check : swatch } />
				);
			},
		},
		{
			label: __( 'Title', 'your-textdomain' ),
			id: 'title',
			enableHiding: false,
			enableGlobalSearch: true,
			type: 'string',
			render: ( { item } ) => {
				return item?.title?.raw;
			},
			getValue: ( { item } ) => {
				return item?.title?.raw;
			},
		},
		{
			label: __( 'Description', 'your-textdomain' ),
			id: 'description',
			enableSorting: false,
			enableGlobalSearch: true,
			type: 'string',
			render: ( { item } ) => {
				return (
					<div className="pos__todo-description">
						<RawHTML>{ item?.excerpt?.rendered }</RawHTML>
					</div>
				);
			},
			getValue: ( { item } ) => {
				return item?.excerpt?.raw;
			},
		},
		{
			label: __( 'Flags', 'your-textdomain' ),
			id: 'flags',
			header: (
				<HStack spacing={ 1 } justify="start">
					<span>{ __( 'Flags', 'your-textdomain' ) }</span>
				</HStack>
			),
			type: 'array',
			render: ( { item } ) => {
				if ( ! notebooks ) {
					return '';
				}
				return (
					<>
						{ item.scheduled && (
							<Button
								variant="secondary"
								key={ 'future' }
								size="small"
								icon={ scheduled }
								className="pos__notebook-badge pos__notebook-badge--future"
							>
								{ ( getNotebook(
									item?.meta?.pos_blocked_pending_term,
									notebooks
								)?.name || 'Pending' ) +
									' on ' +
									new Date( item.scheduled * 1000 ).toLocaleDateString() }
							</Button>
						) }
						{ item.meta?.pos_blocked_by > 0 && (
							<Button
								variant="secondary"
								key={ 'blocked' }
								size="small"
								icon={ pending }
								className="pos__notebook-badge pos__notebook-badge--future"
								onClick={ ( event ) => {
									window.open(
										`/wp-admin/post.php?post=${ item?.meta?.pos_blocked_by }&action=edit`,
										'_blank'
									);
								} }
							>
								{ ( getNotebook(
									item?.meta?.pos_blocked_pending_term,
									notebooks
								)?.name || 'Pending' ) +
									' after #' +
									item?.meta?.pos_blocked_by }
							</Button>
						) }
						{ item?.meta?.pos_recurring_days > 0 && (
							<Button
								key={ 'recurring' }
								variant="secondary"
								size="small"
								className="pos__notebook-badge"
								icon={ rotateLeft }
							>
								{ `Every ${ item?.meta?.pos_recurring_days } days` }
							</Button>
						) }
						{ item?.meta?.url && (
							<Button
								variant="secondary"
								size="small"
								className="pos__notebook-badge"
								icon={ external }
								onClick={ ( event ) => {
									window.open( item?.meta?.url, '_blank' );
								} }
							>
								{ new URL( item?.meta?.url ).hostname }
							</Button>
						) }
						{ item?.blocking?.map( ( todo ) => (
							<Button
								variant="secondary"
								key={ todo }
								size="small"
								className="pos__notebook-badge"
								icon={ notAllowed }
								onClick={ ( event ) => {
									window.open(
										`/wp-admin/post.php?post=${ todo }&action=edit`,
										'_blank'
									);
								} }
							>
								{ `Blocking #${ todo }` }
							</Button>
						) ) }
					</>
				);
			},
			enableSorting: false,
			filterBy: {
				operators: [ `isAny`, `isNone`, `isAll`, `isNotAll` ],
			},
			elements: [
				{
					label: 'Future',
					value: 'future',
				},
				{
					label: 'Blocked',
					value: 'blocked',
				},
				{
					label: 'Recurring',
					value: 'recurring',
				},
				{
					label: 'Blocking',
					value: 'blocking',
				},
			],
			getValue: ( { item } ) => [
				( item.scheduled ) && 'future',
				( item.meta?.pos_blocked_by > 0 ) && 'blocked',
				( item.meta?.pos_recurring_days > 0 ) && 'recurring',
				( item.blocking?.length > 0 ) && 'blocking',
			].filter( Boolean ),
		},
		{
			label: __( 'Notebooks', 'your-textdomain' ),
			id: 'notebooks',
			header: (
				<HStack spacing={ 1 } justify="start">
					<span>{ __( 'Notebooks', 'your-textdomain' ) }</span>
				</HStack>
			),
			type: 'array',
			render: ( { item } ) => {
				if ( ! notebooks ) {
					return '';
				}
				return (
					<>
						{ item?.notebook?.map( ( notebook ) => (
							<Button
								variant="tertiary"
								key={ notebook }
								size="small"
								className="pos__notebook-badge"
								onClick={ ( event ) => {
									filterByNotebook( notebook );
								} }
							>
								{ getNotebook( notebook, notebooks )?.name ||
									'' }
							</Button>
						) ) }
					</>
				);
			},
			enableSorting: false,
			filterBy: {
				operators: [ `isAny`, `isNone`, `isAll`, `isNotAll` ],
			},
			elements: notebooks?.map( ( notebook ) => ( {
				label: notebook.name,
				value: notebook.id,
			} ) ),
			getValue: ( { item } ) => {
				return item?.notebook || [];
			},
		},
		{
			label: __( 'Date', 'your-textdomain' ),
			id: 'date',
			enableSorting: true,
			enableGlobalSearch: true,
			type: 'string',
			// TODO: Add date filter before filterSortAndPaginate.
			// filterBy: {
			// 	operators: [ `is`, `isNot` ],
			// },
			render: ( { item } ) => {
				return new Date( item.date ).toLocaleDateString();
			},
			getValue: ( { item } ) => {
				return new Date( item.date ).toLocaleDateString();
			},
			elements: [],
		},
	];

	// We will use the entity records hook to fetch all the items from the "notebook" custom taxonomy
	const { records, isLoading: todoLoading } = useEntityRecords(
		'postType',
		'todo',
		{
			per_page: -1,
			context: 'edit',
			status: [ 'publish', 'pending', 'future', 'private' ],
		}
	);

	// filterSortAndPaginate works in memory. We theoretically could pass the parameters to backend to filter sort and paginate there.
	const { data: shownData, paginationInfo } = useMemo( () => {
		// TODO: Add date filter before filterSortAndPaginate.
		return filterSortAndPaginate( records, view, fields );
	}, [ view, records ] );

	const notebookFilters = view.filters.reduce( ( acc, filter ) => {
		if (
			filter.field === 'notebooks' &&
			( filter.operator === 'isAny' || filter.operator === 'isAll' )
		) {
			acc = acc.concat( filter.value );
		}
		return acc;
	}, [] );

	useEffect( () => {
		if ( notebooks && notebooks.length > 0 ) {
			if ( ! filterByHash() ) {
				filterByNotebook( props.defaultNotebook );
			}
			window.addEventListener( 'hashchange', filterByHash );
		}
	}, [ notebooks ] );

	useEffect( () => {
		if ( notebooks && notebooks.length > 0 && notebookFilters.length > 0 ) {
			//window.removeEventListener( "hashchange", filterByHash );
			window.location.hash = notebookFilters
				.map( ( notebook ) => getNotebook( notebook, notebooks )?.slug )
				.join( ',' );
			//window.addEventListener( "hashchange", filterByHash );
		}
	}, [ notebookFilters, notebooks ] );

	const actions = [
		{
			id: 'open-url',
			label: 'Open URL',
			icon: external,
			isEligible: ( item ) => item.meta?.url,
			callback: async ( items ) => {
				window.open( items[ 0 ].meta?.url, '_blank' );
			},
		},
		{
			id: 'complete',
			label: __( 'Complete', 'your-textdomain' ),
			icon: check,
			isEligible: () => true,
			callback: async ( items ) => {
				// Completed items are in trash.
				items.forEach( ( item ) =>
					deleteEntityRecord( 'postType', 'todo', item.id )
				);
			},
			isPrimary: true,
		},
		{
			id: 'kill',
			label: __( 'Complete and stop recurring', 'your-textdomain' ),
			icon: check,
			isEligible: ( item ) => ( item.meta?.pos_recurring_days > 0 ),
			callback: async ( items ) => {
				// Completed items are in trash.
				items.forEach( ( item ) => {
					saveEntityRecord( 'postType', 'todo', {
						id: item.id,
						meta: {
							pos_recurring_days: 0,
						},
					} ).then( ( response ) => {
						deleteEntityRecord( 'postType', 'todo', item.id );
					} );
				} );
			},
			isPrimary: true,
		},
		{
			id: 'edit',
			label: __( 'Edit', 'your-textdomain' ),
			icon: edit,
			isEligible: () => true,
			callback: async ( items ) => {
				if ( items.length === 1 ) {
					window.open(
						`/wp-admin/post.php?post=${ items[ 0 ].id }&action=edit`,
						'_blank'
					);
				}
			},
		},
	];

	if ( notebooks && ! notebooksLoading ) {
		notebooks.forEach( ( notebook ) => {
			if ( notebook.meta?.flag?.includes( 'star' ) ) {
				actions.push( {
					id: 'move-notebook-' + notebook.id,
					label: 'To ' + notebook.name,
					icon: edit,
					isEligible: ( item ) => true,
					callback: async ( items ) => {
						items.forEach( ( item ) => {
							const changes = {
								id: item.id,
								notebook: [
									notebook.id,
									...item.notebook.filter(
										( id ) =>
											! notebookFilters.includes( id )
									),
								],
							};
							saveEntityRecord( 'postType', 'todo', changes );
						} );
					},
				} );
			}
		} );
	}
	return (
		<>
			<TodoForm
				presetNotebooks={ notebookFilters }
				possibleFlags={ possibleFlags }
				nowNotebook={ props.nowNotebook }
			/>
			<Card elevation={ 1 }>
				<CardBody>
					<DataViews
						isLoading={ todoLoading || notebooksLoading }
						getItemId={ ( item ) => item.id.toString() }
						paginationInfo={ paginationInfo }
						header={
							<DropdownMenu
								controls={ [
									{
										onClick: () =>
											filterByNotebook( 'all', true ),
										title: 'All',
									},
								]
									.concat(
										notebooks
											?.filter( ( notebook ) =>
												notebook?.meta?.flag?.includes(
													'star'
												)
											)
											.map( ( notebook ) => ( {
												onClick: () =>
													filterByNotebook(
														notebook.id,
														true
													),
												title: notebook.name,
											} ) )
									)
									.concat( [
										{
											onClick: () =>
												window.open(
													`/wp-admin/edit.php?post_type=todo`
												),
											title: 'Classic WP-Admin',
										},
									] ) }
								icon={ starFilled }
								label="Filter by a starred notebook"
							/>
						}
						data={ shownData }
						view={ view }
						fields={ fields }
						onChangeView={ setView }
						actions={ actions }
						defaultLayouts={ {
							table: {
								// Define default table layout settings
								spacing: 'normal',
								showHeader: true,
							},
							list: {
								spacing: 'compact',
								showHeader: true,
							},
						} }
					/>
				</CardBody>
			</Card>
		</>
	);
}

window.renderTodoAdmin = ( el, props = {} ) => {
	const root = createRoot( el );
	root.render( <TodoAdmin { ...props } /> );
};
