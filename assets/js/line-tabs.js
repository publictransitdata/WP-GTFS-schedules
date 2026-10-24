/**
 * Lists of lines marked data-gtfs-tabs show one group (transport mode) at a time.
 * Without JavaScript every group stays visible, one under the other.
 *
 * Follows the WAI-ARIA tabs pattern: arrow keys move between tabs.
 */
( function () {
	document.querySelectorAll( '[data-gtfs-tabs]' ).forEach( showGroupsAsTabs );

	function showGroupsAsTabs( list ) {
		const groups = [ ...list.querySelectorAll( ':scope > .gtfs-lines__group' ) ];

		if ( groups.length < 2 ) {
			return;
		}

		const tablist = document.createElement( 'div' );
		tablist.className = 'gtfs-lines__tabs';
		tablist.setAttribute( 'role', 'tablist' );

		const tabs = groups.map( ( group ) => {
			const tab = document.createElement( 'button' );
			tab.type = 'button';
			tab.id = `${ group.id }-tab`;
			tab.className = 'gtfs-lines__tab';
			// The heading's own markup: its transport icon (hidden from screen readers) and its name.
			tab.innerHTML = group.querySelector( '.gtfs-lines__heading' ).innerHTML;
			tab.setAttribute( 'role', 'tab' );
			tab.setAttribute( 'aria-controls', group.id );

			group.setAttribute( 'role', 'tabpanel' );
			group.setAttribute( 'aria-labelledby', tab.id );
			group.tabIndex = 0;

			tablist.append( tab );
			return tab;
		} );

		function select( index ) {
			tabs.forEach( ( tab, tabIndex ) => {
				const selected = tabIndex === index;
				tab.setAttribute( 'aria-selected', String( selected ) );
				tab.tabIndex = selected ? 0 : -1;
				groups[ tabIndex ].hidden = ! selected;
			} );
		}

		tablist.addEventListener( 'click', ( event ) => {
			const index = tabs.indexOf( event.target.closest( '[role="tab"]' ) );

			if ( index >= 0 ) {
				select( index );
			}
		} );

		tablist.addEventListener( 'keydown', ( event ) => {
			const step = { ArrowRight: 1, ArrowLeft: -1 }[ event.key ];

			if ( ! step ) {
				return;
			}

			const index = ( tabs.indexOf( document.activeElement ) + step + tabs.length ) % tabs.length;
			select( index );
			tabs[ index ].focus();
		} );

		list.classList.add( 'is-tabbed' );
		list.prepend( tablist );
		select( 0 );
	}
} )();
