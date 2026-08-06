import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import {
	createRoot,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { edit, plus, trash } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

import { isAssignableKnowledgeType } from './knowledge-types';
import './style.css';

const settings = window.personalNotesSettings || {};

const DEFAULT_VIEW = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 20,
	titleField: 'title',
	descriptionField: 'description',
	fields: [ 'knowledgeTypes', 'source', 'modified' ],
	filters: [],
	sort: {
		field: 'modified',
		direction: 'desc',
	},
	layout: {
		density: 'balanced',
		styles: { modified: { width: '150px' } },
	},
};

const DEFAULT_LAYOUTS = {
	table: {
		layout: DEFAULT_VIEW.layout,
	},
	list: {},
	grid: {
		layout: {
			badgeFields: [ 'knowledgeTypes', 'source' ],
			previewSize: 260,
		},
	},
};

const MOBILE_VIEW = {
	...DEFAULT_VIEW,
	type: 'list',
	perPage: 10,
	layout: {},
};

async function fetchAllPages( path, query = {} ) {
	const firstResponse = await apiFetch( {
		path: addQueryArgs( path, { ...query, page: 1, per_page: 100 } ),
		parse: false,
	} );
	const firstPage = await firstResponse.json();
	const totalPages = Number(
		firstResponse.headers.get( 'X-WP-TotalPages' ) || 1
	);

	if ( totalPages <= 1 ) {
		return firstPage;
	}

	const remainingPages = await Promise.all(
		Array.from( { length: totalPages - 1 }, ( value, index ) =>
			apiFetch( {
				path: addQueryArgs( path, {
					...query,
					page: index + 2,
					per_page: 100,
				} ),
			} )
		)
	);

	return firstPage.concat( ...remainingPages );
}

function getTermIds( item ) {
	return item[ settings.taxonomyField ] || [];
}

function getDateValue( value ) {
	return value ? `${ value }Z` : '';
}

function NotesAdmin() {
	const [ notes, setNotes ] = useState( [] );
	const [ terms, setTerms ] = useState( [] );
	const [ view, setView ] = useState( () =>
		window.matchMedia( '(max-width: 782px)' ).matches
			? MOBILE_VIEW
			: DEFAULT_VIEW
	);
	const [ loading, setLoading ] = useState( true );
	const [ creating, setCreating ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const termsById = useMemo(
		() => new Map( terms.map( ( term ) => [ term.id, term ] ) ),
		[ terms ]
	);
	const termsBySlug = useMemo(
		() => new Map( terms.map( ( term ) => [ term.slug, term ] ) ),
		[ terms ]
	);
	const knowledgeTypeTerms = useMemo(
		() => terms.filter( isAssignableKnowledgeType ),
		[ terms ]
	);
	const knowledgeTypeIds = useMemo(
		() => new Set( knowledgeTypeTerms.map( ( term ) => term.id ) ),
		[ knowledgeTypeTerms ]
	);

	const getSourceLabel = useCallback(
		( item ) => {
			const slugs = getTermIds( item ).map(
				( termId ) => termsById.get( termId )?.slug
			);

			if ( slugs.includes( 'readwise' ) ) {
				return __( 'Readwise', 'personal-notes' );
			}
			if ( slugs.includes( 'evernote' ) ) {
				return __( 'Evernote', 'personal-notes' );
			}
			if ( slugs.includes( 'synced' ) ) {
				return __( 'Synced', 'personal-notes' );
			}
			return __( 'Manual', 'personal-notes' );
		},
		[ termsById ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Title', 'personal-notes' ),
				type: 'text',
				enableHiding: false,
				enableSorting: true,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.title.raw,
			},
			{
				id: 'description',
				label: __( 'Description', 'personal-notes' ),
				type: 'text',
				enableSorting: false,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.excerpt.raw,
			},
			{
				id: 'knowledgeTypes',
				label: __( 'Knowledge Type', 'personal-notes' ),
				type: 'text',
				enableSorting: false,
				elements: knowledgeTypeTerms.map( ( term ) => ( {
					label: term.name,
					value: term.id.toString(),
				} ) ),
				filterBy: {
					operators: [ 'isAny' ],
					isPrimary: true,
				},
				getValue: ( { item } ) =>
					getTermIds( item )
						.filter( ( termId ) => knowledgeTypeIds.has( termId ) )
						.map( ( termId ) => termId.toString() ),
				render: ( { item } ) => (
					<span className="personal-notes-admin__knowledge-types">
						{ getTermIds( item )
							.filter( ( termId ) =>
								knowledgeTypeIds.has( termId )
							)
							.map( ( termId ) => termsById.get( termId )?.name )
							.filter( Boolean )
							.join( ', ' ) || __( 'Unfiled', 'personal-notes' ) }
					</span>
				),
			},
			{
				id: 'source',
				label: __( 'Source', 'personal-notes' ),
				type: 'text',
				enableSorting: false,
				getValue: ( { item } ) => getSourceLabel( item ),
			},
			{
				id: 'modified',
				label: __( 'Modified', 'personal-notes' ),
				type: 'datetime',
				enableSorting: true,
				getValue: ( { item } ) => getDateValue( item.modified_gmt ),
				render: ( { item } ) =>
					new Intl.DateTimeFormat( undefined, {
						dateStyle: 'medium',
					} ).format( new Date( getDateValue( item.modified_gmt ) ) ),
			},
		],
		[ knowledgeTypeIds, knowledgeTypeTerms, getSourceLabel, termsById ]
	);

	const { data: shownNotes, paginationInfo } = useMemo(
		() => filterSortAndPaginate( notes, view, fields ),
		[ fields, notes, view ]
	);

	useEffect( () => {
		document.body.classList.add( 'personal-notes-js' );

		async function loadNotes() {
			try {
				const fetchedTerms = await fetchAllPages(
					settings.taxonomyRestPath,
					{
						context: 'view',
						hide_empty: false,
					}
				);
				const noteTerm = fetchedTerms.find(
					( term ) => term.slug === 'note'
				);

				if ( ! noteTerm ) {
					throw new Error(
						__(
							'The Knowledge note type is missing.',
							'personal-notes'
						)
					);
				}

				const fetchedNotes = await fetchAllPages(
					settings.knowledgeRestPath,
					{
						context: 'edit',
						status: [ 'private', 'publish', 'future' ],
						[ settings.taxonomyField ]: [ noteTerm.id ],
					}
				);

				setTerms( fetchedTerms );
				setNotes( fetchedNotes );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message:
						error.message ||
						__( 'Could not load notes.', 'personal-notes' ),
				} );
			} finally {
				setLoading( false );
			}
		}

		loadNotes();
	}, [] );

	async function createNote() {
		const requiredTerms = [ 'artifact', 'note', 'manual', 'inbox' ]
			.map( ( slug ) => termsBySlug.get( slug )?.id )
			.filter( Boolean );

		if ( requiredTerms.length !== 4 ) {
			setNotice( {
				status: 'error',
				message: __(
					'The Knowledge note terms are missing.',
					'personal-notes'
				),
			} );
			return;
		}

		setCreating( true );
		setNotice( null );
		try {
			const note = await apiFetch( {
				path: settings.knowledgeRestPath,
				method: 'POST',
				data: {
					title: __( 'Untitled Note', 'personal-notes' ),
					content: '',
					status: 'private',
					[ settings.taxonomyField ]: requiredTerms,
				},
			} );
			window.location.assign(
				addQueryArgs( settings.editPostUrl, {
					post: note.id,
					action: 'edit',
				} )
			);
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Could not create a note.', 'personal-notes' ),
			} );
			setCreating( false );
		}
	}

	const actions = [
		{
			id: 'edit',
			label: __( 'Edit', 'personal-notes' ),
			icon: edit,
			isPrimary: true,
			supportsBulk: false,
			callback: ( items ) => {
				window.location.assign(
					addQueryArgs( settings.editPostUrl, {
						post: items[ 0 ].id,
						action: 'edit',
					} )
				);
			},
		},
		{
			id: 'trash',
			label: __( 'Move to trash', 'personal-notes' ),
			icon: trash,
			isDestructive: true,
			supportsBulk: true,
			callback: async ( items, { onActionPerformed } ) => {
				try {
					await Promise.all(
						items.map( ( item ) =>
							apiFetch( {
								path: `${ settings.knowledgeRestPath }/${ item.id }`,
								method: 'DELETE',
							} )
						)
					);
					onActionPerformed?.( items );
					const removedIds = new Set(
						items.map( ( item ) => item.id )
					);
					setNotes( ( current ) =>
						current.filter(
							( item ) => ! removedIds.has( item.id )
						)
					);
					setNotice( {
						status: 'success',
						message:
							items.length === 1
								? __( 'Note moved to trash.', 'personal-notes' )
								: __(
										'Notes moved to trash.',
										'personal-notes'
								  ),
					} );
				} catch ( error ) {
					setNotice( {
						status: 'error',
						message:
							error.message ||
							__( 'Could not trash the note.', 'personal-notes' ),
					} );
				}
			},
		},
	];

	const editNote = ( item ) =>
		window.location.assign(
			addQueryArgs( settings.editPostUrl, {
				post: item.id,
				action: 'edit',
			} )
		);

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<DataViews
				isLoading={ loading }
				getItemId={ ( item ) => item.id.toString() }
				data={ shownNotes }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				paginationInfo={ paginationInfo }
				defaultLayouts={ DEFAULT_LAYOUTS }
				onClickItem={ editNote }
				isItemClickable={ () => true }
				searchLabel={ __( 'Search notes', 'personal-notes' ) }
				header={
					<Button
						variant="primary"
						icon={ plus }
						onClick={ createNote }
						isBusy={ creating }
						disabled={ creating || loading }
					>
						{ __( 'Add note', 'personal-notes' ) }
					</Button>
				}
			/>
		</>
	);
}

const mount = document.getElementById( 'personal-notes-admin-app' );

if ( mount ) {
	createRoot( mount ).render( <NotesAdmin /> );
}
