import apiFetch from '@wordpress/api-fetch';
import { createBlock, serialize } from '@wordpress/blocks';
import {
	Button,
	Draggable,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	Tooltip,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginSidebar } from '@wordpress/edit-post';
import { RawHTML, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	chevronDown,
	chevronUp,
	drafts,
	external,
	plus,
} from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';
import { addQueryArgs } from '@wordpress/url';

import { isAssignableKnowledgeType } from './knowledge-types';
import './editor.css';

const settings = window.personalNotesSettings || {};

function getFieldText( field ) {
	return field?.rendered || field?.raw || '';
}

function getTermIds( note ) {
	return note[ settings.taxonomyField ] || [];
}

function NoteResult( { note, onInsert } ) {
	const [ expanded, setExpanded ] = useState( false );
	const title =
		getFieldText( note.title ) || __( 'Untitled Note', 'personal-notes' );
	const excerpt = getFieldText( note.excerpt ).replace( /<[^>]*>/g, '' );
	const block = createBlock( 'pos/note', { note_id: note.id } );
	const editUrl = addQueryArgs( settings.editPostUrl, {
		post: note.id,
		action: 'edit',
	} );

	return (
		<Draggable
			elementId={ `personal-notes-sidebar-note-${ note.id }` }
			__experimentalTransferDataType="wp-blocks"
			transferData={ {} }
			onDragStart={ ( event ) => {
				event.dataTransfer.setData(
					'text/html',
					serialize( [ block ] )
				);
			} }
		>
			{ ( { onDraggableStart, onDraggableEnd } ) => (
				<div
					id={ `personal-notes-sidebar-note-${ note.id }` }
					className="personal-notes-editor-sidebar__result"
					draggable
					onDragStart={ onDraggableStart }
					onDragEnd={ onDraggableEnd }
				>
					<div className="personal-notes-editor-sidebar__result-header">
						<Button
							className="personal-notes-editor-sidebar__title"
							variant="link"
							onClick={ () => setExpanded( ! expanded ) }
						>
							{ title }
						</Button>
						<div className="personal-notes-editor-sidebar__actions">
							<Tooltip
								text={ __( 'Preview note', 'personal-notes' ) }
							>
								<Button
									icon={ expanded ? chevronUp : chevronDown }
									label={ __(
										'Preview note',
										'personal-notes'
									) }
									onClick={ () => setExpanded( ! expanded ) }
								/>
							</Tooltip>
							<Tooltip
								text={ __( 'Insert note', 'personal-notes' ) }
							>
								<Button
									icon={ plus }
									label={ __(
										'Insert note',
										'personal-notes'
									) }
									onClick={ () => onInsert( block ) }
								/>
							</Tooltip>
							<Tooltip
								text={ __( 'Open note', 'personal-notes' ) }
							>
								<Button
									href={ editUrl }
									icon={ external }
									label={ __(
										'Open note',
										'personal-notes'
									) }
									target="_blank"
									rel="noreferrer"
								/>
							</Tooltip>
						</div>
					</div>
					{ excerpt && (
						<p className="personal-notes-editor-sidebar__excerpt">
							{ excerpt }
						</p>
					) }
					{ expanded && (
						<RawHTML className="personal-notes-editor-sidebar__preview">
							{ getFieldText( note.content ) }
						</RawHTML>
					) }
				</div>
			) }
		</Draggable>
	);
}

function NotesSidebar() {
	const [ search, setSearch ] = useState( '' );
	const [ notes, setNotes ] = useState( [] );
	const [ terms, setTerms ] = useState( [] );
	const [ selectedType, setSelectedType ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const currentPostId = useSelect( ( select ) =>
		select( 'core/editor' ).getCurrentPostId()
	);
	const { insertBlock } = useDispatch( 'core/block-editor' );

	const noteTerm = useMemo(
		() => terms.find( ( term ) => term.slug === 'note' ),
		[ terms ]
	);
	const knowledgeTypes = useMemo(
		() => terms.filter( isAssignableKnowledgeType ),
		[ terms ]
	);
	const visibleNotes = useMemo(
		() =>
			notes.filter(
				( note ) =>
					note.id !== currentPostId &&
					( ! selectedType ||
						getTermIds( note ).includes( Number( selectedType ) ) )
			),
		[ currentPostId, notes, selectedType ]
	);

	useEffect( () => {
		let cancelled = false;

		apiFetch( {
			path: addQueryArgs( settings.taxonomyRestPath, {
				context: 'view',
				hide_empty: false,
				per_page: 100,
			} ),
		} )
			.then( ( fetchedTerms ) => {
				if ( ! cancelled ) {
					setTerms( fetchedTerms );
				}
			} )
			.catch( ( fetchError ) => {
				if ( ! cancelled ) {
					setError(
						fetchError.message ||
							__(
								'Could not load Knowledge Types.',
								'personal-notes'
							)
					);
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	useEffect( () => {
		if ( ! noteTerm ) {
			return undefined;
		}

		let cancelled = false;
		const timer = window.setTimeout( () => {
			setLoading( true );
			setError( '' );
			apiFetch( {
				path: addQueryArgs( settings.knowledgeRestPath, {
					context: 'edit',
					status: [ 'private', 'publish', 'future' ],
					per_page: 20,
					orderby: 'modified',
					order: 'desc',
					search,
					[ settings.taxonomyField ]: [ noteTerm.id ],
				} ),
			} )
				.then( ( fetchedNotes ) => {
					if ( ! cancelled ) {
						setNotes( fetchedNotes );
					}
				} )
				.catch( ( fetchError ) => {
					if ( ! cancelled ) {
						setError(
							fetchError.message ||
								__( 'Could not load notes.', 'personal-notes' )
						);
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setLoading( false );
					}
				} );
		}, 300 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ noteTerm, search ] );

	return (
		<PluginSidebar
			name="personal-notes"
			title={ __( 'Notes', 'personal-notes' ) }
			icon={ drafts }
		>
			<div className="personal-notes-editor-sidebar">
				<TextControl
					label={ __( 'Search notes', 'personal-notes' ) }
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Knowledge Type', 'personal-notes' ) }
					value={ selectedType }
					options={ [
						{
							label: __( 'All types', 'personal-notes' ),
							value: '',
						},
						...knowledgeTypes.map( ( term ) => ( {
							label: term.name,
							value: term.id.toString(),
						} ) ),
					] }
					onChange={ setSelectedType }
					__nextHasNoMarginBottom
				/>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ loading ? (
					<div className="personal-notes-editor-sidebar__status">
						<Spinner />
					</div>
				) : (
					<div className="personal-notes-editor-sidebar__results">
						{ visibleNotes.map( ( note ) => (
							<NoteResult
								key={ note.id }
								note={ note }
								onInsert={ insertBlock }
							/>
						) ) }
						{ ! error && visibleNotes.length === 0 && (
							<p className="personal-notes-editor-sidebar__empty">
								{ __( 'No notes found.', 'personal-notes' ) }
							</p>
						) }
					</div>
				) }
			</div>
		</PluginSidebar>
	);
}

registerPlugin( 'personal-notes', { render: NotesSidebar } );
