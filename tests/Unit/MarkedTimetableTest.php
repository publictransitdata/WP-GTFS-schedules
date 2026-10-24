<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use GtfsTimetables\Network\TripNote;
use GtfsTimetables\Schedule\MarkedTimetable;
use GtfsTimetables\Schedule\TimetableDeparture;
use PHPUnit\Framework\TestCase;

final class MarkedTimetableTest extends TestCase {

	public function test_departures_are_listed_by_hour_with_their_minutes(): void {
		$timetable = $this->timetable( array( '05:03', '' ), array( '05:33', '' ), array( '06:03', '' ) );

		$this->assertSame(
			array(
				array( '05', array( '03', '33' ) ),
				array( '06', array( '03' ) ),
			),
			$this->minutes_by_hour( $timetable )
		);
	}

	public function test_trips_that_differ_get_a_letter_explained_in_the_legend(): void {
		$timetable = $this->timetable(
			array( '05:03', 'to Strzyża PKM' ),
			array( '05:33', '' ),
			array( '06:03', 'via School' ),
			array( '06:33', 'to Strzyża PKM' )
		);

		$marks = array_map( fn( TimetableDeparture $departure ) => $departure->mark, array_merge( ...array_column( $timetable->rows(), 1 ) ) );

		$this->assertSame( array( 'a', null, 'b', 'a' ), $marks );
		$this->assertSame(
			array(
				'a' => 'to Strzyża PKM',
				'b' => 'via School',
			),
			$timetable->legend()
		);
	}

	public function test_hours_after_midnight_come_last_with_the_clock_hour(): void {
		$timetable = $this->timetable( array( '28:18', '' ), array( '04:47', '' ) );

		$this->assertSame(
			array(
				array( '04', array( '47' ) ),
				array( '04', array( '18' ) ),
			),
			$this->minutes_by_hour( $timetable )
		);
	}

	public function test_the_next_departure_is_the_first_that_has_not_left(): void {
		$timetable = $this->timetable( array( '08:00', '' ), array( '08:20', '' ), array( '08:40', '' ) );
		$now       = $this->warsaw( '2026-10-24 08:10' );

		$next = $timetable->next_departure( $now );

		$this->assertSame( '20', $next?->minute );
		$this->assertTrue( $timetable->rows()[0][1][0]->has_left( $now ) );
		$this->assertNull( $timetable->next_departure( $this->warsaw( '2026-10-24 09:00' ) ) );
	}

	public function test_a_trip_note_says_where_it_goes_and_which_way(): void {
		$this->assertSame( 'to Strzyża PKM', ( new TripNote( 'Strzyża PKM', null ) )->text() );
		$this->assertSame( 'via School', ( new TripNote( null, 'School' ) )->text() );
		$this->assertSame( 'to Depot, via School', ( new TripNote( 'Depot', 'School' ) )->text() );
	}

	/**
	 * @param array{0: string, 1: string} ...$departures GTFS time "HH:MM" and note.
	 */
	private function timetable( array ...$departures ): MarkedTimetable {
		$timetable = new MarkedTimetable( $this->warsaw( '2026-10-24 00:00' ) );

		foreach ( $departures as list( $time, $note ) ) {
			list( $hours, $minutes ) = array_map( 'intval', explode( ':', $time ) );
			$timetable->add( $hours * 3600 + $minutes * 60, $note );
		}

		return $timetable;
	}

	private function minutes_by_hour( MarkedTimetable $timetable ): array {
		return array_map(
			fn( array $row ) => array( $row[0], array_map( fn( TimetableDeparture $departure ) => $departure->minute, $row[1] ) ),
			$timetable->rows()
		);
	}

	private function warsaw( string $moment ): DateTimeImmutable {
		return new DateTimeImmutable( $moment, new DateTimeZone( 'Europe/Warsaw' ) );
	}
}
