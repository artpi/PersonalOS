import {
	Icon,
	__experimentalHStack as HStack,
	Button,
} from '@wordpress/components';
//import domReady from '@wordpress/dom-ready';
import { useState, useMemo, createRoot } from '@wordpress/element';
// Per https://github.com/WordPress/gutenberg/tree/trunk/packages/dataviews :
// Important note If you're trying to use the DataViews component in a WordPress plugin or theme and you're building your scripts using the @wordpress/scripts package, you need to import the components from @wordpress/dataviews/wp instead of @wordpress/dataviews.
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useEntityRecords, store as coreStore } from '@wordpress/core-data';
import '../notebooks/style.scss';
import { trash, flag, swatch, check } from '@wordpress/icons';

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
function TodoAdmin( props ) {
	let viewConfig = defaultView;

	if ( props.view ) {
		viewConfig = { ...defaultView, ...props.view };
	}

	const [ view, setView ] = useState( viewConfig );
	const { deleteEntityRecord } = useDispatch( coreStore );

	const { records: notebooks, isLoading: notebooksLoading } = useEntityRecords( 'taxonomy', 'notebook', {
		per_page: -1,
		page: 1,
		hide_empty: false,
	} );

	function getNotebook( id, notebooks ) {
		return notebooks.find( ( notebook ) => notebook.id === id );
	}

	function filterByNotebook( noteBookId ) {
		const existingNotebookFilter = view.filters.find(
			( filter ) => filter.field === 'notebooks'
		);
		let newFilters;

		if ( existingNotebookFilter ) {
			// If filter exists, toggle the notebookId in its values
			const values = existingNotebookFilter.value || [];
			const newValues = values.includes( noteBookId )
				? values.filter( ( id ) => id !== noteBookId )
				: [ ...values, noteBookId ];

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
					value: [ noteBookId ],
				},
			];
		}

		setView( { ...view, filters: newFilters } );
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
				return item?.excerpt?.raw;
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
					<Icon icon={ flag } />
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
								variant="secondary"
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
	];

	// We will use the entity records hook to fetch all the items from the "notebook" custom taxonomy
	const { records, isLoading: todoLoading } = useEntityRecords( 'postType', 'todo', {
		per_page: -1,
		context: 'edit',
		status: [ 'publish', 'pending', 'future', 'private' ],
	} );

	// filterSortAndPaginate works in memory. We theoretically could pass the parameters to backend to filter sort and paginate there.
	const { data: shownData, paginationInfo } = useMemo( () => {
		return filterSortAndPaginate( records, view, fields );
	}, [ view, records ] );

	return (
		<DataViews
			isLoading={ todoLoading || notebooksLoading }
			getItemId={ ( item ) => item.id.toString() }
			paginationInfo={ paginationInfo }
			data={ shownData }
			view={ view }
			fields={ fields }
			onChangeView={ setView }
			actions={ [
				{
					id: 'complete',
					label: __( 'Complete', 'your-textdomain' ),
					icon: check,
					callback: async ( items ) => {
						// Completed items are in trash.
						items.forEach( ( item ) => deleteEntityRecord( 'postType', 'todo', item.id ) );
					},
					isPrimary: true,
				}
			] }
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
			isItemClickable={ () => false }
		/>
	);
}

window.renderTodoAdmin = ( el, props = {} ) => {
	const root = createRoot( el );
	root.render( <TodoAdmin { ...props } /> );
};
