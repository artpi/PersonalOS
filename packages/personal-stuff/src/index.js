import apiFetch from '@wordpress/api-fetch';
import { registerCoreBlocks } from '@wordpress/block-library';
import {
	BlockEditorProvider,
	BlockList,
	BlockTools,
	WritingFlow,
	ObserveTyping,
	Inserter,
	BlockInspector,
	RichText,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { MediaUpload } from '@wordpress/media-utils';
import { addFilter } from '@wordpress/hooks';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	CheckboxControl,
	SlotFillProvider,
	Popover,
	Spinner,
} from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import {
	createRoot,
	useEffect,
	useMemo,
	useRef,
	useState,
	RawHTML,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	edit,
	plus,
	search as searchIcon,
	image as imageIcon,
	Icon,
	chevronRight,
	update,
	funnel,
	category,
	seen as viewIcon,
} from '@wordpress/icons';
import { contentSession, appendPhotos, itemContent } from './content';
import { createItemSlug } from './identity';
import { SearchIndex } from './search';
import './style.css';

registerCoreBlocks();
addFilter( 'editor.MediaUpload', 'personal-stuff/media', () => MediaUpload );
const settings = window.personalStuffSettings;
const termIds = ( item ) => item[ settings.taxonomyField ] || [];
const title = ( item ) => item.title.raw;
const editorUrl = ( item ) =>
	addQueryArgs( settings.editPostUrl, { post: item.id, action: 'edit' } );

async function allPages( path, query = {} ) {
	let rows = [];
	for ( let page = 1, pages = 1; page <= pages; page++ ) {
		const response = await apiFetch( {
			path: addQueryArgs( path, { ...query, per_page: 100, page } ),
			parse: false,
		} );
		rows = rows.concat( await response.json() );
		pages = Number( response.headers.get( 'X-WP-TotalPages' ) || 1 );
	}
	return rows;
}

function ancestors( term, byId ) {
	const chain = [];
	const seen = new Set();
	while ( term && ! seen.has( term.id ) ) {
		seen.add( term.id );
		chain.unshift( term );
		term = byId.get( term.parent );
	}
	return chain;
}

function useVocabulary( terms ) {
	return useMemo( () => {
		const byId = new Map( terms.map( ( term ) => [ term.id, term ] ) );
		const bySlug = new Map( terms.map( ( term ) => [ term.slug, term ] ) );
		const below = ( term, slug ) =>
			term.slug !== slug &&
			ancestors( term, byId ).some( ( parent ) => parent.slug === slug );
		const path = ( term ) =>
			ancestors( term, byId )
				.filter( ( parent ) => below( parent, 'stuff-places' ) )
				.map( ( parent ) => parent.name )
				.join( ' / ' );
		return {
			byId,
			bySlug,
			below,
			path,
			places: terms
				.filter( ( term ) => below( term, 'stuff-places' ) )
				.sort( ( a, b ) => path( a ).localeCompare( path( b ) ) ),
			tags: terms.filter( ( term ) => below( term, 'stuff-tags' ) ),
		};
	}, [ terms ] );
}

function Photo( { url, srcSet = '', alt = '' } ) {
	const [ broken, setBroken ] = useState( false );
	useEffect( () => setBroken( false ), [ url ] );
	return url && ! broken ? (
		<img
			src={ url }
			srcSet={ srcSet || undefined }
			sizes={ srcSet ? '(max-width: 480px) 100vw, 320px' : undefined }
			alt={ alt }
			loading="lazy"
			onError={ () => setBroken( true ) }
		/>
	) : (
		<span className="stuff-photo-empty">
			<Icon icon={ imageIcon } size={ 36 } />
			{ url
				? __( 'Photo unavailable', 'personal-stuff' )
				: __( 'No photo', 'personal-stuff' ) }
		</span>
	);
}

function ItemEditor( {
	item,
	isNew,
	initialPlaceSlug,
	vocabulary,
	onSaved,
	onClose,
} ) {
	const session = useMemo(
		() => contentSession( item.content.raw ),
		[ item.content.raw ]
	);
	const [ blocks, setBlocks ] = useState( session.blocks );
	const blocksRef = useRef( blocks );
	blocksRef.current = blocks;
	const [ name, setName ] = useState( isNew ? '' : title( item ) );
	const nameRef = useRef( name );
	nameRef.current = name;
	const currentPlaces = termIds( item ).filter( ( id ) =>
		vocabulary.places.some( ( place ) => place.id === id )
	);
	const [ place, setPlace ] = useState( String( currentPlaces[ 0 ] || '' ) );
	const placeRef = useRef( place );
	placeRef.current = place;
	const initialPlaceApplied = useRef(
		! isNew || ! initialPlaceSlug || initialPlaceSlug === 'unplaced'
	);
	const [ tags, setTags ] = useState(
		termIds( item ).filter( ( id ) =>
			vocabulary.tags.some( ( tag ) => tag.id === id )
		)
	);
	const tagsRef = useRef( tags );
	tagsRef.current = tags;
	const newItemSlug = useRef( '' );
	if ( isNew && ! newItemSlug.current ) {
		newItemSlug.current = createItemSlug( window.crypto );
	}
	const [ saved, setSaved ] = useState( item );
	const savedRef = useRef( saved );
	savedRef.current = saved;
	useEffect( () => {
		const previous = window.wp.media.view.settings.post.id;
		window.wp.media.view.settings.post.id = saved.id || 0;
		return () => {
			window.wp.media.view.settings.post.id = previous;
		};
	}, [ saved.id ] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ progress, setProgress ] = useState( '' );
	const [ url, setUrl ] = useState( '' );
	const [ inspect, setInspect ] = useState( false );
	const [ confirmClose, setConfirmClose ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const revisionRef = useRef( 0 );
	const vocabularyReady = [ 'artifact', 'stuff-item' ].every( ( slug ) =>
		vocabulary.bySlug.has( slug )
	);
	useEffect( () => {
		if ( initialPlaceApplied.current ) {
			return;
		}
		const initialPlace = vocabulary.bySlug.get( initialPlaceSlug );
		if (
			initialPlace &&
			vocabulary.places.some( ( term ) => term.id === initialPlace.id )
		) {
			const initialPlaceId = String( initialPlace.id );
			placeRef.current = initialPlaceId;
			setPlace( initialPlaceId );
			initialPlaceApplied.current = true;
		}
	}, [ initialPlaceSlug, vocabulary ] );
	useEffect( () => {
		if ( ! dirty ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );
	const changeBlocks = ( next ) => {
		blocksRef.current = next;
		setBlocks( next );
		revisionRef.current += 1;
		setDirty( true );
	};

	async function persist( requestedBlocks ) {
		if ( ! vocabularyReady ) {
			throw new Error(
				__(
					'Item options are still loading. Try saving again in a moment.',
					'personal-stuff'
				)
			);
		}
		const previous = savedRef.current;
		if ( previous.id ) {
			const current = await apiFetch( {
				path: `${ settings.knowledgeRestPath }/${ previous.id }?context=edit`,
			} );
			if (
				current.modified_gmt !== previous.modified_gmt ||
				current.content.raw !== previous.content.raw ||
				JSON.stringify( termIds( current ) ) !==
					JSON.stringify( termIds( previous ) ) ||
				title( current ) !== title( previous )
			) {
				throw new Error(
					__(
						'This item changed elsewhere. Close and reopen it before saving your changes.',
						'personal-stuff'
					)
				);
			}
		}
		const unrelated = termIds( previous ).filter(
			( id ) =>
				! vocabulary.places.some( ( term ) => term.id === id ) &&
				! vocabulary.tags.some( ( term ) => term.id === id )
		);
		const nextBlocks = requestedBlocks || blocksRef.current;
		const content = session.serialize( nextBlocks );
		const persistedRevision = revisionRef.current;
		const data = {
			title:
				nameRef.current.trim() ||
				__( 'Untitled item', 'personal-stuff' ),
			[ settings.taxonomyField ]: Array.from(
				new Set( [
					...unrelated,
					vocabulary.bySlug.get( 'artifact' ).id,
					vocabulary.bySlug.get( 'stuff-item' ).id,
					...tagsRef.current,
					...( placeRef.current
						? [ Number( placeRef.current ) ]
						: [] ),
				] )
			),
			...( previous.id
				? {}
				: { status: 'private', slug: newItemSlug.current } ),
			...( ! previous.id || content !== previous.content.raw
				? { content }
				: {} ),
		};
		const result = await apiFetch( {
			path: `${ settings.knowledgeRestPath }${
				previous.id ? `/${ previous.id }` : ''
			}`,
			method: 'POST',
			data,
		} );
		savedRef.current = result;
		setSaved( result );
		if ( revisionRef.current === persistedRevision ) {
			setDirty( false );
		}
		onSaved( result );
		return result;
	}

	async function save() {
		setBusy( true );
		setError( '' );
		try {
			await persist();
			onClose();
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	}

	async function upload( filesList, onFileChange, onError ) {
		const files = Array.from( filesList );
		setBusy( true );
		setError( '' );
		const uploaded = [];
		try {
			const parent = await persist();
			for ( const [ index, file ] of Array.from( files ).entries() ) {
				setProgress(
					sprintf(
						// translators: 1: current photo, 2: number of photos.
						__( 'Uploading photo %1$d of %2$d…', 'personal-stuff' ),
						index + 1,
						files.length
					)
				);
				const body = new FormData();
				body.append( 'file', file );
				body.append( 'post', parent.id );
				const media = await apiFetch( {
					path: '/wp/v2/media',
					method: 'POST',
					body,
				} );
				const photo = { ...media, url: media.source_url };
				uploaded.push( photo );
				if ( onFileChange ) {
					onFileChange( [ ...uploaded ] );
				} else {
					const next = appendPhotos( blocksRef.current, [ photo ] );
					blocksRef.current = next;
					changeBlocks( next );
					await persist( next );
				}
			}
		} catch ( failure ) {
			setError(
				`${ failure.message } ${ __(
					'Successful uploads are retained. Check Media before retrying an uncertain upload.',
					'personal-stuff'
				) }`
			);
			onError?.( failure );
		} finally {
			setBusy( false );
			setProgress( '' );
		}
	}

	function addUrl() {
		try {
			if ( ! [ 'https:', 'http:' ].includes( new URL( url ).protocol ) ) {
				throw new Error();
			}
			changeBlocks( appendPhotos( blocks, [ { url } ] ) );
			setUrl( '' );
			setError( '' );
		} catch {
			setError(
				__(
					'Enter an absolute HTTP or HTTPS photo URL.',
					'personal-stuff'
				)
			);
		}
	}
	const close = () => ( dirty ? setConfirmClose( true ) : onClose() );
	let saveStatus = saved.id
		? __( 'Saved', 'personal-stuff' )
		: __( 'Not saved yet', 'personal-stuff' );
	if ( ! vocabularyReady ) {
		saveStatus = __( 'Loading item options…', 'personal-stuff' );
	}
	if ( dirty ) {
		saveStatus = vocabularyReady
			? __( 'Unsaved changes', 'personal-stuff' )
			: __( 'Loading item options…', 'personal-stuff' );
	}
	return (
		<Modal
			title={
				isNew
					? __( 'Add item', 'personal-stuff' )
					: __( 'Edit item', 'personal-stuff' )
			}
			className="stuff-editor-modal"
			onRequestClose={ busy ? () => {} : close }
			shouldCloseOnClickOutside={ false }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ currentPlaces.length > 1 && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This item has multiple places. Saving will use the place selected below.',
						'personal-stuff'
					) }
				</Notice>
			) }
			<fieldset aria-busy={ busy } className="stuff-item-fields">
				<TextControl
					className="stuff-item-name"
					placeholder={ __(
						'What are you putting away?',
						'personal-stuff'
					) }
					label={ __( 'Name', 'personal-stuff' ) }
					value={ name }
					onChange={ ( value ) => {
						nameRef.current = value;
						setName( value );
						revisionRef.current += 1;
						setDirty( true );
					} }
				/>
				<SelectControl
					label={ __( 'Place', 'personal-stuff' ) }
					value={ place }
					onChange={ ( value ) => {
						initialPlaceApplied.current = true;
						placeRef.current = value;
						setPlace( value );
						revisionRef.current += 1;
						setDirty( true );
					} }
					options={ [
						{
							value: '',
							label: __( 'Unplaced', 'personal-stuff' ),
						},
						...vocabulary.places.map( ( term ) => ( {
							value: String( term.id ),
							label: vocabulary.path( term ),
						} ) ),
					] }
				/>
				<details>
					<summary>{ __( 'Tags', 'personal-stuff' ) }</summary>
					{ vocabulary.tags.map( ( tag ) => (
						<CheckboxControl
							key={ tag.id }
							label={ tag.name }
							aria-label={ tag.name }
							checked={ tags.includes( tag.id ) }
							onChange={ ( checked ) => {
								const nextTags = checked
									? [ ...tags, tag.id ]
									: tags.filter( ( id ) => id !== tag.id );
								tagsRef.current = nextTags;
								setTags( nextTags );
								revisionRef.current += 1;
								setDirty( true );
							} }
						/>
					) ) }
				</details>
				<div className="stuff-toolbar stuff-photo-actions">
					{ settings.canUpload && (
						<label
							className="stuff-file"
							htmlFor="stuff-photo-upload"
						>
							<Icon icon={ imageIcon } size={ 28 } />
							<strong>
								{ __( 'Add photos', 'personal-stuff' ) }
							</strong>
							<span>
								{ __(
									'Take a photo or choose from your library',
									'personal-stuff'
								) }
							</span>
							<input
								id="stuff-photo-upload"
								type="file"
								accept="image/*"
								multiple
								disabled={ busy || ! vocabularyReady }
								onChange={ ( event ) => {
									upload( event.target.files );
									event.target.value = '';
								} }
							/>
						</label>
					) }
				</div>
				<details>
					<summary>
						{ __( 'More photo options', 'personal-stuff' ) }
					</summary>
					{ saved.id > 0 && (
						<Button
							href={ editorUrl( saved ) }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Open in Gutenberg', 'personal-stuff' ) }
						</Button>
					) }
					<p className="stuff-hint">
						{ __(
							'Photos use public, hard-to-guess Media URLs. Reorder image blocks to choose the cover. Removing a block keeps its Media file.',
							'personal-stuff'
						) }
					</p>
					<TextControl
						label={ __( 'Photo URL', 'personal-stuff' ) }
						value={ url }
						onChange={ setUrl }
					/>
					<Button
						variant="secondary"
						onClick={ addUrl }
						disabled={ ! url }
					>
						{ __( 'Add URL photo', 'personal-stuff' ) }
					</Button>
				</details>
			</fieldset>

			<SlotFillProvider>
				<BlockEditorProvider
					value={ blocks }
					onInput={ changeBlocks }
					onChange={ changeBlocks }
					settings={ {
						hasFixedToolbar: false,
						mediaUpload:
							settings.canUpload && vocabularyReady
								? ( { filesList, onFileChange, onError } ) =>
										upload(
											filesList,
											onFileChange,
											onError
										)
								: undefined,
					} }
				>
					<div className="stuff-toolbar stuff-content-toolbar">
						<strong>
							{ __( 'Notes & photos', 'personal-stuff' ) }
						</strong>
						<Inserter />
						<Button
							aria-pressed={ inspect }
							onClick={ () => setInspect( ! inspect ) }
						>
							{ __( 'Block settings', 'personal-stuff' ) }
						</Button>
					</div>
					<div className="stuff-block-layout">
						<div className="stuff-blocks editor-styles-wrapper">
							<BlockTools>
								<WritingFlow>
									<ObserveTyping>
										<BlockList />
									</ObserveTyping>
								</WritingFlow>
							</BlockTools>
						</div>
						{ inspect && (
							<aside>
								<BlockInspector />
							</aside>
						) }
					</div>
					<Popover.Slot />
				</BlockEditorProvider>
			</SlotFillProvider>
			<div className="stuff-editor-footer">
				<div className="stuff-save-status" role="status">
					{ busy
						? progress || __( 'Saving item…', 'personal-stuff' )
						: saveStatus }
				</div>
				<div className="stuff-toolbar">
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || ! vocabularyReady }
						onClick={ save }
					>
						{ __( 'Save item', 'personal-stuff' ) }
					</Button>
					<Button disabled={ busy } onClick={ close }>
						{ __( 'Close', 'personal-stuff' ) }
					</Button>
				</div>
			</div>
			{ confirmClose && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'personal-stuff' ) }
					onRequestClose={ () => setConfirmClose( false ) }
				>
					<p>
						{ __(
							'Uploaded photos and previously saved changes will remain.',
							'personal-stuff'
						) }
					</p>
					<Button variant="primary" onClick={ onClose }>
						{ __( 'Discard changes', 'personal-stuff' ) }
					</Button>
					<Button onClick={ () => setConfirmClose( false ) }>
						{ __( 'Keep editing', 'personal-stuff' ) }
					</Button>
				</Modal>
			) }
		</Modal>
	);
}

function InlineDescription( { item, onSaved } ) {
	const [ editing, setEditing ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const session = useMemo(
		() => contentSession( item.content.raw ),
		[ item.content.raw ]
	);
	const paragraph = session.blocks.find(
		( block ) => block.name === 'core/paragraph'
	);
	const [ text, setText ] = useState( '' );
	async function save() {
		setBusy( true );
		try {
			const current = await apiFetch( {
				path: `${ settings.knowledgeRestPath }/${ item.id }?context=edit`,
			} );
			if ( current.content.raw !== item.content.raw ) {
				throw new Error(
					__(
						'Content changed elsewhere. Refresh before editing.',
						'personal-stuff'
					)
				);
			}
			const changed = paragraph
				? {
						...paragraph,
						attributes: { ...paragraph.attributes, content: text },
				  }
				: createBlock( 'core/paragraph', { content: text } );
			const blocks = paragraph
				? session.blocks.map( ( block ) =>
						block === paragraph ? changed : block
				  )
				: [ changed, ...session.blocks ];
			const result = await apiFetch( {
				path: `${ settings.knowledgeRestPath }/${ item.id }`,
				method: 'POST',
				data: { content: session.serialize( blocks ) },
			} );
			onSaved( result );
			setEditing( false );
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	}
	return (
		<div className="stuff-inline-description">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ editing ? (
				<>
					<RichText
						tagName="p"
						value={ text }
						onChange={ setText }
						aria-label={ __( 'Description', 'personal-stuff' ) }
					/>
					<div className="stuff-toolbar">
						<Button
							variant="primary"
							disabled={ busy }
							onClick={ save }
						>
							{ __( 'Save description', 'personal-stuff' ) }
						</Button>
						<Button
							disabled={ busy }
							onClick={ () => setEditing( false ) }
						>
							{ __( 'Cancel', 'personal-stuff' ) }
						</Button>
					</div>
				</>
			) : (
				<Button
					onClick={ () => {
						setText(
							String( paragraph?.attributes.content || '' )
						);
						setEditing( true );
					} }
				>
					{ __( 'Edit description', 'personal-stuff' ) }
				</Button>
			) }
		</div>
	);
}

function TermEditor( { vocabulary, onChanged, onClose } ) {
	const [ selected, setSelected ] = useState( '' );
	const [ kind, setKind ] = useState( 'stuff-places' );
	const [ name, setName ] = useState( '' );
	const [ slug, setSlug ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ parent, setParent ] = useState( vocabulary.bySlug.get( kind ).id );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const branch =
		kind === 'stuff-places' ? vocabulary.places : vocabulary.tags;
	function choose( id ) {
		const term = vocabulary.byId.get( Number( id ) );
		setSelected( id );
		setName( term?.name || '' );
		setSlug( term?.slug || '' );
		setDescription( term?.description || '' );
		setParent( term?.parent || vocabulary.bySlug.get( kind ).id );
	}
	async function mutate( remove = false ) {
		setBusy( true );
		setError( '' );
		try {
			await apiFetch( {
				path: `${ settings.taxonomyRestPath }${
					selected ? `/${ selected }` : ''
				}`,
				method: remove ? 'DELETE' : 'POST',
				data: remove
					? { force: true }
					: {
							name,
							...( slug ? { slug } : {} ),
							description,
							parent: Number( parent ),
					  },
			} );
			await onChanged();
			choose( '' );
			setDeleting( false );
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	}
	return (
		<Modal
			title={ __( 'Places and tags', 'personal-stuff' ) }
			onRequestClose={ busy ? () => {} : onClose }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<fieldset disabled={ busy }>
				<SelectControl
					label={ __( 'Branch', 'personal-stuff' ) }
					value={ kind }
					options={ [
						{
							value: 'stuff-places',
							label: __( 'Places', 'personal-stuff' ),
						},
						{
							value: 'stuff-tags',
							label: __( 'Tags', 'personal-stuff' ),
						},
					] }
					onChange={ ( value ) => {
						setKind( value );
						setSelected( '' );
						setName( '' );
						setSlug( '' );
						setDescription( '' );
						setParent( vocabulary.bySlug.get( value ).id );
					} }
				/>
				<SelectControl
					label={ __( 'Term', 'personal-stuff' ) }
					value={ selected }
					onChange={ choose }
					options={ [
						{
							value: '',
							label: __( 'Create new', 'personal-stuff' ),
						},
						...branch.map( ( term ) => ( {
							value: String( term.id ),
							label:
								kind === 'stuff-places'
									? vocabulary.path( term )
									: term.name,
						} ) ),
					] }
				/>
				<TextControl
					label={ __( 'Name', 'personal-stuff' ) }
					value={ name }
					onChange={ setName }
				/>
				<TextControl
					label={ __( 'Slug', 'personal-stuff' ) }
					value={ slug }
					onChange={ setSlug }
					help={ __(
						'Leave empty to generate from the name.',
						'personal-stuff'
					) }
				/>
				<TextareaControl
					label={ __( 'Description', 'personal-stuff' ) }
					value={ description }
					onChange={ setDescription }
				/>
				<SelectControl
					label={ __( 'Parent', 'personal-stuff' ) }
					value={ String( parent ) }
					onChange={ setParent }
					options={ [
						{
							value: String( vocabulary.bySlug.get( kind ).id ),
							label: __( 'Top of branch', 'personal-stuff' ),
						},
						...branch
							.filter(
								( term ) =>
									! ancestors( term, vocabulary.byId ).some(
										( ancestor ) =>
											ancestor.id === Number( selected )
									)
							)
							.map( ( term ) => ( {
								value: String( term.id ),
								label:
									kind === 'stuff-places'
										? vocabulary.path( term )
										: term.name,
							} ) ),
					] }
				/>
				<div className="stuff-toolbar">
					<Button
						variant="primary"
						disabled={ ! name.trim() || busy }
						onClick={ () => mutate() }
					>
						{ __( 'Save term', 'personal-stuff' ) }
					</Button>
					{ selected && (
						<Button
							isDestructive
							onClick={ () => setDeleting( true ) }
						>
							{ __( 'Delete term', 'personal-stuff' ) }
						</Button>
					) }
				</div>
			</fieldset>
			{ deleting && (
				<Modal
					title={ __( 'Delete term?', 'personal-stuff' ) }
					onRequestClose={ () => setDeleting( false ) }
				>
					<p>
						{ __(
							'WordPress removes this assignment from items and reparents its children. Items and photos remain.',
							'personal-stuff'
						) }
					</p>
					<Button
						isDestructive
						disabled={ busy }
						onClick={ () => mutate( true ) }
					>
						{ __( 'Delete term', 'personal-stuff' ) }
					</Button>
				</Modal>
			) }
		</Modal>
	);
}

function Stuff() {
	const [ items, setItems ] = useState( [] );
	const [ terms, setTerms ] = useState( [] );
	const vocabulary = useVocabulary( terms );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ managing, setManaging ] = useState( false );
	const [ addingId, setAddingId ] = useState( null );
	const [ showFilters, setShowFilters ] = useState( false );
	const [ route, setRoute ] = useState(
		() => new URLSearchParams( window.location.search )
	);
	const [ printing, setPrinting ] = useState( false );
	const [ view, setView ] = useState( {
		type: 'grid',
		page: 1,
		perPage: 24,
		titleField: 'name',
		mediaField: 'cover',
		descriptionField: 'location',
		fields: [ 'tags' ],
		layout: {},
		search: '',
	} );
	const touch = useRef( null );
	const loadMore = useRef( null );
	const detail = items.find(
		( item ) => item.id === Number( route.get( 'item' ) )
	);
	const search = route.get( 'q' ) || '';
	const place = route.get( 'place' ) || '';
	const tag = route.get( 'tag' ) || '';
	const photo = route.get( 'photo' ) || 'all';
	function navigate( changes, replace = false ) {
		const next = new URLSearchParams( window.location.search );
		Object.entries( changes ).forEach( ( [ key, value ] ) =>
			value ? next.set( key, value ) : next.delete( key )
		);
		window.history[ replace ? 'replaceState' : 'pushState' ](
			{},
			'',
			`${ window.location.pathname }${ next.size ? `?${ next }` : '' }`
		);
		setRoute( next );
		if (
			Object.keys( changes ).some( ( key ) =>
				[ 'q', 'place', 'tag', 'photo' ].includes( key )
			)
		) {
			setView( ( current ) => ( {
				...current,
				page: 1,
				perPage: 24,
			} ) );
		}
	}
	async function load() {
		setLoading( true );
		setError( '' );
		try {
			const fetchedTerms = await allPages( settings.taxonomyRestPath, {
				hide_empty: false,
			} );
			const identity = fetchedTerms.find(
				( term ) => term.slug === 'stuff-item'
			);
			if (
				! identity ||
				! [ 'artifact', 'stuff', 'stuff-places', 'stuff-tags' ].every(
					( slug ) =>
						fetchedTerms.some( ( term ) => term.slug === slug )
				)
			) {
				throw new Error(
					__(
						'Stuff Knowledge Types are missing. Reload after Knowledge is available.',
						'personal-stuff'
					)
				);
			}
			setTerms( fetchedTerms );
			const fetchedItems = await allPages( settings.knowledgeRestPath, {
				context: 'edit',
				status: [ 'private', 'publish', 'draft', 'future', 'pending' ],
				[ settings.taxonomyField ]: [ identity.id ],
			} );
			setItems( fetchedItems );
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setLoading( false );
		}
	}
	useEffect( () => {
		load();
		const pop = () =>
			setRoute( new URLSearchParams( window.location.search ) );
		window.addEventListener( 'popstate', pop );
		return () => window.removeEventListener( 'popstate', pop );
	}, [] );
	const projected = useMemo(
		() =>
			items.map( ( item ) => {
				const content = itemContent(
					item.content.rendered || item.content.raw
				);
				const assigned = termIds( item )
					.map( ( id ) => vocabulary.byId.get( id ) )
					.filter( Boolean );
				const locations = assigned.filter( ( term ) =>
					vocabulary.below( term, 'stuff-places' )
				);
				return {
					...item,
					...content,
					name: title( item ),
					tags: assigned
						.filter( ( term ) =>
							vocabulary.below( term, 'stuff-tags' )
						)
						.map( ( term ) => term.name ),
					location: locations.map( vocabulary.path ).join( '; ' ),
					placeId: locations[ 0 ]?.id,
					photoCount: content.photos.length,
				};
			} ),
		[ items, vocabulary ]
	);
	const filtered = useMemo( () => {
		const index = new SearchIndex();
		index.rebuild(
			projected.filter(
				( item ) =>
					( ! tag ||
						termIds( item ).includes(
							vocabulary.bySlug.get( tag )?.id
						) ) &&
					( ! place ||
						( place === 'unplaced'
							? ! item.placeId
							: termIds( item ).some( ( id ) =>
									ancestors(
										vocabulary.byId.get( id ),
										vocabulary.byId
									).some(
										( ancestor ) => ancestor.slug === place
									)
							  ) ) )
			)
		);
		return index.search( search, { photo } );
	}, [ projected, vocabulary, search, photo, place, tag ] );
	const fields = useMemo(
		() => [
			{
				id: 'name',
				label: __( 'Name', 'personal-stuff' ),
				type: 'text',
				getValue: ( { item } ) => item.name,
				enableSorting: true,
				enableHiding: false,
			},
			{
				id: 'cover',
				label: __( 'Photo', 'personal-stuff' ),
				render: ( { item } ) => <Photo { ...item.photos[ 0 ] } />,
				enableSorting: false,
			},
			{
				id: 'location',
				label: __( 'Place', 'personal-stuff' ),
				type: 'text',
				getValue: ( { item } ) =>
					item.location || __( 'Unplaced', 'personal-stuff' ),
				enableSorting: true,
			},
			{
				id: 'tags',
				label: __( 'Tags', 'personal-stuff' ),
				type: 'text',
				getValue: ( { item } ) => item.tags.join( ', ' ),
				enableSorting: false,
			},
		],
		[]
	);
	const { data, paginationInfo } = filterSortAndPaginate(
		filtered,
		view,
		fields
	);
	// Detail navigation and printing use the entire filtered/sorted set, not a page.
	const ordered = filterSortAndPaginate(
		filtered,
		{ ...view, page: 1, perPage: Math.max( filtered.length, 1 ) },
		fields
	).data;
	useEffect( () => {
		if ( loading || data.length >= filtered.length || ! loadMore.current ) {
			return;
		}
		const observer = new window.IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					setView( ( current ) => ( {
						...current,
						page: 1,
						perPage: Math.min(
							( current.perPage || 24 ) + 24,
							filtered.length
						),
					} ) );
				}
			},
			{ rootMargin: '400px 0px' }
		);
		observer.observe( loadMore.current );
		return () => observer.disconnect();
	}, [ data.length, filtered.length, loading ] );
	const position = ordered.findIndex( ( item ) => item.id === detail?.id );
	function step( offset ) {
		const next = ordered[ position + offset ];
		if ( next ) {
			navigate( { item: next.id } );
		}
	}
	function createItem() {
		const ids = [ 'artifact', 'stuff-item' ]
			.map( ( slug ) => vocabulary.bySlug.get( slug )?.id )
			.filter( Boolean );
		if ( place && place !== 'unplaced' && vocabulary.bySlug.has( place ) ) {
			ids.push( vocabulary.bySlug.get( place ).id );
		}
		setAddingId( 0 );
		setEditing( {
			id: 0,
			title: { raw: '', rendered: '' },
			content: { raw: '', rendered: '' },
			modified_gmt: '',
			[ settings.taxonomyField ]: ids,
		} );
	}
	function onSaved( item ) {
		setItems( ( current ) =>
			current.some( ( row ) => row.id === item.id )
				? current.map( ( row ) => ( row.id === item.id ? item : row ) )
				: [ item, ...current ]
		);
	}
	useEffect( () => {
		if ( ! printing ) {
			return;
		}
		let cancelled = false;
		let timeout;
		const finish = () => setPrinting( false );
		window.addEventListener( 'afterprint', finish );
		Promise.race( [
			Promise.all(
				Array.from(
					document.querySelectorAll( '.stuff-print img' )
				).map( ( image ) => {
					image.loading = 'eager';
					return image.decode().catch( () => {} );
				} )
			),
			new Promise( ( resolve ) => {
				timeout = window.setTimeout( resolve, 10000 );
			} ),
		] ).then( () => {
			window.clearTimeout( timeout );
			if ( ! cancelled ) {
				window.print();
				setPrinting( false );
			}
		} );
		return () => {
			cancelled = true;
			window.clearTimeout( timeout );
			window.removeEventListener( 'afterprint', finish );
		};
	}, [ printing ] );
	return (
		<main className="personal-stuff">
			<header className="stuff-header">
				<div>
					<p className="stuff-eyebrow">
						{ __( 'PERSONAL INVENTORY', 'personal-stuff' ) }
					</p>
					<h1>{ __( 'Stuff', 'personal-stuff' ) }</h1>
					<p>
						{ __( 'Everything in its place.', 'personal-stuff' ) }
					</p>
				</div>
				<div className="stuff-toolbar">
					<Button
						icon={ update }
						label={ __( 'Refresh', 'personal-stuff' ) }
						className="stuff-refresh"
						onClick={ load }
						disabled={ loading }
					></Button>
					{ settings.canManageTerms && (
						<Button
							variant="secondary"
							icon={ category }
							className="stuff-manage"
							label={ __( 'Places & tags', 'personal-stuff' ) }
							disabled={
								loading ||
								! vocabulary.bySlug.has( 'stuff-places' )
							}
							onClick={ () => setManaging( true ) }
						>
							<span>
								{ __( 'Places & tags', 'personal-stuff' ) }
							</span>
						</Button>
					) }
					<Button
						variant="primary"
						icon={ plus }
						onClick={ createItem }
					>
						{ __( 'Add item', 'personal-stuff' ) }
					</Button>
				</div>
			</header>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="stuff-search-row">
				<Icon icon={ searchIcon } />
				<TextControl
					hideLabelFromVision
					label={ __( 'Search belongings', 'personal-stuff' ) }
					placeholder={ __( 'Find something…', 'personal-stuff' ) }
					value={ search }
					onChange={ ( value ) =>
						navigate( { q: value, item: '' }, true )
					}
				/>
				<Button
					icon={ funnel }
					aria-expanded={ showFilters }
					aria-controls="stuff-filters"
					onClick={ () => setShowFilters( ! showFilters ) }
				>
					{ __( 'Filters', 'personal-stuff' ) }
					{ ( place || tag || photo !== 'all' ) && (
						<span className="stuff-filter-dot" />
					) }
				</Button>
			</div>
			<div
				className="stuff-filters"
				id="stuff-filters"
				hidden={ ! showFilters }
			>
				<SelectControl
					label={ __( 'Place and descendants', 'personal-stuff' ) }
					value={ place }
					onChange={ ( value ) =>
						navigate( { place: value, item: '' } )
					}
					options={ [
						{
							value: '',
							label: __( 'All places', 'personal-stuff' ),
						},
						{
							value: 'unplaced',
							label: __( 'Unplaced', 'personal-stuff' ),
						},
						...vocabulary.places.map( ( term ) => ( {
							value: term.slug,
							label: vocabulary.path( term ),
						} ) ),
					] }
				/>
				<SelectControl
					label={ __( 'Tag', 'personal-stuff' ) }
					value={ tag }
					onChange={ ( value ) =>
						navigate( { tag: value, item: '' } )
					}
					options={ [
						{
							value: '',
							label: __( 'All tags', 'personal-stuff' ),
						},
						...vocabulary.tags.map( ( term ) => ( {
							value: term.slug,
							label: term.name,
						} ) ),
					] }
				/>
				<SelectControl
					label={ __( 'Photos', 'personal-stuff' ) }
					value={ photo }
					onChange={ ( value ) =>
						navigate( { photo: value, item: '' } )
					}
					options={ [
						{
							value: 'all',
							label: __( 'All items', 'personal-stuff' ),
						},
						{
							value: 'with',
							label: __( 'With photos', 'personal-stuff' ),
						},
						{
							value: 'without',
							label: __( 'Without photos', 'personal-stuff' ),
						},
					] }
				/>
			</div>
			<nav
				className="stuff-places"
				aria-label={ __( 'Browse places', 'personal-stuff' ) }
			>
				<a
					href={ addQueryArgs( window.location.pathname, {
						place: '',
						q: search,
						tag,
						photo,
					} ) }
					aria-current={ ! place ? 'location' : undefined }
					onClick={ ( event ) => {
						if (
							event.button === 0 &&
							! event.metaKey &&
							! event.ctrlKey &&
							! event.shiftKey &&
							! event.altKey
						) {
							event.preventDefault();
							navigate( { place: '', item: '' } );
						}
					} }
				>
					{ __( 'All stuff', 'personal-stuff' ) }
				</a>
				{ ancestors( vocabulary.bySlug.get( place ), vocabulary.byId )
					.filter( ( term ) =>
						vocabulary.below( term, 'stuff-places' )
					)
					.map( ( term ) => (
						<span className="stuff-place-crumb" key={ term.id }>
							<Icon icon={ chevronRight } size={ 16 } />
							<Button
								aria-current={
									term.slug === place ? 'location' : undefined
								}
								onClick={ () =>
									navigate( { place: term.slug, item: '' } )
								}
							>
								{ term.name }
							</Button>
						</span>
					) ) }
				<span className="stuff-place-children">
					{ vocabulary.places
						.filter(
							( term ) =>
								term.parent ===
								( vocabulary.bySlug.get( place )?.id ||
									vocabulary.bySlug.get( 'stuff-places' )
										?.id )
						)
						.map( ( term ) => (
							<Button
								key={ term.id }
								onClick={ () =>
									navigate( { place: term.slug, item: '' } )
								}
							>
								{ term.name }
								<Icon icon={ chevronRight } size={ 16 } />
							</Button>
						) ) }
				</span>
			</nav>

			<DataViews
				header={
					<div className="stuff-toolbar stuff-count">
						<span>
							{ sprintf(
								// translators: %d: number of matching inventory items.
								_n(
									'%d item',
									'%d items',
									filtered.length,
									'personal-stuff'
								),
								filtered.length
							) }
						</span>
						<Button
							className="stuff-clear-filters"
							disabled={
								! search && ! place && ! tag && photo === 'all'
							}
							onClick={ () =>
								navigate( {
									q: '',
									place: '',
									tag: '',
									photo: '',
									item: '',
								} )
							}
						>
							{ __( 'Clear filters', 'personal-stuff' ) }
						</Button>
						<Button
							disabled={ loading || printing }
							onClick={ () => setPrinting( true ) }
						>
							{ printing
								? __( 'Preparing…', 'personal-stuff' )
								: __( 'Print / PDF', 'personal-stuff' ) }
						</Button>
					</div>
				}
				data={ data }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				paginationInfo={ paginationInfo }
				defaultLayouts={ {
					grid: { layout: {} },
					list: {},
					table: {},
				} }
				getItemId={ ( item ) => String( item.id ) }
				isLoading={ loading }
				search={ false }
				actions={ [
					{
						id: 'view',
						label: __( 'View item', 'personal-stuff' ),
						icon: viewIcon,
						isPrimary: true,
						callback: ( rows ) =>
							navigate( { item: rows[ 0 ].id } ),
					},
					{
						id: 'edit',
						label: __( 'Edit item', 'personal-stuff' ),
						icon: edit,
						callback: ( rows ) => setEditing( rows[ 0 ] ),
					},
				] }
				onClickItem={ ( item ) => navigate( { item: item.id } ) }
				isItemClickable={ () => true }
			/>
			{ data.length < filtered.length && (
				<div
					ref={ loadMore }
					className="stuff-load-more"
					aria-hidden="true"
				>
					<Spinner />
				</div>
			) }
			{ loading && <Spinner /> }
			{ ! loading && route.has( 'item' ) && ! detail && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This item is unavailable. It may have been trashed or you may not have access.',
						'personal-stuff'
					) }
				</Notice>
			) }
			{ detail && ! editing && (
				<Modal
					title={ title( detail ) }
					className="stuff-detail-modal"
					onRequestClose={ () => navigate( { item: '' } ) }
				>
					<div className="stuff-toolbar">
						<Button
							disabled={ position <= 0 }
							onClick={ () => step( -1 ) }
						>
							{ __( 'Previous', 'personal-stuff' ) }
						</Button>
						<span>
							{ position >= 0
								? `${ position + 1 } / ${ ordered.length }`
								: __(
										'Outside current filter',
										'personal-stuff'
								  ) }
						</span>
						<Button
							disabled={
								position < 0 || position >= ordered.length - 1
							}
							onClick={ () => step( 1 ) }
						>
							{ __( 'Next', 'personal-stuff' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => setEditing( detail ) }
						>
							{ __( 'Edit item', 'personal-stuff' ) }
						</Button>
						<Button href={ editorUrl( detail ) }>
							{ __( 'Gutenberg', 'personal-stuff' ) }
						</Button>
					</div>
					<div className="stuff-toolbar">
						{ termIds( detail )
							.map( ( id ) => vocabulary.byId.get( id ) )
							.filter(
								( term ) =>
									term &&
									( vocabulary.below(
										term,
										'stuff-places'
									) ||
										vocabulary.below( term, 'stuff-tags' ) )
							)
							.map( ( term ) => (
								<Button
									key={ term.id }
									variant="tertiary"
									onClick={ () =>
										navigate( {
											item: '',
											[ vocabulary.below(
												term,
												'stuff-places'
											)
												? 'place'
												: 'tag' ]: term.slug,
										} )
									}
								>
									{ vocabulary.below( term, 'stuff-places' )
										? vocabulary.path( term )
										: term.name }
								</Button>
							) ) }
					</div>
					<InlineDescription
						key={ detail.id }
						item={ detail }
						onSaved={ onSaved }
					/>
					<div
						className="stuff-detail-content"
						onErrorCapture={ ( event ) => {
							if ( event.target.tagName === 'IMG' ) {
								event.target.alt = __(
									'Photo unavailable. Edit this item to update its image source.',
									'personal-stuff'
								);
							}
						} }
						onTouchStart={ ( event ) => {
							touch.current = [
								event.touches[ 0 ].clientX,
								event.touches[ 0 ].clientY,
							];
						} }
						onTouchEnd={ ( event ) => {
							if ( ! touch.current ) {
								return;
							}
							const dx =
								event.changedTouches[ 0 ].clientX -
								touch.current[ 0 ];
							const dy =
								event.changedTouches[ 0 ].clientY -
								touch.current[ 1 ];
							if ( Math.abs( dx ) > 90 && Math.abs( dy ) < 50 ) {
								step( dx < 0 ? 1 : -1 );
							}
							touch.current = null;
						} }
					>
						<RawHTML>{ detail.content.rendered }</RawHTML>
					</div>
				</Modal>
			) }
			{ editing && (
				<ItemEditor
					key={ editing.id }
					item={ editing }
					isNew={ editing.id === addingId }
					initialPlaceSlug={ place }
					vocabulary={ vocabulary }
					onSaved={ onSaved }
					onClose={ () => {
						setEditing( null );
						setAddingId( null );
					} }
				/>
			) }
			{ managing && (
				<TermEditor
					vocabulary={ vocabulary }
					onChanged={ load }
					onClose={ () => setManaging( false ) }
				/>
			) }
			{ printing && (
				<section className="stuff-print">
					<h1>{ __( 'Stuff', 'personal-stuff' ) }</h1>
					<p>
						{ [ search, place, tag, photo === 'all' ? '' : photo ]
							.filter( Boolean )
							.join( ' · ' ) }
					</p>
					{ ordered.map( ( item ) => (
						<article key={ item.id }>
							<h2>{ item.name }</h2>
							<p>
								{ item.location } { item.tags.join( ', ' ) }
							</p>
							<RawHTML>{ item.content.rendered }</RawHTML>
						</article>
					) ) }
				</section>
			) }
			<footer className="stuff-hint">
				<a href={ settings.skillUrl }>
					{ __( 'Operating skill', 'personal-stuff' ) }
				</a>
			</footer>
		</main>
	);
}

const mount = document.getElementById( 'personal-stuff-app' );
if ( mount ) {
	createRoot( mount ).render( <Stuff /> );
}
