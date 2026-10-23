<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables;

use GtfsTimetables\Import\ImportError;

defined( 'ABSPATH' ) || exit;

/**
 * Feed data lives in three sets of tables: LIVE is what visitors see,
 * STAGING receives a new import, and RETIRED briefly holds the previous
 * data while the two are swapped.
 */
final class Schema {

	public const LIVE    = 'gtfs_';
	public const STAGING = 'gtfs_next_';
	public const RETIRED = 'gtfs_old_';

	private const VERSION        = '1';
	private const VERSION_OPTION = 'gtfs_timetables_db_version';

	public static function table( string $name, string $set = self::LIVE ): string {
		global $wpdb;

		return $wpdb->prefix . $set . $name;
	}

	public static function create( string $set ): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::definitions() as $name => $columns ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column definitions are constants of this class.
			$wpdb->query( $wpdb->prepare( "CREATE TABLE IF NOT EXISTS %i ({$columns}) {$charset_collate}", self::table( $name, $set ) ) );
		}
	}

	public static function drop( string $set ): void {
		global $wpdb;

		foreach ( array_keys( self::definitions() ) as $name ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $name, $set ) ) );
		}
	}

	/**
	 * @throws ImportError When the database refuses the swap; the live data is then left untouched.
	 */
	public static function promote_staging(): void {
		global $wpdb;

		self::drop( self::RETIRED );
		self::create( self::LIVE );

		$renames = array();
		foreach ( array_keys( self::definitions() ) as $name ) {
			$renames[] = array( self::table( $name, self::LIVE ), self::table( $name, self::RETIRED ) );
			$renames[] = array( self::table( $name, self::STAGING ), self::table( $name, self::LIVE ) );
		}

		$sql = 'RENAME TABLE ' . implode( ', ', array_fill( 0, count( $renames ), '%i TO %i' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $sql holds only %i placeholders.
		if ( false === $wpdb->query( $wpdb->prepare( $sql, array_merge( ...$renames ) ) ) ) {
			throw new ImportError( $wpdb->last_error );
		}

		self::drop( self::RETIRED );
	}

	public static function upgrade_if_needed(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		self::drop( self::LIVE );
		self::create( self::LIVE );
		FeedInfo::clear();
		self::remember_version();
	}

	public static function remember_version(): void {
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	public static function forget_version(): void {
		delete_option( self::VERSION_OPTION );
	}

	public static function drop_everything(): void {
		self::drop( self::LIVE );
		self::drop( self::STAGING );
		self::drop( self::RETIRED );
	}

	private static function definitions(): array {
		return array(
			'agency'         => "
				agency_id VARCHAR(100) NOT NULL DEFAULT '',
				agency_name VARCHAR(255) NOT NULL DEFAULT '',
				agency_timezone VARCHAR(64) NOT NULL DEFAULT '',
				PRIMARY KEY (agency_id)",

			'stops'          => "
				stop_id VARCHAR(100) NOT NULL,
				stop_code VARCHAR(50) NOT NULL DEFAULT '',
				stop_name VARCHAR(191) NOT NULL DEFAULT '',
				location_type TINYINT UNSIGNED NOT NULL DEFAULT 0,
				parent_station VARCHAR(100) NOT NULL DEFAULT '',
				PRIMARY KEY (stop_id),
				KEY parent_station (parent_station),
				KEY stop_name (stop_name)",

			'routes'         => "
				route_id VARCHAR(100) NOT NULL,
				agency_id VARCHAR(100) NOT NULL DEFAULT '',
				route_short_name VARCHAR(50) NOT NULL DEFAULT '',
				route_long_name VARCHAR(255) NOT NULL DEFAULT '',
				route_type SMALLINT UNSIGNED NOT NULL DEFAULT 3,
				route_color CHAR(6) NOT NULL DEFAULT '',
				route_text_color CHAR(6) NOT NULL DEFAULT '',
				route_sort_order INT UNSIGNED NULL,
				PRIMARY KEY (route_id)",

			'trips'          => "
				trip_id VARCHAR(100) NOT NULL,
				route_id VARCHAR(100) NOT NULL,
				service_id VARCHAR(100) NOT NULL,
				trip_headsign VARCHAR(255) NOT NULL DEFAULT '',
				direction_id TINYINT UNSIGNED NULL,
				PRIMARY KEY (trip_id),
				KEY service_id (service_id)",

			'stop_times'     => "
				trip_id VARCHAR(100) NOT NULL,
				stop_sequence INT UNSIGNED NOT NULL,
				stop_id VARCHAR(100) NOT NULL,
				departure_secs MEDIUMINT UNSIGNED NOT NULL,
				pickup_type TINYINT UNSIGNED NOT NULL DEFAULT 0,
				stop_headsign VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (trip_id, stop_sequence),
				KEY stop_departure (stop_id, departure_secs)",

			'calendar'       => '
				service_id VARCHAR(100) NOT NULL,
				monday TINYINT UNSIGNED NOT NULL,
				tuesday TINYINT UNSIGNED NOT NULL,
				wednesday TINYINT UNSIGNED NOT NULL,
				thursday TINYINT UNSIGNED NOT NULL,
				friday TINYINT UNSIGNED NOT NULL,
				saturday TINYINT UNSIGNED NOT NULL,
				sunday TINYINT UNSIGNED NOT NULL,
				start_date INT UNSIGNED NOT NULL,
				end_date INT UNSIGNED NOT NULL,
				PRIMARY KEY (service_id)',

			'calendar_dates' => '
				service_id VARCHAR(100) NOT NULL,
				service_date INT UNSIGNED NOT NULL,
				exception_type TINYINT UNSIGNED NOT NULL,
				PRIMARY KEY (service_id, service_date),
				KEY service_date (service_date)',
		);
	}
}
