<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Cli;

use PtdGtfsSchedules\FeedInfo;
use PtdGtfsSchedules\Import\ImportError;
use PtdGtfsSchedules\Import\Importer;
use PtdGtfsSchedules\Import\ImportLock;
use PtdGtfsSchedules\Import\ImportState;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Imports GTFS feeds from the command line, the most reliable option for large feeds.
 */
final class Command {

	private const SECONDS_PER_STEP = 60;

	/**
	 * Downloads and imports the feed set on the settings page, or imports a feed .zip file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Import this feed .zip instead of downloading the feed URL.
	 *
	 * [--force]
	 * : Import even if the feed has not changed since the last import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ptd-gtfs-schedules import
	 *     wp ptd-gtfs-schedules import --force
	 *     wp ptd-gtfs-schedules import --file=gtfs.zip
	 */
	public function import( array $args, array $options ): void {
		if ( ImportLock::is_held() ) {
			WP_CLI::error( 'Another import step is running right now. Try again in a minute.' );
		}

		$importer = new Importer();
		$force    = ! empty( $options['force'] );
		$state    = isset( $options['file'] )
			? $this->start_from_file( $importer, (string) $options['file'], $force )
			: $importer->start( $force );

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
	 * The import deletes its archive once unpacked, so it gets a copy and the user's file stays.
	 */
	private function start_from_file( Importer $importer, string $path, bool $force ): ImportState {
		if ( ! is_readable( $path ) ) {
			WP_CLI::error( "Cannot read {$path}." );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$archive = wp_tempnam( 'gtfs-feed.zip' );

		if ( ! copy( $path, $archive ) ) {
			WP_CLI::error( 'Could not copy the file to the temporary directory.' );
		}

		try {
			return $importer->start_from_archive( $archive, $force );
		} catch ( ImportError $error ) {
			WP_CLI::error( $error->plain_text() );
		}
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
