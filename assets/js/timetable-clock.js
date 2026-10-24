/**
 * Keeps timetables current while the page stays open: departures that have left are greyed out,
 * the next one is highlighted and named in words above the table. The server already marks them
 * when it renders the page; this moves the marks along as time passes, and corrects pages
 * served from a page cache.
 */
( function () {
	const REFRESH_EVERY_MS = 30 * 1000;

	function markDepartures() {
		const now = Date.now();

		document.querySelectorAll( '[data-gtfs-timetable]' ).forEach( ( timetable ) => {
			let next = null;

			timetable.querySelectorAll( 'time[datetime]' ).forEach( ( departure ) => {
				const hasLeft = Date.parse( departure.getAttribute( 'datetime' ) ) < now;
				const isNext = ! hasLeft && ! next;

				departure.classList.toggle( 'has-left', hasLeft );
				departure.classList.toggle( 'is-next', isNext );
				next = isNext ? departure : next;
			} );

			const summary = timetable.querySelector( '[data-gtfs-next-departure]' );

			if ( summary ) {
				summary.hidden = ! next;
				summary.querySelector( 'strong' ).textContent = next ? next.dataset.clock : '';
			}
		} );
	}

	markDepartures();
	setInterval( markDepartures, REFRESH_EVERY_MS );
	document.addEventListener( 'visibilitychange', () => ! document.hidden && markDepartures() );
} )();
