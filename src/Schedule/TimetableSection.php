<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Departures of one route in one direction, laid out like a printed stop timetable:
 * one line per hour with the minutes of each departure.
 */
final class TimetableSection {

	/** @var array<int, TimetableDeparture[]> Service hour (may be 24 or more after midnight) => departures. */
	private array $departures_by_hour = array();

	public function __construct(
		public readonly RouteBadge $route,
		public readonly string $headsign,
		private readonly DateTimeImmutable $service_day_start,
	) {}

	public function add_departure( int $seconds_into_service_day, string $trip_id = '', string $stop_id = '' ): void {
		$this->departures_by_hour[ intdiv( $seconds_into_service_day, HOUR_IN_SECONDS ) ][] = new TimetableDeparture(
			$this->service_day_start->setTimestamp( $this->service_day_start->getTimestamp() + $seconds_into_service_day ),
			sprintf( '%02d', intdiv( $seconds_into_service_day % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ) ),
			null,
			null,
			$trip_id,
			$stop_id
		);
	}

	/**
	 * Hours after midnight come last and can repeat an early-morning clock hour,
	 * e.g. 04:47 and 28:18 (04:18 the next morning) on the same service day.
	 *
	 * @return list<array{0: string, 1: TimetableDeparture[]}> [clock hour such as "05", its departures].
	 */
	public function departure_rows(): array {
		ksort( $this->departures_by_hour );

		$rows = array();
		foreach ( $this->departures_by_hour as $hour => $departures ) {
			$rows[] = array( sprintf( '%02d', $hour % 24 ), $departures );
		}

		return $rows;
	}

	/**
	 * For templates copied into themes before departure_rows().
	 *
	 * @return list<array{0: string, 1: string[]}> [clock hour such as "05", minutes such as ["03", "33"]].
	 */
	public function rows(): array {
		return array_map(
			fn( array $row ) => array( $row[0], array_map( fn( TimetableDeparture $departure ) => $departure->minute, $row[1] ) ),
			$this->departure_rows()
		);
	}

	public function sort_key(): string {
		return $this->route->name . "\0" . $this->headsign;
	}
}
