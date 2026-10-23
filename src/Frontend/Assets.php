<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private const HANDLE = 'gtfs-timetables';

	public static function register(): void {
		$base_url = plugins_url( 'assets/', GTFS_TIMETABLES_FILE );

		wp_register_style( self::HANDLE, $base_url . 'css/gtfs-timetables.css', array(), GTFS_TIMETABLES_VERSION );
		wp_register_script(
			self::HANDLE . '-departures',
			$base_url . 'js/departures.js',
			array(),
			GTFS_TIMETABLES_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public static function enqueue_styles(): void {
		wp_enqueue_style( self::HANDLE );
	}

	public static function enqueue_live_refresh(): void {
		wp_enqueue_script( self::HANDLE . '-departures' );
	}
}
