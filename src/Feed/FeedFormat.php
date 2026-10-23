<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Feed;

use GtfsTimetables\Import\ImportError;
use GtfsTimetables\Schedule\GtfsTime;

defined( 'ABSPATH' ) || exit;

/**
 * The subset of the GTFS reference we import.
 *
 * @link https://gtfs.org/documentation/schedule/reference/
 */
final class FeedFormat {

	private const REQUIRED_FILES = array( 'agency.txt', 'stops.txt', 'routes.txt', 'trips.txt', 'stop_times.txt' );
	private const CALENDAR_FILES = array( 'calendar.txt', 'calendar_dates.txt' );

	/**
	 * @return FeedFile[] In import order.
	 */
	public static function files(): array {
		return array(
			new FeedFile(
				'agency.txt',
				'agency',
				array( 'agency_id', 'agency_name', 'agency_timezone' ),
				fn( array $row ) => array(
					self::text( $row, 'agency_id' ),
					self::text( $row, 'agency_name' ),
					self::text( $row, 'agency_timezone' ),
				)
			),
			new FeedFile(
				'calendar.txt',
				'calendar',
				array( 'service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date' ),
				fn( array $row ) => array(
					self::text( $row, 'service_id' ),
					self::flag( $row, 'monday' ),
					self::flag( $row, 'tuesday' ),
					self::flag( $row, 'wednesday' ),
					self::flag( $row, 'thursday' ),
					self::flag( $row, 'friday' ),
					self::flag( $row, 'saturday' ),
					self::flag( $row, 'sunday' ),
					self::date( $row, 'start_date' ),
					self::date( $row, 'end_date' ),
				)
			),
			new FeedFile(
				'calendar_dates.txt',
				'calendar_dates',
				array( 'service_id', 'service_date', 'exception_type' ),
				fn( array $row ) => array(
					self::text( $row, 'service_id' ),
					self::date( $row, 'date' ),
					self::integer( $row, 'exception_type' ),
				)
			),
			new FeedFile(
				'routes.txt',
				'routes',
				array( 'route_id', 'agency_id', 'route_short_name', 'route_long_name', 'route_type', 'route_color', 'route_text_color', 'route_sort_order' ),
				fn( array $row ) => array(
					self::text( $row, 'route_id' ),
					self::text( $row, 'agency_id' ),
					self::text( $row, 'route_short_name' ),
					self::text( $row, 'route_long_name' ),
					self::integer( $row, 'route_type', 3 ),
					self::color( $row, 'route_color' ),
					self::color( $row, 'route_text_color' ),
					self::integer_or_null( $row, 'route_sort_order' ),
				)
			),
			new FeedFile(
				'stops.txt',
				'stops',
				array( 'stop_id', 'stop_code', 'stop_name', 'location_type', 'parent_station' ),
				fn( array $row ) => array(
					self::text( $row, 'stop_id' ),
					self::text( $row, 'stop_code' ),
					self::text( $row, 'stop_name' ),
					self::integer( $row, 'location_type' ),
					self::text( $row, 'parent_station' ),
				)
			),
			new FeedFile(
				'trips.txt',
				'trips',
				array( 'trip_id', 'route_id', 'service_id', 'trip_headsign', 'direction_id' ),
				fn( array $row ) => array(
					self::text( $row, 'trip_id' ),
					self::text( $row, 'route_id' ),
					self::text( $row, 'service_id' ),
					self::text( $row, 'trip_headsign' ),
					self::integer_or_null( $row, 'direction_id' ),
				)
			),
			new FeedFile(
				'stop_times.txt',
				'stop_times',
				array( 'trip_id', 'stop_sequence', 'stop_id', 'departure_secs', 'pickup_type', 'stop_headsign' ),
				fn( array $row ) => self::stop_time( $row )
			),
		);
	}

	public static function assert_complete( string $directory ): void {
		$missing = array_values(
			array_filter(
				self::REQUIRED_FILES,
				fn( string $name ) => ! is_readable( "{$directory}/{$name}" )
			)
		);

		$has_calendar = array_filter(
			self::CALENDAR_FILES,
			fn( string $name ) => is_readable( "{$directory}/{$name}" )
		);

		if ( ! $has_calendar ) {
			$missing[] = implode( ' / ', self::CALENDAR_FILES );
		}

		if ( $missing ) {
			/* translators: %s: comma-separated list of file names */
			throw new ImportError( esc_html( sprintf( __( 'The feed is missing required files: %s', 'gtfs-timetables' ), implode( ', ', $missing ) ) ) );
		}
	}

	/**
	 * Some publishers zip a folder instead of the files themselves.
	 */
	public static function locate_root( string $directory ): string {
		if ( is_readable( "{$directory}/stops.txt" ) ) {
			return $directory;
		}

		$nested = glob( "{$directory}/*/stops.txt" );

		return $nested ? dirname( $nested[0] ) : $directory;
	}

	public static function uses_frequencies( string $directory ): bool {
		$path = "{$directory}/frequencies.txt";

		return is_readable( $path ) && null !== ( new CsvReader( $path ) )->next_row();
	}

	private static function stop_time( array $row ): ?array {
		$time    = self::text( $row, 'departure_time' );
		$seconds = GtfsTime::to_seconds( '' !== $time ? $time : self::text( $row, 'arrival_time' ) );

		if ( null === $seconds ) {
			return null;
		}

		return array(
			self::text( $row, 'trip_id' ),
			self::integer( $row, 'stop_sequence' ),
			self::text( $row, 'stop_id' ),
			$seconds,
			self::integer( $row, 'pickup_type' ),
			self::text( $row, 'stop_headsign' ),
		);
	}

	private static function text( array $row, string $column ): string {
		return trim( (string) ( $row[ $column ] ?? '' ) );
	}

	private static function integer( array $row, string $column, int $fallback = 0 ): int {
		$value = self::text( $row, $column );

		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	private static function integer_or_null( array $row, string $column ): ?int {
		$value = self::text( $row, $column );

		return is_numeric( $value ) ? (int) $value : null;
	}

	private static function flag( array $row, string $column ): int {
		return '1' === self::text( $row, $column ) ? 1 : 0;
	}

	private static function date( array $row, string $column ): int {
		$value = self::text( $row, $column );

		return preg_match( '/^\d{8}$/', $value ) ? (int) $value : 0;
	}

	private static function color( array $row, string $column ): string {
		$value = strtoupper( ltrim( self::text( $row, $column ), '#' ) );

		return preg_match( '/^[0-9A-F]{6}$/', $value ) ? $value : '';
	}
}
