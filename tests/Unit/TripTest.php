<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PtdGtfsSchedules\Network\LineDirection;
use PtdGtfsSchedules\Schedule\Trip;
use PtdGtfsSchedules\Schedule\TripCall;
use PHPUnit\Framework\TestCase;

final class TripTest extends TestCase {

	public function test_stops_where_nobody_can_get_on_or_off_are_left_out(): void {
		$trip = $this->trip(
			$this->call( 'depot', '08:00', '', 1, 1 ),
			$this->call( 'A', '08:05' ),
			$this->call( 'timing point', '08:07', '', 1, 1 ),
			$this->call( 'B', '08:10' )
		);

		$this->assertSame( array( 'A', 'B' ), array_map( fn( TripCall $call ) => $call->stop_id, $trip->calls ) );
		$this->assertTrue( $trip->calls[0]->is_first );
		$this->assertTrue( $trip->calls[1]->is_last );
	}

	public function test_the_first_stop_shows_the_departure_and_the_last_the_arrival(): void {
		$trip = $this->trip(
			$this->call( 'A', '08:05', '08:00' ),
			$this->call( 'B', '08:20', '08:10' )
		);

		$this->assertSame( '08:05', $trip->calls[0]->time()->format( 'H:i' ) );
		$this->assertSame( '08:10', $trip->calls[1]->time()->format( 'H:i' ) );
		$this->assertFalse( $trip->calls[0]->shows_arrival_and_departure() );
		$this->assertFalse( $trip->calls[1]->shows_arrival_and_departure() );
	}

	public function test_a_layover_shows_both_the_arrival_and_the_departure(): void {
		$trip = $this->trip(
			$this->call( 'A', '14:00' ),
			$this->call( 'B', '14:38', '14:32' ),
			$this->call( 'C', '14:45' )
		);

		$layover = $trip->calls[1];

		$this->assertTrue( $layover->shows_arrival_and_departure() );
		$this->assertSame( '14:32', $layover->arrival->format( 'H:i' ) );
		$this->assertSame( '14:38', $layover->departure->format( 'H:i' ) );
		$this->assertFalse( $trip->calls[2]->shows_arrival_and_departure() );
	}

	public function test_times_after_24_00_fall_on_the_next_calendar_day(): void {
		$trip = $this->trip(
			$this->call( 'A', '23:50' ),
			$this->call( 'B', '25:10' )
		);

		$this->assertSame( '2026-10-24 01:10', $trip->calls[1]->time()->format( 'Y-m-d H:i' ) );
	}

	public function test_a_stop_counts_as_passed_once_the_trip_has_left_it(): void {
		$trip = $this->trip(
			$this->call( 'A', '08:00' ),
			$this->call( 'B', '08:12', '08:10' ),
			$this->call( 'C', '08:20' )
		);
		$now  = $this->warsaw( '2026-10-23 08:11' );

		$this->assertTrue( $trip->calls[0]->has_left( $now ) );
		$this->assertFalse( $trip->calls[1]->has_left( $now ) );
	}

	public function test_getting_on_or_off_only_is_not_noted_where_it_goes_without_saying(): void {
		$trip = $this->trip(
			$this->call( 'A', '08:00', '', 0, 1 ),
			$this->call( 'B', '08:05', '', 0, 1 ),
			$this->call( 'C', '08:10', '', 1, 0 ),
			$this->call( 'D', '08:15', '', 1, 0 )
		);

		$this->assertFalse( $trip->calls[0]->is_boarding_only() );
		$this->assertTrue( $trip->calls[1]->is_boarding_only() );
		$this->assertTrue( $trip->calls[2]->is_alighting_only() );
		$this->assertFalse( $trip->calls[3]->is_alighting_only() );
	}

	public function test_a_stop_on_request_is_marked(): void {
		$trip = $this->trip(
			$this->call( 'A', '08:00' ),
			$this->call( 'B', '08:05', '', 3, 3 ),
			$this->call( 'C', '08:10' )
		);

		$this->assertFalse( $trip->calls[0]->on_request );
		$this->assertTrue( $trip->calls[1]->on_request );
	}

	public function test_on_a_loop_the_rider_boards_where_the_trip_goes_on(): void {
		$trip = $this->trip(
			$this->call( 'A', '08:00' ),
			$this->call( 'B', '08:05' ),
			$this->call( 'A', '08:10' )
		);

		$this->assertSame( 0, $trip->boarding_position( 'A' ) );
		$this->assertSame( 1, $trip->boarding_position( 'B' ) );
		$this->assertNull( $trip->boarding_position( 'Z' ) );
	}

	public function test_without_a_headsign_the_trip_goes_to_its_last_stop(): void {
		$trip = $this->trip( $this->call( 'A', '08:00' ), $this->call( 'B', '08:10' ) );

		$this->assertSame( 'Stop B', $trip->destination() );
	}

	public function test_a_trip_belongs_to_the_direction_with_its_direction_id(): void {
		$trip = $this->trip( $this->call( 'A', '08:00' ), $this->call( 'B', '08:10' ) );

		$this->assertTrue( ( new LineDirection( null, '', array() ) )->is_taken_by( $trip ) );
		$this->assertFalse( ( new LineDirection( 1, '', array() ) )->is_taken_by( $trip ) );
	}

	public function test_without_direction_ids_a_trip_belongs_to_the_direction_of_its_headsign(): void {
		$trip = Trip::from_rows( $this->trip_row( 'Jelitkowo' ), array( $this->call( 'A', '08:00' ) ), $this->warsaw( '2026-10-23' ) );

		$this->assertTrue( ( new LineDirection( null, 'Jelitkowo', array() ) )->is_taken_by( $trip ) );
		$this->assertFalse( ( new LineDirection( null, 'Oliwa', array() ) )->is_taken_by( $trip ) );
	}

	private function trip( object ...$stop_times ): Trip {
		return Trip::from_rows( $this->trip_row( '' ), $stop_times, $this->warsaw( '2026-10-23' ) );
	}

	private function trip_row( string $headsign ): object {
		return (object) array(
			'trip_id'          => 'T1',
			'headsign'         => $headsign,
			'direction_id'     => null,
			'service_id'       => 'weekdays',
			'route_id'         => '10006',
			'route_short_name' => '6',
			'route_long_name'  => '',
			'route_color'      => '',
			'route_text_color' => '',
		);
	}

	private function call( string $stop_id, string $departure, string $arrival = '', int $pickup_type = 0, int $drop_off_type = 0 ): object {
		$seconds = function ( string $time ): int {
			list( $hours, $minutes ) = array_map( 'intval', explode( ':', $time ) );

			return $hours * 3600 + $minutes * 60;
		};

		return (object) array(
			'stop_id'        => $stop_id,
			'stop_name'      => "Stop {$stop_id}",
			'departure_secs' => $seconds( $departure ),
			'arrival_secs'   => '' === $arrival ? null : $seconds( $arrival ),
			'pickup_type'    => $pickup_type,
			'drop_off_type'  => $drop_off_type,
		);
	}

	private function warsaw( string $moment ): DateTimeImmutable {
		return new DateTimeImmutable( $moment, new DateTimeZone( 'Europe/Warsaw' ) );
	}
}
