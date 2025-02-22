import {
	__experimentalVStack as VStack,
	CheckboxControl,
	TabPanel,
} from '@wordpress/components';

export default function NotebookSelectorTabPanel( {
	notebooks,
	chosenNotebooks,
	setNotebook,
	possibleFlags,
} ) {
	const tabs = [
		...possibleFlags.map( ( flag ) => ( {
			name: flag.id,
			title: flag.name,
		} ) ),
		{
			name: 'all',
			title: 'All Notebooks',
		},
	];

	const onSelect = ( tabName ) => {
		// Handle tab selection if needed
	};

	const renderTab = ( tab ) => {
		return (
			<div
				style={ {
					maxHeight: '200px',
					overflowY: 'auto',
					padding: '20px',
				} }
			>
				<VStack>
					{ notebooks
						?.filter(
							( notebook ) =>
								tab.name === 'all' ||
								notebook?.meta?.flag?.includes(
									tab.name
								)
						)
						?.map( ( notebook ) => (
							<CheckboxControl
								key={ notebook.id }
								__nextHasNoMarginBottom
								label={ notebook.name }
								checked={
									chosenNotebooks.includes(
										notebook.id
									) ||
									chosenNotebooks.includes(
										notebook.slug
									)
								}
								onChange={ ( value ) =>
									setNotebook( notebook, value )
								}
							/>
						) ) }
				</VStack>
			</div>
		);
	};

	return (
		<TabPanel
			className="pos-notebook-selector"
			activeClass="active-tab"
			onSelect={ onSelect }
			tabs={ tabs }
		>
			{ renderTab }
		</TabPanel>
	);
} 