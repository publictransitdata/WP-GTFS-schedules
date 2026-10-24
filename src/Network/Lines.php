<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

use PtdGtfsSchedules\FeedCache;
use PtdGtfsSchedules\Schedule\RouteBadge;
use PtdGtfsSchedules\Schema;

defined( 'ABSPATH' ) || exit;

final class Lines {

	/**
	 * @return Line[] Every line that has trips, in network order.
	 */
	public function all(): array {
		$lines = array_map( array( self::class, 'line_from_row' ), FeedCache::remember( 'lines', fn() => $this->find_rows() ) );
		usort( $lines, array( Line::class, 'in_network_order' ) );

		return $lines;
	}

	public function find( string $route_id ): ?Line {
		foreach ( $this->all() as $line ) {
			if ( $line->badge->route_id === $route_id ) {
				return $line;
			}
		}

		return null;
	}

	/**
	 * @param TransportMode[] $modes Only lines of these modes; every mode when empty.
	 * @return LineGroup[] One group per transport mode, night lines last.
	 */
	public function grouped( array $modes = array(), bool $night_lines_apart = true ): array {
		$lines_by_group = array();

		foreach ( $this->all() as $line ) {
			if ( $modes && ! in_array( $line->mode, $modes, true ) ) {
				continue;
			}

			$group = $night_lines_apart && $this->is_night_line( $line ) ? LineGroup::NIGHT : $line->mode->value;

			$lines_by_group[ $group ][] = $line;
		}

		$groups = array();
		foreach ( TransportMode::cases() as $mode ) {
			if ( isset( $lines_by_group[ $mode->value ] ) ) {
				$groups[] = new LineGroup( $mode->value, $mode->label(), $lines_by_group[ $mode->value ] );
			}
		}

		if ( isset( $lines_by_group[ LineGroup::NIGHT ] ) ) {
			$groups[] = new LineGroup( LineGroup::NIGHT, __( 'Night lines', 'ptd-gtfs-schedules' ), $lines_by_group[ LineGroup::NIGHT ] );
		}

		return $groups;
	}

	/**
	 * @return TransportMode[] The modes the feed has lines for, in display order.
	 */
	public function modes(): array {
		$modes_in_feed = array_map( fn( Line $line ) => $line->mode, $this->all() );

		return array_values( array_filter( TransportMode::cases(), fn( TransportMode $mode ) => in_array( $mode, $modes_in_feed, true ) ) );
	}

	/**
	 * Where a line goes, from the most frequent headsign of each direction:
	 * both termini for most lines, one for a loop.
	 *
	 * @param array<int, array{direction_id: string|null, trip_headsign: string}> $headsigns Most frequent first.
	 * @return string[]
	 */
	public static function destinations_from( array $headsigns ): array {
		$destinations = array();

		foreach ( $headsigns as $headsign ) {
			$name      = trim( $headsign['trip_headsign'] );
			$direction = $headsign['direction_id'] ?? $name;

			if ( '' !== $name && ! isset( $destinations[ $direction ] ) && ! in_array( $name, $destinations, true ) ) {
				$destinations[ $direction ] = $name;
			}
		}

		$two_most_frequent = array_slice( $destinations, 0, 2, true );
		ksort( $two_most_frequent );

		return array_values( $two_most_frequent );
	}

	private function is_night_line( Line $line ): bool {
		/**
		 * Filters whether a line is listed with the night lines rather than with its transport mode.
		 *
		 * @param bool $is_night By default, lines named N followed by a digit (N1, N8…).
		 * @param Line $line
		 */
		return (bool) apply_filters( 'ptd_gtfs_schedules_is_night_line', $line->looks_like_night_line(), $line );
	}

	private static function line_from_row( array $row ): Line {
		return new Line(
			RouteBadge::from_row( (object) $row ),
			TransportMode::from_route_type( (int) $row['route_type'] ),
			$row['destinations'],
			null === $row['route_sort_order'] ? null : (int) $row['route_sort_order']
		);
	}

	/**
	 * Plain arrays, so they can be cached.
	 */
	private function find_rows(): array {
		global $wpdb;

		$routes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT route_id, route_short_name, route_long_name, route_type, route_color, route_text_color, route_sort_order FROM %i',
				Schema::table( 'routes' )
			),
			ARRAY_A
		);

		$headsigns = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT route_id, direction_id, trip_headsign, COUNT(*) AS trips FROM %i GROUP BY route_id, direction_id, trip_headsign ORDER BY trips DESC',
				Schema::table( 'trips' )
			),
			ARRAY_A
		);

		$headsigns_by_route = array();
		foreach ( $headsigns as $headsign ) {
			$headsigns_by_route[ $headsign['route_id'] ][] = $headsign;
		}

		$rows = array();
		foreach ( $routes as $route ) {
			$route_headsigns = $headsigns_by_route[ $route['route_id'] ] ?? null;

			if ( null === $route_headsigns ) {
				continue;
			}

			$route['destinations'] = '' !== $route['route_long_name']
				? array( $route['route_long_name'] )
				: self::destinations_from( $route_headsigns );

			$rows[] = $route;
		}

		return $rows;
	}
}
