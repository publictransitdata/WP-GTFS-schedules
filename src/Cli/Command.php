<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Cli;

use GtfsTimetables\FeedInfo;
use GtfsTimetables\Import\Importer;
use GtfsTimetables\Import\ImportLock;
use GtfsTimetables\Import\ImportState;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Imports GTFS feeds from the command line, the most reliable option for large feeds.
 */
final class Command {

	private const SECONDS_PER_STEP = 60;

	/**
	 * Downloads and imports the feed set on the settings page.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Import even if the feed has not changed since the last import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gtfs-timetables import
	 *     wp gtfs-timetables import --force
	 */
	public function import( array $args, array $options ): void {
		if ( ImportLock::is_held() ) {
			WP_CLI::error( 'Another import step is running right now. Try again in a minute.' );
		}

		$importer = new Importer();
		$state    = $importer->start( ! empty( $options['force'] ) );

		while ( $state->is_running() ) {
			$state = $importer->run_step( self::SECONDS_PER_STEP );
			WP_CLI::log( $importer->summary( $state ) );
		}

		if ( ImportState::FAILED === $state->stage ) {
			WP_CLI::error( $state->message );
		}

		WP_CLI::success( $state->message );
	}

	/**
	 * Shows what data is live on the site.
	 */
	public function status(): void {
		if ( ! FeedInfo::is_loaded() ) {
			WP_CLI::log( 'No feed has been imported yet.' );
			return;
		}

		$feed = FeedInfo::all();

		WP_CLI::log( 'Imported:  ' . wp_date( 'Y-m-d H:i', (int) $feed['imported_at'] ) );
		WP_CLI::log( 'Time zone: ' . FeedInfo::timezone()->getName() );

		foreach ( (array) $feed['rows'] as $table => $count ) {
			WP_CLI::log( sprintf( '%-15s %s', $table, number_format( (int) $count ) ) );
		}
	}
}
