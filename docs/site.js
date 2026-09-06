const tabs = [ ...document.querySelectorAll( '[role="tab"]' ) ];
const captions = {
	notes: 'Your notes, organized with Knowledge Types and the native WordPress DataViews interface.',
	todo: 'A focused task list with the scheduling, recurrence, and Knowledge Types behind your everyday work.',
	stuff: 'Your belongings, organized by place and tag. This filtered view contains only synthetic demo items.',
};

function selectTab( tab ) {
	for ( const item of tabs ) {
		const selected = item === tab;
		item.setAttribute( 'aria-selected', String( selected ) );
		item.tabIndex = selected ? 0 : -1;
		document.getElementById( item.getAttribute( 'aria-controls' ) ).hidden =
			! selected;
	}
	const app = tab.id.replace( 'tab-', '' );
	document.getElementById( 'preview-path' ).textContent = `/${ app }/`;
	document.getElementById( 'preview-caption' ).textContent = captions[ app ];
}

for ( const tab of tabs ) {
	tab.addEventListener( 'click', () => selectTab( tab ) );
	tab.addEventListener( 'keydown', ( event ) => {
		let index = tabs.indexOf( tab );
		if ( event.key === 'ArrowRight' ) {
			index = ( index + 1 ) % tabs.length;
		} else if ( event.key === 'ArrowLeft' ) {
			index = ( index - 1 + tabs.length ) % tabs.length;
		} else if ( event.key === 'Home' ) {
			index = 0;
		} else if ( event.key === 'End' ) {
			index = tabs.length - 1;
		} else {
			return;
		}
		event.preventDefault();
		selectTab( tabs[ index ] );
		tabs[ index ].focus();
	} );
}
