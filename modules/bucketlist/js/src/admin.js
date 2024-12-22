import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import './style.scss';
import { Icon } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { trash, flag } from '@wordpress/icons';
import { __experimentalHStack as HStack } from '@wordpress/components';

function NotebookAdmin() {
	const [ view, setView ] = useState( {
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
	} );

	// Fetch notebooks taxonomy data

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
				return item.meta?.flag?.join( ', ' ) || '';
			},
			enableSorting: false,
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

domReady( () => {
	const root = createRoot( document.getElementById( 'bucketlist-root' ) );
	root.render( <NotebookAdmin /> );
} );
