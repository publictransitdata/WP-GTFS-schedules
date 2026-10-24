<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * One line's departures from one stop on one service day, laid out like a printed timetable:
 * a row per hour with the minutes of each departure. Trips that differ from the usual one
 * carry a letter, explained in a legend below: "a — to Strzyża PKM".
 */
final class MarkedTimetable {

	private const MARKS = 'abcdefghijklmnopqrstuvwxyz';

	/** @var array<int, TimetableDeparture[]> Service hour (24 and more after midnight) => departures. */
	private array $departures_by_hour = array();

	/** @var array<string, string> Footnote => its letter. */
	private array $marks = array();

	public function __construct(
		private readonly DateTimeImmutable $service_day_start,
	) {}

	/**
	 * @param int    $seconds_into_service_day
	 * @param string $note Empty for a trip on the usual route.
	 */
	public function add( int $seconds_into_service_day, string $note = '' ): void {
		$mark = '' === $note ? null : $this->mark_for( $note );
		$time = $this->service_day_start->setTimestamp( $this->service_day_start->getTimestamp() + $seconds_into_service_day );

		$this->departures_by_hour[ intdiv( $seconds_into_service_day, HOUR_IN_SECONDS ) ][] = new TimetableDeparture(
			$time,
			sprintf( '%02d', intdiv( $seconds_into_service_day % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ) ),
			$mark,
			'' === $note ? null : $note
		);
	}

	public function is_empty(): bool {
		return ! $this->departures_by_hour;
	}

	/**
	 * Hours after midnight come last and show the clock hour (25 → 01).
	 *
	 * @return list<array{0: string, 1: TimetableDeparture[]}> [clock hour such as "05", its departures].
	 */
	public function rows(): array {
		ksort( $this->departures_by_hour );

		$rows = array();
		foreach ( $this->departures_by_hour as $hour => $departures ) {
			usort( $departures, fn( TimetableDeparture $a, TimetableDeparture $b ) => $a->time <=> $b->time );
			$rows[] = array( sprintf( '%02d', $hour % 24 ), $departures );
		}

		return $rows;
	}

	/**
	 * @return array<string, string> Letter => footnote, in alphabetical order.
	 */
	public function legend(): array {
		return array_flip( $this->marks );
	}

	/**
	 * The first departure at or after $now; null when every one has left.
	 */
	public function next_departure( DateTimeImmutable $now ): ?TimetableDeparture {
		foreach ( $this->rows() as list( , $departures ) ) {
			foreach ( $departures as $departure ) {
				if ( ! $departure->has_left( $now ) ) {
					return $departure;
				}
			}
		}

		return null;
	}

	private function mark_for( string $note ): string {
		$this->marks[ $note ] ??= self::MARKS[ count( $this->marks ) % strlen( self::MARKS ) ];

		return $this->marks[ $note ];
	}
}
