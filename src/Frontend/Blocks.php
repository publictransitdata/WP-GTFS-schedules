<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use GtfsTimetables\Network\Lines;
use GtfsTimetables\Network\TransportMode;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks are described in blocks/{name}/block.json and rendered on the server,
 * so the editor previews exactly what visitors will see.
 */
final class Blocks {

	private const LINES = 'gtfs-timetables/lines';

	public static function register(): void {
		register_block_type(
			GTFS_TIMETABLES_DIR . '/blocks/lines',
			array( 'render_callback' => array( self::class, 'render_lines' ) )
		);

		wp_set_script_translations( self::editor_script( self::LINES ), 'gtfs-timetables', GTFS_TIMETABLES_DIR . '/languages' );
	}

	public static function render_lines( array $attributes ): string {
		return Renderer::line_list( LineListSettings::from_block_attributes( $attributes ), true );
	}

	/**
	 * The editor offers only the transport modes the imported feed has.
	 */
	public static function describe_feed_to_editor(): void {
		$modes = array_map(
			fn( TransportMode $mode ) => array(
				'value' => $mode->value,
				'label' => $mode->label(),
			),
			( new Lines() )->modes()
		);

		wp_add_inline_script(
			self::editor_script( self::LINES ),
			'window.gtfsTimetablesEditor = ' . wp_json_encode( array( 'modes' => $modes ) ) . ';',
			'before'
		);
	}

	private static function editor_script( string $block ): string {
		return generate_block_asset_handle( $block, 'editorScript' );
	}
}
