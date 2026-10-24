/**
 * Editor side of the Line timetable block. The line comes from the address (?line=…) when visitors
 * follow a Lines block, so the editor previews the first line of the feed.
 */
( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	const el = element.createElement;
	const { __ } = i18n;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody } = components;

	blocks.registerBlockType( 'ptd-gtfs-schedules/line', {
		edit() {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'How this page works', 'ptd-gtfs-schedules' ) },
						el( 'p', null, __( 'Every Lines block on the site links to the page with this block, adding the chosen line to the address.', 'ptd-gtfs-schedules' ) ),
						el( 'p', null, __( 'The preview shows the first line of the feed. Without a line in the address, visitors see the list of all lines.', 'ptd-gtfs-schedules' ) )
					)
				),
				el( ServerSideRender, { block: 'ptd-gtfs-schedules/line' } )
			);
		},
	} );
} )( wp.blocks, wp.blockEditor, wp.components, wp.element, wp.i18n, wp.serverSideRender );
