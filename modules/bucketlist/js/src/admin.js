import { DataViews } from '@wordpress/dataviews/wp';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { store as coreStore, useEntityRecords } from '@wordpress/core-data';
import "./style.scss";
import { Icon } from '@wordpress/components';

import { trash, flag } from '@wordpress/icons';
import {
	Button,
	__experimentalText as Text,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

function BucketlistAdmin() {
	const [ view, setView ] = useState({
		type: 'table',
		search: '',
		page: 1,
		perPage: 10,
		fields: [ 'name', 'description', 'flags', 'count' ],
		layout: {},
		filters: [],
	});

	// Fetch notebooks taxonomy data

	const fields = [
		{
			label: __('Name', 'your-textdomain'),
			id: 'name',
			enableHiding: false,
			enableGlobalSearch: true,
			type: 'string',
		},
		{
			label: __('Description', 'your-textdomain'),
			id: 'description',
			enableSorting: false,
			enableGlobalSearch: true,
			type: 'string',
		},
		{
			label: __('Flags', 'your-textdomain'),
			id: 'flags',
			header: (
				<HStack spacing={1} justify="start">
					<Icon icon={flag} />
					<span>{__('Flags', 'your-textdomain')}</span>
				</HStack>
			),
			type: 'array',
			render: ({ item }) => {
				return item.meta?.flag?.join(', ') || '';
			},
			enableSorting: false,
		},
		{
			label: __('Count', 'your-textdomain'),
			id: 'count',
			enableSorting: true,
			enableGlobalSearch: false,
			type: 'number',
		},
	];




	const { records, isLoading, hasResolved } = useEntityRecords( 'taxonomy', 'notebook', {
		per_page: view.perPage,
		page: view.page,
		hide_empty: false,
	} );

	console.log(records);
	// Transform notebooks data for DataViews

	if (!hasResolved) {
		return <div>{__('Loading...', 'your-textdomain')}</div>;
	}

	return (
		<DataViews
			getItemId={(item) => item.id.toString()}
			paginationInfo={{
				totalItems: records.length,
				totalPages: Math.ceil(records.length / view.perPage),
			}}
			data={records}
			view={view}
			fields={fields}
			onChangeView={setView}
			actions={[
				{
					id: 'delete',
					label: __('Delete', 'your-textdomain'),
					icon: trash,
					callback: async (items) => {
						// Implement delete functionality
						console.log('Delete items:', items);
					},
				},
			]}
			defaultLayouts={{
				table: {
					// Define default table layout settings
					spacing: 'normal',
					showHeader: true,
				},
			}}
		/>
	);
}

domReady(() => {
	const root = createRoot(
		document.getElementById('bucketlist-root')
	);
	root.render(<BucketlistAdmin />);
});