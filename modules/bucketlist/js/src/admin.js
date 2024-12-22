import { DataViews, filterSortAndPaginate, View } from '@wordpress/dataviews/wp';
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { trash, image, Icon, category } from '@wordpress/icons';
import {
	Button,
	__experimentalText as Text,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

const data = [
	{
		id: 1,
		title: 'Apollo',
		description: 'Apollo description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Not a planet',
		categories: [ 'Space', 'NASA' ],
		satellites: 0,
		date: '2021-01-01T00:00:00Z',
	},
	{
		id: 2,
		title: 'Space',
		description: 'Space description',
		image: 'https://live.staticflickr.com/5678/21911065441_92e2d44708_b.jpg',
		type: 'Not a planet',
		categories: [ 'Space' ],
		satellites: 0,
		date: '2019-01-02T00:00:00Z',
	},
	{
		id: 3,
		title: 'NASA',
		description: 'NASA photo',
		image: 'https://live.staticflickr.com/742/21712365770_8f70a2c91e_b.jpg',
		type: 'Not a planet',
		categories: [ 'NASA' ],
		satellites: 0,
		date: '2025-01-03T00:00:00Z',
	},
	{
		id: 4,
		title: 'Neptune',
		description: 'Neptune description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Ice giant',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 14,
		date: '2020-01-01T00:00:00Z',
	},
	{
		id: 5,
		title: 'Mercury',
		description: 'Mercury description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Terrestrial',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 0,
		date: '2020-01-02T01:00:00Z',
	},
	{
		id: 6,
		title: 'Venus',
		description: 'La planète Vénus',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Terrestrial',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 0,
		date: '2020-01-02T00:00:00Z',
	},
	{
		id: 7,
		title: 'Earth',
		description: 'Earth description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Terrestrial',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 1,
		date: '2023-01-03T00:00:00Z',
	},
	{
		id: 8,
		title: 'Mars',
		description: 'Mars description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Terrestrial',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 2,
		date: '2020-01-01T00:00:00Z',
	},
	{
		id: 9,
		title: 'Jupiter',
		description: 'Jupiter description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Gas giant',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 95,
		date: '2017-01-01T00:01:00Z',
	},
	{
		id: 10,
		title: 'Saturn',
		description: 'Saturn description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Gas giant',
		categories: [ 'Space', 'Planet', 'Solar system' ],
		satellites: 146,
		date: '2020-02-01T00:02:00Z',
	},
	{
		id: 11,
		title: 'Uranus',
		description: 'Uranus description',
		image: 'https://live.staticflickr.com/5725/21726228300_51333bd62c_b.jpg',
		type: 'Ice giant',
		categories: [ 'Space', 'Ice giant', 'Solar system' ],
		satellites: 28,
		date: '2020-03-01T00:00:00Z',
	},
];

const fields = [
	{
		label: 'Image',
		id: 'image',
		header: (
			<HStack spacing={ 1 } justify="start">
				<Icon icon={ image } />
				<span>Image</span>
			</HStack>
		),
		render: ( { item } ) => {
			return (
				<img src={ item.image } alt="" style={ { width: '100%' } } />
			);
		},
		enableSorting: false,
	},
	{
		label: 'Title',
		id: 'title',
		enableHiding: false,
		enableGlobalSearch: true,
	},
	{
		id: 'date',
		label: 'Date',
		type: 'datetime',
	},
	{
		label: 'Type',
		id: 'type',
		enableHiding: false,
		elements: [
			{ value: 'Not a planet', label: 'Not a planet' },
			{ value: 'Ice giant', label: 'Ice giant' },
			{ value: 'Terrestrial', label: 'Terrestrial' },
			{ value: 'Gas giant', label: 'Gas giant' },
		],
	},
	{
		label: 'Satellites',
		id: 'satellites',
		type: 'integer',
		enableSorting: true,
	},
	{
		label: 'Description',
		id: 'description',
		enableSorting: false,
		enableGlobalSearch: true,
	},
	{
		label: 'Categories',
		id: 'categories',
		header: (
			<HStack spacing={ 1 } justify="start">
				<Icon icon={ category } />
				<span>Categories</span>
			</HStack>
		),
		elements: [
			{ value: 'Space', label: 'Space' },
			{ value: 'NASA', label: 'NASA' },
			{ value: 'Planet', label: 'Planet' },
			{ value: 'Solar system', label: 'Solar system' },
			{ value: 'Ice giant', label: 'Ice giant' },
		],
		getValue: ( { item } ) => {
			return item.categories;
		},
		render: ( { item } ) => {
			return item.categories.join( ',' );
		},
		enableSorting: false,
	},
];
const DEFAULT_VIEW = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 10,
	layout: {},
	filters: [],
};

const actions = [];

function BucketlistAdmin() {
	const [ view, setView ] = useState( {
		...DEFAULT_VIEW,
		fields: [ 'title', 'description', 'categories' ],
	} );

	return (
		<DataViews
			getItemId={ ( item ) => item.id.toString() }
			paginationInfo={ { totalItems: 11, totalPages: 1 } }
			data={ data }
			view={ view }
			fields={ fields }
			onChangeView={ setView }
			actions={ actions }
			defaultLayouts={ {
				table: {},
			} }
		/>
	);
}

domReady( () => {
	const root = createRoot(
		document.getElementById( 'bucketlist-root' )
	);
	root.render( <BucketlistAdmin /> );
} );