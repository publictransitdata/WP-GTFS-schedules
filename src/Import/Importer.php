<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Import;

use GtfsTimetables\Feed\CsvReader;
use GtfsTimetables\Feed\FeedDownloader;
use GtfsTimetables\Feed\FeedFile;
use GtfsTimetables\Feed\FeedFormat;
use GtfsTimetables\FeedInfo;
use GtfsTimetables\Schema;
use GtfsTimetables\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Imports a feed in steps of a few seconds each. Every step continues where the
 * previous one stopped, so a city-sized feed fits within shared-hosting time limits.
 * Data goes into staging tables and replaces the live ones only when complete.
 */
final class Importer {

	public function __construct(
		private readonly FeedDownloader $downloader = new FeedDownloader(),
	) {}

	public static function seconds_per_step(): int {
		$time_limit = (int) ini_get( 'max_execution_time' );

		return $time_limit > 0 ? max( 5, min( 20, intdiv( $time_limit, 2 ) ) ) : 20;
	}

	public function start( bool $force = false ): ImportState {
		$state = ImportState::load();

		if ( $state->is_running() && ! $state->is_stale() ) {
			return $state;
		}

		$state = ImportState::begin( $force );
		$state->save();

		return $state;
	}

	public function run_step( int $seconds ): ImportState {
		$state = ImportState::load();

		if ( ! $state->is_running() || ! ImportLock::acquire( $seconds + MINUTE_IN_SECONDS ) ) {
			return $state;
		}

		$deadline = microtime( true ) + $seconds;

		try {
			if ( ImportState::DOWNLOADING === $state->stage ) {
				$this->download( $state );
			}

			if ( ImportState::LOADING === $state->stage ) {
				$this->load_until( $deadline, $state );
			}
		} catch ( \Throwable $error ) {
			$this->abandon( $state, $error->getMessage() );
		} finally {
			$state->save();
			ImportLock::release();
		}

		return $state;
	}

	public function summary( ImportState $state ): string {
		switch ( $state->stage ) {
			case ImportState::DOWNLOADING:
				return __( 'Downloading the feed…', 'gtfs-timetables' );

			case ImportState::LOADING:
				$file = FeedFormat::files()[ $state->file_index ] ?? null;
				return $file ? sprintf(
					/* translators: 1: file name, 2: percent done, 3: number of rows */
					__( 'Importing %1$s: %2$d%% (%3$s rows so far)', 'gtfs-timetables' ),
					$file->name,
					$this->percent_of_file_done( $file, $state ),
					number_format_i18n( $state->rows[ $file->table ] ?? 0 )
				) : __( 'Finishing…', 'gtfs-timetables' );

			case ImportState::DONE:
			case ImportState::FAILED:
				return $state->message;

			default:
				return __( 'No import has run yet.', 'gtfs-timetables' );
		}
	}

	private function download( ImportState $state ): void {
		$url = Settings::get( 'feed_url' );

		if ( '' === $url ) {
			throw new ImportError( __( 'Set the feed URL first.', 'gtfs-timetables' ) );
		}

		$archive = $this->downloader->download( $url );
		$version = (string) sha1_file( $archive );

		if ( ! $state->forced && FeedInfo::version() === $version ) {
			wp_delete_file( $archive );
			$state->finish( __( 'The feed has not changed since the last import.', 'gtfs-timetables' ) );
			return;
		}

		try {
			$directory = $this->downloader->extract( $archive );
		} finally {
			wp_delete_file( $archive );
		}

		FeedFormat::assert_complete( $directory );

		Schema::drop( Schema::STAGING );
		Schema::create( Schema::STAGING );

		$state->start_loading( $directory, $version );
	}

	private function load_until( float $deadline, ImportState $state ): void {
		$files = FeedFormat::files();

		while ( isset( $files[ $state->file_index ] ) ) {
			$finished_file = $this->load_file( $files[ $state->file_index ], $state, $deadline );

			if ( ! $finished_file ) {
				return;
			}

			$state->move_to_next_file();
		}

		$this->publish( $state );
	}

	/**
	 * @return bool False when the time ran out before the end of the file.
	 */
	private function load_file( FeedFile $file, ImportState $state, float $deadline ): bool {
		$path = "{$state->directory}/{$file->name}";

		if ( ! is_readable( $path ) ) {
			return true;
		}

		$reader = new CsvReader( $path );
		$reader->seek( $state->offset );

		$batch = new BatchInsert( Schema::table( $file->table, Schema::STAGING ), $file->columns );

		while ( null !== ( $row = $reader->next_row() ) ) {
			$values = $file->map( $row );

			if ( null !== $values ) {
				$batch->add( $values );
			}

			if ( $batch->is_full() ) {
				$state->count_rows( $file->table, $batch->flush() );
				$state->offset = $reader->position();

				if ( microtime( true ) >= $deadline ) {
					return false;
				}
			}
		}

		$state->count_rows( $file->table, $batch->flush() );

		return true;
	}

	private function publish( ImportState $state ): void {
		if ( empty( $state->rows['stop_times'] ) ) {
			throw new ImportError( __( 'The feed has no usable stop times.', 'gtfs-timetables' ) );
		}

		$timezone         = $this->feed_timezone();
		$uses_frequencies = FeedFormat::uses_frequencies( $state->directory );

		Schema::promote_staging();

		FeedInfo::save(
			array(
				'version'          => $state->version,
				'timezone'         => $timezone,
				'imported_at'      => time(),
				'rows'             => $state->rows,
				'uses_frequencies' => $uses_frequencies,
			)
		);

		$this->downloader->clean_up();

		$state->finish(
			sprintf(
				/* translators: 1: number of stops, 2: number of trips, 3: number of stop times */
				__( 'Import finished: %1$s stops, %2$s trips, %3$s stop times.', 'gtfs-timetables' ),
				number_format_i18n( $state->rows['stops'] ?? 0 ),
				number_format_i18n( $state->rows['trips'] ?? 0 ),
				number_format_i18n( $state->rows['stop_times'] ?? 0 )
			)
		);

		do_action( 'gtfs_timetables_imported', FeedInfo::all() );
	}

	private function abandon( ImportState $state, string $reason ): void {
		Schema::drop( Schema::STAGING );
		$this->downloader->clean_up();
		$state->fail( $reason );
	}

	private function feed_timezone(): string {
		global $wpdb;

		$table = Schema::table( 'agency', Schema::STAGING );

		return (string) $wpdb->get_var( "SELECT agency_timezone FROM {$table} WHERE agency_timezone <> '' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	}

	private function percent_of_file_done( FeedFile $file, ImportState $state ): int {
		$path = "{$state->directory}/{$file->name}";
		$size = is_readable( $path ) ? (int) filesize( $path ) : 0;

		return $size > 0 ? (int) floor( 100 * $state->offset / $size ) : 0;
	}
}
