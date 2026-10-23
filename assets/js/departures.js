/**
 * Keeps departure boards current, even on pages served from a page cache.
 */
( function () {
	const REFRESH_EVERY_MS = 60 * 1000;

	async function refresh( board ) {
		const response = await fetch( board.dataset.refreshUrl, {
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			return;
		}

		const { html } = await response.json();

		if ( html ) {
			board.outerHTML = html;
		}
	}

	function refreshAllBoards() {
		if ( document.hidden ) {
			return;
		}

		document
			.querySelectorAll( '.gtfs-departures[data-refresh-url]' )
			.forEach( ( board ) => refresh( board ).catch( () => {} ) );
	}

	setInterval( refreshAllBoards, REFRESH_EVERY_MS );
	document.addEventListener( 'visibilitychange', refreshAllBoards );
} )();
