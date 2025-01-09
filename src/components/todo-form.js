import {
	Card,
	CardBody,
	CardFooter,
	__experimentalInputControl as InputControl,
	TextareaControl,
	PanelBody,
	Panel,
	PanelRow,
	DatePicker,
	Button,
	ComboboxControl,
	__experimentalInputControlPrefixWrapper as InputControlPrefixWrapper,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import {
	useEntityRecords,
	store as coreStore,
} from '@wordpress/core-data';
import { check, close } from '@wordpress/icons';
import NotebookSelectorTabPanel from './notebook-selector-tab-panel';
import { getNotebook } from '../utils/notebook';

export default function TodoForm( {
	presetNotebooks = [],
	possibleFlags = [],
	nowNotebook = null,
} ) {
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
			notebook: presetNotebooks, //newTodo.notebook.concat( presetNotebooks ),
		} );
	}, [ presetNotebooks ] );

	const { saveEntityRecord } = useDispatch( coreStore );
	const { records: notebooks } = useEntityRecords( 'taxonomy', 'notebook', {
		per_page: -1,
		hide_empty: false,
	} );
	const { records: todos } = useEntityRecords( 'postType', 'todo', {
		per_page: -1,
		context: 'edit',
		status: [ 'publish', 'pending', 'future', 'private' ],
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
					placeholder={
						'New TODO in ' +
						( notebooks
							? notebooks
									.filter( ( notebook ) =>
										presetNotebooks.includes( notebook.id )
									)
									.map( ( notebook ) => notebook.name )
									.join( ', ' )
							: 'Inbox' )
					}
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
								setNewTodo( {
									...newTodo,
									meta: { ...newTodo.meta, url: value },
								} )
							}
							label={ 'Action URL' }
							onValidate={ () => true }
							value={ newTodo.meta?.url }
							help={
								'A URL associated with the task. For example tel://500500500 or http://website-of-thing-to-buy'
							}
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
								title="Schedule task"
								initialOpen={ false }
								onToggle={ ( open ) =>
									setNewTodo( {
										...newTodo,
										date: open ? newTodo.date : undefined,
									} )
								}
							>
								<PanelRow>
									<DatePicker
										label="Show on"
										currentDate={
											newTodo.date
												? new Date( newTodo.date )
												: new Date()
										}
										onChange={ ( value ) => {
											const newData = {
												...newTodo,
												date: value,
											};
											if (
												! newData?.meta
													?.pos_blocked_pending_term
											) {
												newData.meta.pos_blocked_pending_term =
													getNotebook(
														nowNotebook,
														notebooks
													)?.slug;
											}
											setNewTodo( newData );
										} }
									/>
								</PanelRow>
								{ newTodo.date && (
									<>
										<PanelRow className="wide">
											<h4>{ `On ${ new Date(
												newTodo.date
											).toLocaleDateString() } move to:` }</h4>
										</PanelRow>
										<PanelRow className="wide">
											<NotebookSelectorTabPanel
												notebooks={ notebooks }
												possibleFlags={ possibleFlags }
												chosenNotebooks={ [
													newTodo.meta
														.pos_blocked_pending_term,
												] }
												setNotebook={ (
													notebook,
													value
												) => {
													setNewTodo( {
														...newTodo,
														meta: {
															...newTodo.meta,
															pos_blocked_pending_term:
																value
																	? notebook.slug
																	: undefined,
														},
													} );
												} }
											/>
										</PanelRow>
									</>
								) }
							</PanelBody>
							<PanelBody
								title="Recurring"
								initialOpen={ false }
								onToggle={ ( open ) => setNewTodo( { ...newTodo, meta: { ...newTodo.meta, pos_recurring_days: open ? newTodo.meta?.pos_recurring_days : undefined } } ) }
							>
								<PanelRow>
									<InputControl
										className="pos__todo-form-recurring"
										__next40pxDefaultSize = { true }
										type="number"
										onChange={ ( value ) =>
											setNewTodo( { ...newTodo, meta: {
												...newTodo.meta,
												pos_recurring_days: value,
												pos_blocked_pending_term: newTodo.meta?.pos_blocked_pending_term || getNotebook( nowNotebook, notebooks )?.slug,
											} } )
										}
										prefix={ <InputControlPrefixWrapper>schedule</InputControlPrefixWrapper> }
										suffix={ <InputControlPrefixWrapper>days after completion</InputControlPrefixWrapper> }
										label={ 'After completion, duplicate this task and' }
										onValidate={ () => true }
										help={ 'When you complete this task, it will be duplicated and scheduled x days from completion time.' }
										value={ newTodo.meta?.pos_recurring_days || 0 }
									/>
								</PanelRow>
								{ newTodo.meta?.pos_recurring_days && (
									<>
										<PanelRow className="wide">
											<h4>{ `${ newTodo.meta?.pos_recurring_days } days after completion, move to:` }</h4>
										</PanelRow>
										<PanelRow className="wide">
											<NotebookSelectorTabPanel
												notebooks={ notebooks }
												possibleFlags={ possibleFlags }
												chosenNotebooks={ [
													newTodo.meta
														.pos_blocked_pending_term,
												] }
												setNotebook={ (
													notebook,
													value
												) => {
													setNewTodo( {
														...newTodo,
														meta: {
															...newTodo.meta,
															pos_blocked_pending_term:
																value
																	? notebook.slug
																	: undefined,
														},
													} );
												} }
											/>
										</PanelRow>
									</>
								) }
							</PanelBody>
							<PanelBody
								title="This TODO depends on"
								initialOpen={ false }
								onToggle={ ( open ) =>
									setNewTodo( {
										...newTodo,
										meta: {
											...newTodo.meta,
											pos_blocked_by: open
												? newTodo.meta?.pos_blocked_by
												: undefined,
										},
									} )
								}
							>
								<PanelRow className="wide">
									<ComboboxControl
										label="This TODO is blocked by"
										value={ newTodo.meta?.pos_blocked_by }
										options={ todos.map( ( todo ) => ( {
											label:
												'#' +
												todo.id +
												' ' +
												todo.title.raw +
												' (' +
												todo.notebook
													.map(
														( n ) =>
															getNotebook(
																n,
																notebooks
															)?.name
													)
													.join( ', ' ) +
												')',
											value: todo.id,
										} ) ) }
										help={
											'Chose a TODO that is blocking the current one.'
										}
										onChange={ ( value ) => {
											const newData = {
												...newTodo,
												meta: {
													...newTodo.meta,
													pos_blocked_by: value,
												},
											};
											if (
												! newData?.meta
													?.pos_blocked_pending_term
											) {
												newData.meta.pos_blocked_pending_term =
													getNotebook(
														nowNotebook,
														notebooks
													)?.slug;
											}
											setNewTodo( newData );
										} }
									/>
								</PanelRow>
								{ newTodo.meta?.pos_blocked_by && (
									<>
										<PanelRow className="wide">
											<h4>After unblocking, move to:</h4>
										</PanelRow>
										<PanelRow className="wide">
											<NotebookSelectorTabPanel
												notebooks={ notebooks }
												possibleFlags={ possibleFlags }
												chosenNotebooks={ [
													newTodo.meta
														?.pos_blocked_pending_term,
												] }
												setNotebook={ (
													notebook,
													value
												) => {
													setNewTodo( {
														...newTodo,
														meta: {
															...newTodo.meta,
															pos_blocked_pending_term:
																value
																	? notebook.slug
																	: undefined,
														},
													} );
												} }
											/>
										</PanelRow>
									</>
								) }
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
								saveEntityRecord( 'postType', 'todo', newTodo );
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