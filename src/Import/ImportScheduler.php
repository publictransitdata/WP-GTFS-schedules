<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Import;

use PtdGtfsSchedules\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Runs imports from WP-Cron: a recurring event starts one, and a chain of
 * single events carries it forward step by step until it is done.
 */
final class ImportScheduler {

	public const REFRESH_HOOK  = 'ptd_gtfs_schedules_refresh_feed';
	public const CONTINUE_HOOK = 'ptd_gtfs_schedules_continue_import';

	private const QUIET_HOUR = 3;

	public static function register(): void {
		add_action( self::REFRESH_HOOK, array( self::class, 'refresh' ) );
		add_action( self::CONTINUE_HOOK, array( self::class, 'continue_import' ) );
		add_action( 'add_option_' . Settings::OPTION, array( self::class, 'reschedule' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'reschedule' ) );
	}

	public static function refresh(): void {
		( new Importer() )->start();
		self::continue_import();
	}

	public static function continue_import(): void {
		$state = ( new Importer() )->run_step( Importer::seconds_per_step() );

		if ( $state->is_running() ) {
			self::schedule_next_step();
		}
	}

	public static function schedule_next_step(): void {
		if ( ! wp_next_scheduled( self::CONTINUE_HOOK ) ) {
			wp_schedule_single_event( time(), self::CONTINUE_HOOK );
		}
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::REFRESH_HOOK );

		$frequency = Settings::get( 'refresh_frequency' );

		if ( 'off' !== $frequency && '' !== Settings::get( 'feed_url' ) ) {
			wp_schedule_event( self::next_quiet_hour(), $frequency, self::REFRESH_HOOK );
		}
	}

	public static function unschedule_all(): void {
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
		wp_clear_scheduled_hook( self::CONTINUE_HOOK );
	}

	private static function next_quiet_hour(): int {
		$now   = current_datetime();
		$quiet = $now->setTime( self::QUIET_HOUR, 0 );

		return ( $quiet > $now ? $quiet : $quiet->modify( '+1 day' ) )->getTimestamp();
	}
}
