/**
 * Import panel on the settings page: starts an import from the feed URL or an
 * uploaded .zip, then keeps asking the server for the next step until it is
 * finished, showing progress meanwhile.
 */
( function () {
	const panel = document.querySelector( '[data-gtfs-import]' );

	if ( ! panel ) {
		return;
	}

	const urlButton = panel.querySelector( '[data-gtfs-import-url]' );
	const uploadButton = panel.querySelector( '[data-gtfs-import-upload]' );
	const fileInput = panel.querySelector( '[data-gtfs-import-file]' );
	const forceCheckbox = panel.querySelector( '[data-gtfs-import-force]' );
	const status = panel.querySelector( '[data-gtfs-import-status]' );
	const hasFeedUrl = ! urlButton.disabled;
	const PAUSE_BETWEEN_STEPS_MS = 500;

	const pause = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

	function ajaxUrl( action ) {
		const query = new URLSearchParams( {
			action,
			_ajax_nonce: panel.dataset.nonce,
		} );

		return `${ panel.dataset.ajaxUrl }?${ query }`;
	}

	async function post( action, body ) {
		const response = await fetch( ajaxUrl( action ), {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} );
		const payload = await response.json().catch( () => null );

		if ( ! payload?.success ) {
			throw new Error( payload?.data?.message ?? response.statusText );
		}

		return payload.data;
	}

	function forceFlag() {
		return forceCheckbox.checked ? '1' : '';
	}

	function importFromUrl() {
		return post( 'gtfs_timetables_import_start', new URLSearchParams( { force: forceFlag() } ) );
	}

	async function importUpload( file ) {
		if ( file.size > Number( panel.dataset.maxUploadBytes ) ) {
			throw new Error( panel.dataset.tooLargeMessage );
		}

		const body = new FormData();
		body.append( fileInput.name, file );
		body.append( 'force', forceFlag() );

		status.textContent = panel.dataset.uploadingMessage;

		return post( 'gtfs_timetables_import_upload', body );
	}

	function setBusy( busy ) {
		urlButton.disabled = busy || ! hasFeedUrl;
		uploadButton.disabled = busy || ! fileInput.files.length;
	}

	async function followImport( startImport ) {
		setBusy( true );

		try {
			let progress = await startImport();

			while ( progress.running ) {
				status.textContent = progress.summary;
				await pause( PAUSE_BETWEEN_STEPS_MS );
				progress = await post( 'gtfs_timetables_import_step' );
			}

			status.textContent = progress.summary;
		} catch ( error ) {
			status.textContent = error.message;
		} finally {
			setBusy( false );
		}
	}

	urlButton.addEventListener( 'click', () => followImport( importFromUrl ) );
	uploadButton.addEventListener( 'click', () =>
		followImport( () => importUpload( fileInput.files[ 0 ] ) )
	);
	fileInput.addEventListener( 'change', () => setBusy( false ) );

	if ( panel.dataset.running === '1' ) {
		followImport( () => post( 'gtfs_timetables_import_step' ) );
	}
} )();
