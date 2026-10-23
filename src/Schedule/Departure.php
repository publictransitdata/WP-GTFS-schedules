<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class Departure {

	private const SHOW_COUNTDOWN_UNTIL_MINUTES = 30;

	public function __construct(
		public readonly DateTimeImmutable $time,
		public readonly RouteBadge $route,
		public readonly string $headsign,
	) {}

	public static function from_row( object $row, DateTimeImmutable $service_day_start ): self {
		$time = $service_day_start->setTimestamp( $service_day_start->getTimestamp() + (int) $row->departure_secs );

		return new self( $time, RouteBadge::from_row( $row ), (string) $row->headsign );
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
			return __( 'now', 'gtfs-timetables' );
		}

		if ( $minutes <= self::SHOW_COUNTDOWN_UNTIL_MINUTES ) {
			/* translators: %d: minutes until departure */
			return sprintf( _n( 'in %d min', 'in %d min', $minutes, 'gtfs-timetables' ), $minutes );
		}

		return $this->time->format( 'H:i' );
	}
}
