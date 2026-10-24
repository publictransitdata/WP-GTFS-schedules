<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registered on init, before the blocks, because block.json refers to the stylesheet by its handle.
 */
final class Assets {

	public const STYLE = 'gtfs-timetables';

	private const DEPARTURES_SCRIPT = 'gtfs-timetables-departures';
	private const LINE_TABS_SCRIPT  = 'gtfs-timetables-line-tabs';
	private const CLOCK_SCRIPT      = 'gtfs-timetables-timetable-clock';
	private const ROUTE_MAP_SCRIPT  = 'gtfs-timetables-route-map';

	public static function register(): void {
		$base_url = plugins_url( 'assets/', GTFS_TIMETABLES_FILE );

		wp_register_style( self::STYLE, $base_url . 'css/gtfs-timetables.css', array(), GTFS_TIMETABLES_VERSION );
		self::register_footer_script( self::DEPARTURES_SCRIPT, $base_url . 'js/departures.js' );
		self::register_footer_script( self::LINE_TABS_SCRIPT, $base_url . 'js/line-tabs.js' );
		self::register_footer_script( self::CLOCK_SCRIPT, $base_url . 'js/timetable-clock.js' );
		self::register_footer_script( self::ROUTE_MAP_SCRIPT, $base_url . 'js/route-map.js' );
	}

	public static function enqueue_styles(): void {
		wp_enqueue_style( self::STYLE );
	}

	public static function enqueue_live_refresh(): void {
		wp_enqueue_script( self::DEPARTURES_SCRIPT );
	}

	public static function enqueue_line_tabs(): void {
		wp_enqueue_script( self::LINE_TABS_SCRIPT );
	}

	public static function enqueue_timetable_clock(): void {
		wp_enqueue_script( self::CLOCK_SCRIPT );
	}

	public static function enqueue_route_map(): void {
		wp_enqueue_script( self::ROUTE_MAP_SCRIPT );
	}

	private static function register_footer_script( string $handle, string $url ): void {
		wp_register_script(
			$handle,
			$url,
			array(),
			GTFS_TIMETABLES_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
