import {
	Icon,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	Button,
	Card,
	CardBody,
	CardFooter,
	__experimentalInputControl as InputControl,
	TextareaControl,
	PanelBody,
	Panel,
	PanelRow,
	DatePicker,
	CheckboxControl,
	TabPanel,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	DropdownMenu
} from '@wordpress/components';
//import domReady from '@wordpress/dom-ready';
import { useState, useMemo, createRoot, useEffect } from '@wordpress/element';
// Per https://github.com/WordPress/gutenberg/tree/trunk/packages/dataviews :
// Important note If you're trying to use the DataViews component in a WordPress plugin or theme and you're building your scripts using the @wordpress/scripts package, you need to import the components from @wordpress/dataviews/wp instead of @wordpress/dataviews.
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import {
	useEntityRecords,
	store as coreStore,
	useEntityRecord,
} from '@wordpress/core-data';
import '../notebooks/style.scss';
import { calendar, swatch, check, close, edit, external, starFilled } from '@wordpress/icons';
import { RawHTML } from '@wordpress/element';

const defaultView = {
	type: 'list',
	search: '',
	page: 1,
	perPage: 100,
	titleField: 'title',
	descriptionField: 'description',
	fields: [ 'notebooks' ],
	layout: {},
	filters: [],
	sort: {
		order: 'asc',
		orderby: 'title',
	},
};

function NotebookSelectorTabPanel( {
	notebooks,
	chosenNotebooks,
	setNotebook,
	possibleFlags,
} ) {
	return (
		<TabPanel
			children={ ( selectedTab ) => {
				return (
					<div
						style={ {
							maxHeight: '200px',
							overflowY: 'auto',
							padding: '20px',
						} }
					>
						<VStack>
							{ notebooks
								?.filter(
									( notebook ) =>
										selectedTab.name === 'all' ||
										notebook?.meta?.flag?.includes(
											selectedTab.name
										)
								)
								?.map( ( notebook ) => (
									<CheckboxControl
										key={ notebook.id }
										__nextHasNoMarginBottom
										label={ notebook.name }
										checked={ chosenNotebooks.includes(
											notebook.id
										) }
										onChange={ ( value ) =>
											setNotebook( notebook, value )
										}
									/>
								) ) }
						</VStack>
					</div>
				);
			} }
			tabs={ [
				...possibleFlags.map( flag => ( {
					name: flag.id,
					title: flag.name,
				} ) ),
				{
					name: 'all',
					title: 'All Notebooks',
				},
			] }
		/>
	);
}

function TodoForm( { presetNotebooks = [], possibleFlags = [] } ) {
	const emptyTodo = {
		status: 'private',
		title: '',
		excerpt: '',
		notebook: presetNotebooks,
		meta: {},
	};

	const [ newTodo, setNewTodo ] = useState( emptyTodo );

	useEffect( () => {
		setNewTodo( {
			...newTodo,
			notebook: presetNotebooks,//newTodo.notebook.concat( presetNotebooks ),
		} );
	}, [ presetNotebooks ] );

	const { saveEntityRecord } = useDispatch( coreStore );
	const { records: notebooks } = useEntityRecords( 'taxonomy', 'notebook', {
		per_page: -1,
		hide_empty: false,
	} );

	return (
		<Card
			style={ {
				marginBottom: '10px',
			} }
			elevation={ newTodo.title.length > 0 ? 3 : 1 }
			className="pos__todo-form"
		>
			<CardBody>
				<InputControl
					// __next40pxDefaultSize = { true }
					onChange={ ( value ) =>
						setNewTodo( { ...newTodo, title: value } )
					}
					label={ newTodo.title.length > 0 ? 'New TODO' : null }
					onValidate={ () => true }
					value={ newTodo.title }
					placeholder={ "New TODO in " + ( notebooks ? notebooks.filter( notebook => presetNotebooks.includes( notebook.id ) ).map( notebook => notebook.name ).join( ', ' ) : 'Inbox' ) }
				/>
			</CardBody>
			{ newTodo.title.length > 0 && (
				<>
					<CardBody>
						<TextareaControl
							label="Notes"
							onChange={ ( value ) =>
								setNewTodo( { ...newTodo, excerpt: value } )
							}
							placeholder="Placeholder"
							value={ newTodo.excerpt }
						/>
						<InputControl
							__nextHasNoMarginBottom
							onChange={ ( value ) =>
								setNewTodo( { ...newTodo, meta: { ...newTodo.meta, url: value } } )
							}
							label={ 'Action URL' }
							onValidate={ () => true }
							value={ newTodo.meta?.url }
							help={ 'A URL associated with the task. For example tel://500500500 or http://website-of-thing-to-buy' }
							placeholder={ 'https://...' }
						/>
					</CardBody>
					<CardBody>
						<NotebookSelectorTabPanel
							possibleFlags={ possibleFlags }
							notebooks={ notebooks }
							chosenNotebooks={ newTodo.notebook }
							setNotebook={ ( notebook, value ) => {
								if ( value ) {
									setNewTodo( {
										...newTodo,
										notebook: [
											...newTodo.notebook,
											notebook.id,
										],
									} );
								} else {
									setNewTodo( {
										...newTodo,
										notebook: newTodo.notebook.filter(
											( id ) => id !== notebook.id
										),
									} );
								}
							} }
						/>
					</CardBody>
					<CardBody>
						<Panel>
							<PanelBody
								title="Dont bother me before date"
								initialOpen={ false }
							>
								<PanelRow>
									<NotebookSelectorTabPanel
										notebooks={ notebooks }
										possibleFlags={ possibleFlags }
										chosenNotebooks={ [
											newTodo.meta.pos_blocked_pending_term,
										] }
										setNotebook={ ( notebook, value ) => {
											if ( value ) {
												setNewTodo( {
													...newTodo,
													meta: {
														...newTodo.meta,
														pos_blocked_pending_term: notebook.id,
													},
												} );
											} else {
												delete newTodo.meta.pos_blocked_pending_term;
											}
										} }
									/>
								</PanelRow>
								<PanelRow>
									<DatePicker
										label="Show on"
										currentDate={ new Date() }
										onChange={ ( value ) =>
											setNewTodo( {
												...newTodo,
												date: value,
											} )
										}
									/>
								</PanelRow>
							</PanelBody>
							<PanelBody
								title="This TODO depends on"
								initialOpen={ false }
							>
								<PanelRow>
									<h1>Test</h1>
								</PanelRow>
							</PanelBody>
						</Panel>
					</CardBody>
					<CardFooter
						style={ {
							display: 'flex',
							justifyContent: 'space-between',
						} }
					>
						<Button
							variant="tertiary"
							onClick={ () => setNewTodo( emptyTodo ) }
							icon={ close }
						/>
						<Button
							shortcut={ 'CTRL+ENTER' }
							variant="primary"
							isPrimary
							icon={ check }
							onClick={ () => {
								saveEntityRecord(
									'postType',
									'todo',
									newTodo
								);
								setNewTodo( emptyTodo );
							} }
						>
							Add new TODO
						</Button>
					</CardFooter>
				</>
			) }
		</Card>
	);
}

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
			const notebookIds = window.location.hash.replace( '#', '' ).split( ',' ).map( ( id ) => parseInt( id ) );
			filterByNotebook( notebookIds, true );
			return true;
		}
		return false;
	}

	function getNotebook( id, notebooks ) {
		return notebooks.find( ( notebook ) => ( notebook.id === id || notebook.slug == id ) );
	}

	function filterByNotebook( noteBookIds, override = false ) {
		if ( ! Array.isArray( noteBookIds ) ) {
			noteBookIds = [ noteBookIds ];
		}
		const existingNotebookFilter = new Set( view.filters.find(
			( filter ) => filter.field === 'notebooks'
		)?.value || [] );

		const newNotebookFilter = new Set( noteBookIds );

		// Bail if sets the same
		if ( existingNotebookFilter.size === newNotebookFilter.size && Array.from(existingNotebookFilter).every( ( id ) => newNotebookFilter.has( id ) ) ) {
			return;
		}

		let newFilters;

		if ( override ) {
			if ( noteBookIds.includes( 'all' ) ) {
				setView( old => ( { ...old, filters: [] } ) );
			} else {
				setView( old => ( { ...old, filters: [ { field: 'notebooks', operator: 'isAll', value: noteBookIds } ] } ) );
			}
			return;
		}

		if ( existingNotebookFilter.size > 0 ) {
			// If filter exists, toggle the notebookId in its values
			const values = Array.from( existingNotebookFilter );
			const newValues = noteBookIds.every( id => values.includes( id ) )
				? values.filter( id => !noteBookIds.includes( id ) )
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

		setView( (old) => ( { ...old, filters: newFilters } ) );
	}

	// Our setup in this custom taxonomy.
	const fields = [
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
				return <div className="pos__todo-description"><RawHTML>{ item?.excerpt?.rendered }</RawHTML></div>;
			},
			getValue: ( { item } ) => {
				return item?.excerpt?.raw;
			},
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
				const postDate = new Date( item.date );
				return (
					<>
						{ postDate.getTime() > Date.now() && (
							<Button
								variant="secondary"
								key={ 'future' }
								size="small"
								icon={ calendar }
								className="pos__notebook-badge pos__notebook-badge--future"
							>
								{ ( getNotebook( item?.meta?.pos_blocked_pending_term, notebooks )?.name || 'Pending' ) + ' on ' + postDate.toLocaleDateString() }
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
								{ ( new URL( item?.meta?.url ) ).hostname }
							</Button>
						) }
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
			( filter.operator === 'isAny' ||
				filter.operator === 'isAll' )
		) {
			acc = acc.concat( filter.value );
		}
		return acc;
	}, [] );

	useEffect( () => {
		if ( ! notebooksLoading && ! todoLoading ) {
			if ( ! filterByHash() ) {
				filterByNotebook( props.defaultNotebook );
			}
			window.addEventListener( "hashchange", filterByHash );
		}
	}, [ notebooksLoading, todoLoading ] );

	useEffect( () => {
		//window.removeEventListener( "hashchange", filterByHash );
		window.location.hash = notebookFilters.join( ',' );
		//window.addEventListener( "hashchange", filterByHash );
	}, [ notebookFilters ] );

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
					deleteEntityRecord(
						'postType',
						'todo',
						item.id
					)
				);
			},
			isPrimary: true,
		},
		{
			id: 'edit',
			label: __( 'Edit', 'your-textdomain' ),
			icon: edit,
			isEligible: () =>true,
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
			if( notebook.meta?.flag?.includes( 'star' ) ) {
				actions.push( {
					id: 'move-notebook-' + notebook.id,
					label: 'To ' + notebook.name,
					icon: edit,
					isEligible: ( item ) => true,
					callback: async ( items ) => {
						items.forEach( ( item ) => {
							const changes = { id: item.id, notebook: [ notebook.id, ...item.notebook.filter( ( id ) => ! notebookFilters.includes( id ) ) ] };
							saveEntityRecord(
								'postType',
								'todo',
								changes,
							);
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
			/>
			<Card elevation={ 1 }>
				<CardBody>
					<DataViews
						isLoading={ todoLoading || notebooksLoading }
						getItemId={ ( item ) => item.id.toString() }
						paginationInfo={ paginationInfo }
						header={
							<DropdownMenu
								controls={
									[
										{
											onClick: () => filterByNotebook( 'all', true ),
											title: 'All',
										},
									].concat(
										notebooks
										?.filter(
											(notebook) =>
											notebook?.meta?.flag?.includes(
												'star'
											)
									).map((notebook) => ({
										onClick: () => filterByNotebook(notebook.id, true),
											title: notebook.name,
										}))
									).concat(
										[
											{
												onClick: () => window.open( `/wp-admin/edit.php?post_type=todo`),
												title: 'Classic WP-Admin',
											},
										]
									)
								}
								icon={starFilled}
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
