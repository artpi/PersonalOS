import { DataViews } from '@wordpress/dataviews/wp';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import "./style.scss";


import { trash, image, Icon, category } from '@wordpress/icons';
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
		fields: [ 'title', 'description', 'categories' ],
		layout: {},
		filters: [],
	});

	// Fetch notebooks taxonomy data
	const { notebooks, hasResolved } = useSelect((select) => {
		const query = {
			per_page: -1,
			hide_empty: false,
		};
		
		return {
			notebooks: select(coreStore).getEntityRecords(
				'taxonomy',
				'notebook',
				query
			),
			hasResolved: select(coreStore).hasFinishedResolution(
				'getEntityRecords',
				['taxonomy', 'notebook', query]
			),
		};
	}, []);

	const fields = [
		{
			label: __('Title', 'your-textdomain'),
			id: 'title',
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
			label: __('Categories', 'your-textdomain'),
			id: 'categories',
			header: (
				<HStack spacing={1} justify="start">
					<Icon icon={category} />
					<span>{__('Categories', 'your-textdomain')}</span>
				</HStack>
			),
			type: 'array',
			render: ({ item }) => {
				return item.categories?.join(', ') || '';
			},
			enableSorting: false,
		},
	];

	if (!hasResolved) {
		return <div>{__('Loading...', 'your-textdomain')}</div>;
	}

	// Transform notebooks data for DataViews
	const items = notebooks?.map((notebook) => ({
		id: notebook.id,
		title: notebook.name,
		description: notebook.description,
		categories: notebook.meta?.categories || [],
		slug: notebook.slug,
	})) || [];

	return (
		<DataViews
			getItemId={(item) => item.id.toString()}
			paginationInfo={{
				totalItems: items.length,
				totalPages: Math.ceil(items.length / view.perPage),
			}}
			data={items}
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