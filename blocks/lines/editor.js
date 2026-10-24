/**
 * Editor side of the Lines block: settings in the sidebar, and a preview rendered
 * by the server, exactly as visitors will see it.
 *
 * Plain JavaScript on the wp.* globals, so the plugin needs no build step.
 */
( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	const el = element.createElement;
	const { __ } = i18n;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, SelectControl, ToggleControl, CheckboxControl } = components;

	const modesInFeed = window.ptdGtfsSchedulesEditor?.modes ?? [];

	function DisplaySettings( { attributes, setAttributes } ) {
		return el(
			PanelBody,
			{ title: __( 'Display', 'ptd-gtfs-schedules' ) },
			el( SelectControl, {
				label: __( 'Show', 'ptd-gtfs-schedules' ),
				value: attributes.layout,
				options: [
					{ value: 'compact', label: __( 'Line numbers', 'ptd-gtfs-schedules' ) },
					{ value: 'destinations', label: __( 'Line numbers with destinations', 'ptd-gtfs-schedules' ) },
				],
				onChange: ( layout ) => setAttributes( { layout } ),
			} ),
			el( ToggleControl, {
				label: __( 'One tab per transport mode', 'ptd-gtfs-schedules' ),
				checked: attributes.tabs,
				onChange: ( tabs ) => setAttributes( { tabs } ),
			} ),
			el( ToggleControl, {
				label: __( 'Night lines in their own group', 'ptd-gtfs-schedules' ),
				help: __( 'Lines named N1, N8… are listed together after the other lines.', 'ptd-gtfs-schedules' ),
				checked: attributes.nightLinesApart,
				onChange: ( nightLinesApart ) => setAttributes( { nightLinesApart } ),
			} ),
			el( SelectControl, {
				label: __( 'Group heading level', 'ptd-gtfs-schedules' ),
				value: String( attributes.headingLevel ),
				options: [ 2, 3, 4 ].map( ( level ) => ( { value: String( level ), label: `H${ level }` } ) ),
				onChange: ( level ) => setAttributes( { headingLevel: Number( level ) } ),
			} )
		);
	}

	function ModeSettings( { attributes, setAttributes } ) {
		const toggle = ( mode, checked ) =>
			setAttributes( {
				modes: checked ? [ ...attributes.modes, mode ] : attributes.modes.filter( ( chosen ) => chosen !== mode ),
			} );

		return el(
			PanelBody,
			{ title: __( 'Transport modes', 'ptd-gtfs-schedules' ), initialOpen: false },
			el( 'p', null, __( 'Tick the modes to show. With none ticked, the block shows them all.', 'ptd-gtfs-schedules' ) ),
			modesInFeed.map( ( mode ) =>
				el( CheckboxControl, {
					key: mode.value,
					label: mode.label,
					checked: attributes.modes.includes( mode.value ),
					onChange: ( checked ) => toggle( mode.value, checked ),
				} )
			)
		);
	}

	blocks.registerBlockType( 'ptd-gtfs-schedules/lines', {
		edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( InspectorControls, null, el( DisplaySettings, props ), el( ModeSettings, props ) ),
				el( ServerSideRender, { block: 'ptd-gtfs-schedules/lines', attributes: props.attributes } )
			);
		},
	} );
} )( wp.blocks, wp.blockEditor, wp.components, wp.element, wp.i18n, wp.serverSideRender );
