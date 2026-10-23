/**
 * "Import now" on the settings page: starts an import and keeps asking the
 * server for the next step until it is finished, showing progress meanwhile.
 */
( function () {
	const panel = document.querySelector( '[data-gtfs-import]' );

	if ( ! panel ) {
		return;
	}

	const button = panel.querySelector( 'button' );
	const forceCheckbox = panel.querySelector( '[data-gtfs-import-force]' );
	const status = panel.querySelector( '[data-gtfs-import-status]' );
	const PAUSE_BETWEEN_STEPS_MS = 500;

	const pause = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

	async function send( action, fields = {} ) {
		const body = new URLSearchParams( {
			action,
			_ajax_nonce: panel.dataset.nonce,
			...fields,
		} );

		const response = await fetch( panel.dataset.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} );
		const payload = await response.json();

		if ( ! payload.success ) {
			throw new Error( payload.data?.message ?? response.statusText );
		}

		return payload.data;
	}

	async function followImport( firstRequest ) {
		button.disabled = true;

		try {
			let progress = await firstRequest;

			while ( progress.running ) {
				status.textContent = progress.summary;
				await pause( PAUSE_BETWEEN_STEPS_MS );
				progress = await send( 'gtfs_timetables_import_step' );
			}

			status.textContent = progress.summary;
		} catch ( error ) {
			status.textContent = error.message;
		} finally {
			button.disabled = false;
		}
	}

	button.addEventListener( 'click', () => {
		const force = forceCheckbox.checked ? '1' : '';
		followImport( send( 'gtfs_timetables_import_start', { force } ) );
	} );

	if ( panel.dataset.running === '1' ) {
		followImport( send( 'gtfs_timetables_import_step' ) );
	}
} )();
