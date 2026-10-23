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

	const modesInFeed = window.gtfsTimetablesEditor?.modes ?? [];

	function DisplaySettings( { attributes, setAttributes } ) {
		return el(
			PanelBody,
			{ title: __( 'Display', 'gtfs-timetables' ) },
			el( SelectControl, {
				label: __( 'Show', 'gtfs-timetables' ),
				value: attributes.layout,
				options: [
					{ value: 'compact', label: __( 'Line numbers', 'gtfs-timetables' ) },
					{ value: 'destinations', label: __( 'Line numbers with destinations', 'gtfs-timetables' ) },
				],
				onChange: ( layout ) => setAttributes( { layout } ),
			} ),
			el( ToggleControl, {
				label: __( 'One tab per transport mode', 'gtfs-timetables' ),
				checked: attributes.tabs,
				onChange: ( tabs ) => setAttributes( { tabs } ),
			} ),
			el( ToggleControl, {
				label: __( 'Night lines in their own group', 'gtfs-timetables' ),
				help: __( 'Lines named N1, N8… are listed together after the other lines.', 'gtfs-timetables' ),
				checked: attributes.nightLinesApart,
				onChange: ( nightLinesApart ) => setAttributes( { nightLinesApart } ),
			} ),
			el( SelectControl, {
				label: __( 'Group heading level', 'gtfs-timetables' ),
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
			{ title: __( 'Transport modes', 'gtfs-timetables' ), initialOpen: false },
			el( 'p', null, __( 'Tick the modes to show. With none ticked, the block shows them all.', 'gtfs-timetables' ) ),
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

	blocks.registerBlockType( 'gtfs-timetables/lines', {
		edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( InspectorControls, null, el( DisplaySettings, props ), el( ModeSettings, props ) ),
				el( ServerSideRender, { block: 'gtfs-timetables/lines', attributes: props.attributes } )
			);
		},
	} );
} )( wp.blocks, wp.blockEditor, wp.components, wp.element, wp.i18n, wp.serverSideRender );
