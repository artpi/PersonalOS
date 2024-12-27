import { Icon, __experimentalHStack as HStack } from '@wordpress/components';
//import domReady from '@wordpress/dom-ready';
import { useState, useMemo, createRoot } from '@wordpress/element';
// Per https://github.com/WordPress/gutenberg/tree/trunk/packages/dataviews :
// Important note If you're trying to use the DataViews component in a WordPress plugin or theme and you're building your scripts using the @wordpress/scripts package, you need to import the components from @wordpress/dataviews/wp instead of @wordpress/dataviews.
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { useEntityRecords } from '@wordpress/core-data';
import './style.scss';
import { trash, flag } from '@wordpress/icons';

const defaultView = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 100,
	fields: [ 'name', 'description', 'flags', 'count' ],
	layout: {},
	filters: [],
	sort: {
		order: 'asc',
		orderby: 'name',
	},
};
function NotebookAdmin( props ) {
	let viewConfig = defaultView;

	if ( props.view ) {
		viewConfig = { ...defaultView, ...props.view };
	}

	const [ view, setView ] = useState( viewConfig );

	// Our setup in this custom taxonomy.
	const fields = [
		{
			label: __( 'Name', 'your-textdomain' ),
			id: 'name',
			enableHiding: false,
			enableGlobalSearch: true,
			type: 'string',
		},
		{
			label: __( 'Description', 'your-textdomain' ),
			id: 'description',
			enableSorting: false,
			enableGlobalSearch: true,
			type: 'string',
		},
		{
			label: __( 'Flags', 'your-textdomain' ),
			id: 'flags',
			header: (
				<HStack spacing={ 1 } justify="start">
					<Icon icon={ flag } />
					<span>{ __( 'Flags', 'your-textdomain' ) }</span>
				</HStack>
			),
			type: 'array',
			render: ( { item } ) => {
				return item?.meta?.flag?.join( ', ' ) || '';
			},
			enableSorting: false,
			filterBy: {
				operators: [ `isAny`, `isNone`, `isAll`, `isNotAll` ],
			},
			// elements: [
			// 	{
			// 		label: __( 'Bucketlist', 'your-textdomain' ),
			// 		value: 'bucketlist',
			// 	},
			// ],
			getValue: ( { item } ) => {
				return item?.meta?.flag || [];
			},
		},
		{
			label: __( 'Count', 'your-textdomain' ),
			id: 'count',
			enableSorting: true,
			enableGlobalSearch: false,
			type: 'number',
		},
	];

	// We will use the entity records hook to fetch all the items from the "notebook" custom taxonomy
	const { records } = useEntityRecords( 'taxonomy', 'notebook', {
		per_page: -1,
		page: 1,
		hide_empty: false,
	} );

	// filterSortAndPaginate works in memory. We theoretically could pass the parameters to backend to filter sort and paginate there.
	const { data: shownData, paginationInfo } = useMemo( () => {
		return filterSortAndPaginate( records, view, fields );
	}, [ view, records ] );

	return (
		<DataViews
			getItemId={ ( item ) => item.id.toString() }
			paginationInfo={ paginationInfo }
			data={ shownData }
			view={ view }
			fields={ fields }
			onChangeView={ setView }
			actions={ [
				{
					id: 'delete',
					label: __( 'Delete', 'your-textdomain' ),
					icon: trash,
					callback: async ( items ) => {
						// Implement delete functionality
						console.log( 'Delete items:', items );
					},
				},
			] }
			defaultLayouts={ {
				table: {
					// Define default table layout settings
					spacing: 'normal',
					showHeader: true,
				},
			} }
		/>
	);
}

window.renderNotebookAdmin = ( el, props = {} ) => {
	const root = createRoot( el );
	root.render( <NotebookAdmin { ...props } /> );
};
