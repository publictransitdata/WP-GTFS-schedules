<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * One trip on one service day, from its first stop to its last.
 */
final class Trip {

	private const NOT_POSSIBLE = 1;
	private const ON_REQUEST   = 3;

	/**
	 * @param string            $trip_id
	 * @param RouteBadge        $route
	 * @param string            $headsign     From trips.txt; may be empty.
	 * @param int|null          $direction_id
	 * @param string            $service_id
	 * @param DateTimeImmutable $service_date
	 * @param TripCall[]        $calls        Only stops where riders can get on or off.
	 */
	public function __construct(
		public readonly string $trip_id,
		public readonly RouteBadge $route,
		public readonly string $headsign,
		public readonly ?int $direction_id,
		public readonly string $service_id,
		public readonly DateTimeImmutable $service_date,
		public readonly array $calls,
	) {}

	/**
	 * @param object   $trip       trip_id, headsign, direction_id, service_id and the route's columns.
	 * @param object[] $stop_times In stop_sequence order: stop_id, stop_name, departure_secs, arrival_secs, pickup_type, drop_off_type.
	 */
	public static function from_rows( object $trip, array $stop_times, DateTimeImmutable $service_date ): self {
		$day_start  = ServiceDay::start( $service_date );
		$at         = fn( int $seconds ) => $day_start->setTimestamp( $day_start->getTimestamp() + $seconds );
		$stop_times = array_values(
			array_filter(
				$stop_times,
				fn( object $call ) => self::NOT_POSSIBLE !== (int) $call->pickup_type || self::NOT_POSSIBLE !== (int) $call->drop_off_type
			)
		);
		$last       = array_key_last( $stop_times );

		$calls = array_map(
			fn( object $call, int $position ) => new TripCall(
				(string) $call->stop_id,
				(string) ( $call->stop_name ?? $call->stop_id ),
				$at( (int) ( $call->arrival_secs ?? $call->departure_secs ) ),
				$at( (int) $call->departure_secs ),
				0 === $position,
				$last === $position,
				self::NOT_POSSIBLE !== (int) $call->pickup_type,
				self::NOT_POSSIBLE !== (int) $call->drop_off_type,
				self::ON_REQUEST === (int) $call->pickup_type || self::ON_REQUEST === (int) $call->drop_off_type
			),
			$stop_times,
			array_keys( $stop_times )
		);

		return new self(
			(string) $trip->trip_id,
			RouteBadge::from_row( $trip ),
			(string) $trip->headsign,
			null === $trip->direction_id ? null : (int) $trip->direction_id,
			(string) $trip->service_id,
			$service_date,
			$calls
		);
	}

	/**
	 * Where the trip goes: its headsign, or its last stop when the feed gives none.
	 */
	public function destination(): string {
		if ( '' !== $this->headsign || ! $this->calls ) {
			return $this->headsign;
		}

		return $this->calls[ array_key_last( $this->calls ) ]->stop_name;
	}

	/**
	 * Position of the stop the rider boards at. A loop can call at a stop twice; the rider
	 * boards where the trip goes on from it.
	 *
	 * @return int|null Null when the trip does not call there.
	 */
	public function boarding_position( string $stop_id ): ?int {
		$positions = array_keys( array_filter( $this->calls, fn( TripCall $call ) => $call->stop_id === $stop_id ) );

		foreach ( $positions as $position ) {
			if ( $this->calls[ $position ]->can_board() ) {
				return $position;
			}
		}

		return $positions[0] ?? null;
	}
}
