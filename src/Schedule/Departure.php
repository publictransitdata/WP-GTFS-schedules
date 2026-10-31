<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class Departure {

	private const SHOW_COUNTDOWN_UNTIL_MINUTES = 30;

	/**
	 * @param DateTimeImmutable $time
	 * @param RouteBadge        $route
	 * @param string            $headsign
	 * @param string            $trip_id
	 * @param string            $stop_id      The stop itself, also where the board is a station's.
	 * @param DateTimeImmutable $service_date The day the trip belongs to, the day before for times after midnight.
	 */
	public function __construct(
		public readonly DateTimeImmutable $time,
		public readonly RouteBadge $route,
		public readonly string $headsign,
		public readonly string $trip_id,
		public readonly string $stop_id,
		public readonly DateTimeImmutable $service_date,
	) {}

	public static function from_row( object $row, DateTimeImmutable $service_date ): self {
		$day_start = ServiceDay::start( $service_date );
		$time      = $day_start->setTimestamp( $day_start->getTimestamp() + (int) $row->departure_secs );

		return new self( $time, RouteBadge::from_row( $row ), (string) $row->headsign, (string) $row->trip_id, (string) $row->stop_id, $service_date );
	}

	public function minutes_after( DateTimeImmutable $now ): int {
		return max( 0, intdiv( $this->time->getTimestamp() - $now->getTimestamp(), MINUTE_IN_SECONDS ) );
	}

	/**
	 * "now", "in 5 min" for departures soon, the clock time for later ones.
	 */
	public function label( DateTimeImmutable $now ): string {
		$minutes = $this->minutes_after( $now );

		if ( 0 === $minutes ) {
			return __( 'now', 'ptd-gtfs-schedules' );
		}

		if ( $minutes <= self::SHOW_COUNTDOWN_UNTIL_MINUTES ) {
			/* translators: %d: minutes until departure */
			return sprintf( _n( 'in %d min', 'in %d min', $minutes, 'ptd-gtfs-schedules' ), $minutes );
		}

		return $this->time->format( 'H:i' );
	}
}
