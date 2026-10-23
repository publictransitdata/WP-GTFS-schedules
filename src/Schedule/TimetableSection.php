<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Departures of one route in one direction, laid out like a printed stop timetable:
 * one line per hour with the minutes of each departure.
 */
final class TimetableSection {

	/** @var array<int, int[]> Service hour (may be 24 or more after midnight) => minutes. */
	private array $minutes_by_hour = array();

	public function __construct(
		public readonly RouteBadge $route,
		public readonly string $headsign,
	) {}

	public function add_departure( int $seconds_into_service_day ): void {
		$hour = intdiv( $seconds_into_service_day, HOUR_IN_SECONDS );

		$this->minutes_by_hour[ $hour ][] = intdiv( $seconds_into_service_day % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
	}

	/**
	 * @return array<string, string[]> Clock hour such as "05" => minutes such as ["03", "33"].
	 */
	public function rows(): array {
		ksort( $this->minutes_by_hour );

		$rows = array();
		foreach ( $this->minutes_by_hour as $hour => $minutes ) {
			$clock_hour          = sprintf( '%02d', $hour % 24 );
			$rows[ $clock_hour ] = array_map( fn( int $minute ) => sprintf( '%02d', $minute ), $minutes );
		}

		return $rows;
	}

	public function sort_key(): string {
		return $this->route->name . "\0" . $this->headsign;
	}
}
