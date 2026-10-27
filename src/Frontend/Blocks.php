<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use PtdGtfsSchedules\Network\Lines;
use PtdGtfsSchedules\Network\TransportMode;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks are described in blocks/{name}/block.json and rendered on the server,
 * so the editor previews exactly what visitors will see.
 */
final class Blocks {

	private const LINES = 'ptd-gtfs-schedules/lines';
	private const LINE  = 'ptd-gtfs-schedules/line';

	public static function register(): void {
		register_block_type(
			PTD_GTFS_SCHEDULES_DIR . '/blocks/lines',
			array( 'render_callback' => array( self::class, 'render_lines' ) )
		);

		register_block_type(
			PTD_GTFS_SCHEDULES_DIR . '/blocks/line',
			array( 'render_callback' => array( self::class, 'render_line' ) )
		);

		foreach ( array( self::LINES, self::LINE ) as $block ) {
			wp_set_script_translations( self::editor_script( $block ), 'ptd-gtfs-schedules' );
		}
	}

	public static function render_lines( array $attributes ): string {
		return Renderer::line_list( LineListSettings::from_block_attributes( $attributes ), true );
	}

	public static function render_line(): string {
		return Renderer::line_page( true );
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
			'window.ptdGtfsSchedulesEditor = ' . wp_json_encode( array( 'modes' => $modes ) ) . ';',
			'before'
		);
	}

	private static function editor_script( string $block ): string {
		return generate_block_asset_handle( $block, 'editorScript' );
	}
}
