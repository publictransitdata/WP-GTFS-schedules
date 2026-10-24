<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

use GtfsTimetables\FeedCache;
use GtfsTimetables\Schedule\Sql;
use GtfsTimetables\Schema;

defined( 'ABSPATH' ) || exit;

final class LineDirections {

	private const STOP_SEPARATOR = "\x1F";

	/**
	 * @return LineDirection[] In direction order.
	 */
	public function of( Line $line ): array {
		$directions = FeedCache::remember( 'line_' . md5( $line->badge->route_id ), fn() => $this->find_directions( $line->badge->route_id ) );

		return array_map(
			fn( array $direction ) => new LineDirection(
				$direction['direction_id'],
				$direction['headsign'],
				array_map( fn( array $stop ) => new LineStop( $stop[0], $stop[1] ), $direction['stops'] )
			),
			$directions
		);
	}

	/**
	 * For each direction, the stops most of its trips call at, so short workings and trips
	 * to the depot do not hide the full route. Feeds without direction_id get one direction per headsign.
	 *
	 * @param array<int, array{direction_id: string|int|null, headsign: string, stop_ids: string[]}> $trips
	 * @return array<int, array{direction_id: int|null, headsign: string, stop_ids: string[]}>
	 */
	public static function usual_routes( array $trips ): array {
		$trips_by_route = array();

		foreach ( $trips as $trip ) {
			$direction = null === $trip['direction_id'] ? 'headsign:' . $trip['headsign'] : (int) $trip['direction_id'];
			$route     = implode( self::STOP_SEPARATOR, $trip['stop_ids'] );

			$trips_by_route[ $direction ][ $route ]['trips']                          = ( $trips_by_route[ $direction ][ $route ]['trips'] ?? 0 ) + 1;
			$trips_by_route[ $direction ][ $route ]['headsigns'][ $trip['headsign'] ] = ( $trips_by_route[ $direction ][ $route ]['headsigns'][ $trip['headsign'] ] ?? 0 ) + 1;
		}

		ksort( $trips_by_route );

		$usual_routes = array();
		foreach ( $trips_by_route as $direction => $routes ) {
			uasort( $routes, fn( array $a, array $b ) => $b['trips'] <=> $a['trips'] );

			$usual     = array_key_first( $routes );
			$headsigns = $routes[ $usual ]['headsigns'];
			arsort( $headsigns );

			$usual_routes[] = array(
				'direction_id' => is_int( $direction ) ? $direction : null,
				'headsign'     => (string) array_key_first( $headsigns ),
				'stop_ids'     => explode( self::STOP_SEPARATOR, (string) $usual ),
			);
		}

		return $usual_routes;
	}

	/**
	 * Plain arrays, so they can be cached.
	 */
	private function find_directions( string $route_id ): array {
		global $wpdb;

		$wpdb->query( 'SET SESSION group_concat_max_len = 1048576' );

		$trips = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.direction_id, t.trip_headsign AS headsign, GROUP_CONCAT(st.stop_id ORDER BY st.stop_sequence SEPARATOR %s) AS stops
				FROM %i t INNER JOIN %i st ON st.trip_id = t.trip_id
				WHERE t.route_id = %s
				GROUP BY t.trip_id, t.direction_id, t.trip_headsign',
				self::STOP_SEPARATOR,
				Schema::table( 'trips' ),
				Schema::table( 'stop_times' ),
				$route_id
			),
			ARRAY_A
		);

		$routes = self::usual_routes(
			array_map(
				fn( array $trip ) => array(
					'direction_id' => $trip['direction_id'],
					'headsign'     => $trip['headsign'],
					'stop_ids'     => explode( self::STOP_SEPARATOR, $trip['stops'] ),
				),
				$trips
			)
		);

		$names = $this->stop_names( array_unique( array_merge( array(), ...array_column( $routes, 'stop_ids' ) ) ) );

		return array_map(
			fn( array $route ) => array(
				'direction_id' => $route['direction_id'],
				'headsign'     => $route['headsign'],
				'stops'        => array_map( fn( string $stop_id ) => array( $stop_id, $names[ $stop_id ] ?? $stop_id ), $route['stop_ids'] ),
			),
			$routes
		);
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
