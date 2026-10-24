<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

use PtdGtfsSchedules\FeedCache;
use PtdGtfsSchedules\Schedule\Sql;
use PtdGtfsSchedules\Schema;

defined( 'ABSPATH' ) || exit;

final class LineDirections {

	private const STOP_SEPARATOR = "\x1F";
	private const CALL_SEPARATOR = "\x1E";

	/**
	 * @return LineDirection[] In direction order.
	 */
	public function of( Line $line ): array {
		$directions = FeedCache::remember( 'line_routes_' . md5( $line->badge->route_id ), fn() => $this->find_directions( $line->badge->route_id ) );

		return array_map( array( LineDirection::class, 'from_array' ), $directions );
	}

	/**
	 * For each direction: the stops most of its trips call at (so short workings and trips to the
	 * depot do not hide the full route), how the other trips differ from it, and a note for each
	 * trip that does. Feeds without direction_id get one direction per headsign.
	 *
	 * Only stops where riders can get on or off count: a trip that lets nobody on or off at a stop
	 * (a depot, a layover or a timing point) is, for riders, not calling there.
	 *
	 * @param array<int, array{trip_id: string, direction_id: string|int|null, headsign: string, stop_ids: string[], calls?: array<int, array{0: int, 1: int}>}> $trips
	 *        Calls are [pickup_type, drop_off_type] for each stop, in the same order; regular when left out.
	 * @return list<array{
	 *     direction_id: int|null,
	 *     headsign: string,
	 *     stop_ids: string[],
	 *     branches: list<array{leaves_after: int, rejoins_at: int|null, stop_ids: string[]}>,
	 *     ends_early_at: int[],
	 *     starts_late_at: int[],
	 *     partial_at: int[],
	 *     stop_rules: array<string, array{boarding: bool, alighting: bool, on_request: bool}>,
	 *     trip_notes: array<string, array{to: string|null, via: string|null}>
	 * }> Partial are positions of usual stops that not every trip calls at; stop rules only list stops that are not regular.
	 */
	public static function describe( array $trips ): array {
		$trips_by_route = array();
		$calls_by_stop  = array();

		foreach ( $trips as $trip ) {
			$direction = null === $trip['direction_id'] ? 'headsign:' . $trip['headsign'] : (int) $trip['direction_id'];
			$stop_ids  = array();

			foreach ( $trip['stop_ids'] as $position => $stop_id ) {
				$call = $trip['calls'][ $position ] ?? array( 0, 0 );

				if ( self::serves_riders( $call ) ) {
					$stop_ids[]                                = $stop_id;
					$calls_by_stop[ $direction ][ $stop_id ][] = $call;
				}
			}

			if ( $stop_ids ) {
				$trips_by_route[ $direction ][ implode( self::STOP_SEPARATOR, $stop_ids ) ][ $trip['trip_id'] ] = $trip['headsign'];
			}
		}

		ksort( $trips_by_route );

		return array_map(
			fn( $direction, array $routes ) => self::describe_direction( is_int( $direction ) ? $direction : null, $routes, $calls_by_stop[ $direction ] ),
			array_keys( $trips_by_route ),
			$trips_by_route
		);
	}

	/**
	 * @param int|null                                         $direction_id
	 * @param array<string, array<string, string>>             $routes        Stop ids joined => trip id => headsign.
	 * @param array<string, array<int, array{0: int, 1: int}>> $calls_by_stop Stop id => [pickup_type, drop_off_type] of every call.
	 */
	private static function describe_direction( ?int $direction_id, array $routes, array $calls_by_stop ): array {
		uasort( $routes, fn( array $a, array $b ) => count( $b ) <=> count( $a ) );

		$usual_route    = array_key_first( $routes );
		$usual_stop_ids = explode( self::STOP_SEPARATOR, (string) $usual_route );
		$headsign       = self::most_common( $routes[ $usual_route ] );

		$branches       = array();
		$ends_early_at  = array();
		$starts_late_at = array();
		$trip_notes     = array();
		$trips_calling  = array_fill( 0, count( $usual_stop_ids ), 0 );

		foreach ( $routes as $route => $headsigns ) {
			$differences = RouteComparison::compare( $usual_stop_ids, explode( self::STOP_SEPARATOR, (string) $route ) );
			$detour      = null;

			foreach ( $differences['shared'] as $position ) {
				$trips_calling[ $position ] += count( $headsigns );
			}

			foreach ( $differences['branches'] as $branch ) {
				$branches[ "{$branch['leaves_after']}>{$branch['rejoins_at']}>" . implode( self::STOP_SEPARATOR, $branch['stop_ids'] ) ] = $branch;

				if ( null === $detour && $branch['leaves_after'] >= 0 && null !== $branch['rejoins_at'] ) {
					$detour = $branch['stop_ids'][0];
				}
			}

			$ends_early_at[]  = $differences['ends_early_at'];
			$starts_late_at[] = $differences['starts_late_at'];

			foreach ( $headsigns as $trip_id => $trip_headsign ) {
				$to = (string) $trip_headsign !== $headsign ? (string) $trip_headsign : null;

				if ( null !== $to || null !== $detour ) {
					$trip_notes[ (string) $trip_id ] = array(
						'to'  => $to,
						'via' => $detour,
					);
				}
			}
		}

		return array(
			'direction_id'   => $direction_id,
			'headsign'       => $headsign,
			'stop_ids'       => $usual_stop_ids,
			'branches'       => array_values( $branches ),
			'ends_early_at'  => self::positions( $ends_early_at ),
			'starts_late_at' => self::positions( $starts_late_at ),
			'partial_at'     => array_keys( array_filter( $trips_calling, fn( int $trips ) => $trips < max( $trips_calling ) ) ),
			'stop_rules'     => self::stop_rules( $calls_by_stop ),
			'trip_notes'     => $trip_notes,
		);
	}

	/**
	 * @param array{0: int, 1: int} $call [pickup_type, drop_off_type]; 1 means not possible.
	 */
	private static function serves_riders( array $call ): bool {
		return 1 !== $call[0] || 1 !== $call[1];
	}

	/**
	 * Whether riders can board and alight at each stop, and whether they must signal the driver
	 * (GTFS pickup_type and drop_off_type: 1 = not possible, 3 = on request).
	 * A stop counts as allowing boarding when any trip allows it.
	 *
	 * @param array<string, array<int, array{0: int, 1: int}>> $calls_by_stop
	 * @return array<string, array{boarding: bool, alighting: bool, on_request: bool}> Only stops that are not regular.
	 */
	private static function stop_rules( array $calls_by_stop ): array {
		$rules = array();

		foreach ( $calls_by_stop as $stop_id => $calls ) {
			$rule = array(
				'boarding'   => (bool) array_filter( $calls, fn( array $call ) => 1 !== $call[0] ),
				'alighting'  => (bool) array_filter( $calls, fn( array $call ) => 1 !== $call[1] ),
				'on_request' => ! array_filter( $calls, fn( array $call ) => 3 !== $call[0] && 3 !== $call[1] ),
			);

			if ( ! $rule['boarding'] || ! $rule['alighting'] || $rule['on_request'] ) {
				$rules[ (string) $stop_id ] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * @param array<string, string> $headsigns Trip id => headsign.
	 */
	private static function most_common( array $headsigns ): string {
		$counts = array_count_values( array_map( 'strval', $headsigns ) );
		arsort( $counts );

		return (string) array_key_first( $counts );
	}

	/**
	 * @param array<int|null> $positions
	 * @return int[]
	 */
	private static function positions( array $positions ): array {
		$positions = array_values( array_unique( array_filter( $positions, 'is_int' ) ) );
		sort( $positions );

		return $positions;
	}

	/**
	 * Plain arrays, so they can be cached.
	 */
	private function find_directions( string $route_id ): array {
		global $wpdb;

		$wpdb->query( 'SET SESSION group_concat_max_len = 1048576' );

		$trips = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.trip_id, t.direction_id, t.trip_headsign AS headsign,
					GROUP_CONCAT(CONCAT_WS(%s, st.stop_id, st.pickup_type, st.drop_off_type) ORDER BY st.stop_sequence SEPARATOR %s) AS calls
				FROM %i t INNER JOIN %i st ON st.trip_id = t.trip_id
				WHERE t.route_id = %s
				GROUP BY t.trip_id, t.direction_id, t.trip_headsign',
				self::CALL_SEPARATOR,
				self::STOP_SEPARATOR,
				Schema::table( 'trips' ),
				Schema::table( 'stop_times' ),
				$route_id
			),
			ARRAY_A
		);

		$directions = self::describe(
			array_map(
				function ( array $trip ): array {
					$calls = array_map( fn( string $call ) => explode( self::CALL_SEPARATOR, $call ), explode( self::STOP_SEPARATOR, $trip['calls'] ) );

					return array(
						'trip_id'      => $trip['trip_id'],
						'direction_id' => $trip['direction_id'],
						'headsign'     => $trip['headsign'],
						'stop_ids'     => array_column( $calls, 0 ),
						'calls'        => array_map( fn( array $call ) => array( (int) $call[1], (int) $call[2] ), $calls ),
					);
				},
				$trips
			)
		);

		$stop_ids = array();
		foreach ( $directions as $direction ) {
			$stop_ids = array_merge( $stop_ids, $direction['stop_ids'], ...array_column( $direction['branches'], 'stop_ids' ) );
		}

		$names = $this->stop_names( array_values( array_unique( $stop_ids ) ) );

		return array_map( fn( array $direction ) => $direction + array( 'stop_names' => $names ), $directions );
	}

	/**
	 * @param string[] $stop_ids
	 * @return array<string, string>
	 */
	private function stop_names( array $stop_ids ): array {
		global $wpdb;

		if ( ! $stop_ids ) {
			return array();
		}

		$stops = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT stop_id, stop_name FROM %i WHERE stop_id IN (' . Sql::placeholders( $stop_ids ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders only.
				array_merge( array( Schema::table( 'stops' ) ), array_values( $stop_ids ) )
			),
			ARRAY_A
		);

		return array_column( $stops, 'stop_name', 'stop_id' );
	}
}
