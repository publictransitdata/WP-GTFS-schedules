<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Network\LineDirection;
use GtfsTimetables\Network\LineDirections;
use PHPUnit\Framework\TestCase;

final class LineDirectionsTest extends TestCase {

	public function test_each_direction_follows_the_route_most_of_its_trips_take(): void {
		$directions = LineDirections::describe(
			array(
				$this->trip( 't1', 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 't2', 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 't3', 0, 'Strzyża PKM', 'A', 'B' ),
				$this->trip( 't4', 1, 'Lawendowe Wzgórze', 'D', 'C', 'B', 'A' ),
			)
		);

		$this->assertSame( array( 0, 1 ), array_column( $directions, 'direction_id' ) );
		$this->assertSame( array( 'Jelitkowo', 'Lawendowe Wzgórze' ), array_column( $directions, 'headsign' ) );
		$this->assertSame( array( 'A', 'B', 'C', 'D' ), $directions[0]['stop_ids'] );
	}

	public function test_short_workings_are_noted_where_they_end_and_in_the_trip_note(): void {
		$direction = LineDirections::describe(
			array(
				$this->trip( 't1', 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 't2', 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 't3', 0, 'Strzyża PKM', 'A', 'B' ),
			)
		)[0];

		$this->assertSame( array( 1 ), $direction['ends_early_at'] );
		$this->assertSame(
			array(
				't3' => array(
					'to'  => 'Strzyża PKM',
					'via' => null,
				),
			),
			$direction['trip_notes']
		);
	}

	public function test_a_detour_becomes_a_branch_and_its_trips_say_which_way_they_go(): void {
		$direction = LineDirections::describe(
			array(
				$this->trip( 't1', 0, 'Oliwa', 'A', 'B', 'C', 'D' ),
				$this->trip( 't2', 0, 'Oliwa', 'A', 'B', 'C', 'D' ),
				$this->trip( 't3', 0, 'Oliwa', 'A', 'B', 'School', 'C', 'D' ),
				$this->trip( 't4', 0, 'Oliwa', 'A', 'B', 'School', 'C', 'D' ),
			)
		)[0];

		$this->assertSame(
			array(
				array(
					'leaves_after' => 1,
					'rejoins_at'   => 2,
					'stop_ids'     => array( 'School' ),
				),
			),
			$direction['branches']
		);
		$this->assertSame( array( 'School', 'School' ), array_column( $direction['trip_notes'], 'via' ) );
	}

	/**
	 * Line 21 in Gdynia: most trips set off empty from Gdynia Dworzec Gł. PKP 50 and pass Grabówek SKM 51
	 * without taking anyone on or off; two trips start further along.
	 */
	public function test_riders_see_only_stops_where_they_can_get_on_or_off(): void {
		$no_service  = array( 1, 1 );
		$regular     = array( 0, 0 );
		$on_request  = array( 3, 3 );
		$getting_off = array( 1, 0 );
		$full_trip   = $this->trip( 't1', 1, 'Sopot Reja', 'Dworzec 50', 'Dworzec 03', 'Grabówek 51', 'Kapliczka', 'Hospital', 'Reja' );
		$full_calls  = array( $no_service, $regular, $no_service, $on_request, $getting_off, $regular );
		$short_trip  = $this->trip( 't3', 1, 'Sopot Reja', 'Kapliczka', 'Hospital', 'Reja' );
		$short_calls = array( $on_request, $getting_off, $regular );

		$direction = LineDirections::describe(
			array(
				$full_trip + array( 'calls' => $full_calls ),
				array( 'trip_id' => 't2' ) + $full_trip + array( 'calls' => $full_calls ),
				$short_trip + array( 'calls' => $short_calls ),
			)
		)[0];

		$this->assertSame( array( 'Dworzec 03', 'Kapliczka', 'Hospital', 'Reja' ), $direction['stop_ids'] );
		$this->assertSame( array( 0 ), $direction['partial_at'] );

		$line_direction = LineDirection::from_array( $direction + array( 'stop_names' => array() ) );

		$this->assertFalse( $line_direction->has_stop( 'Dworzec 50' ) );
		$this->assertFalse( $line_direction->has_stop( 'Grabówek 51' ) );
		$this->assertFalse( $line_direction->can_board_at( 'Hospital' ) );
		$this->assertTrue( $line_direction->can_alight_at( 'Hospital' ) );
		$this->assertTrue( $line_direction->stops_on_request_at( 'Kapliczka' ) );
		$this->assertFalse( $line_direction->stops_on_request_at( 'Reja' ) );
		$this->assertSame( 'Dworzec 03', $line_direction->first_boarding_stop()?->stop_id );
	}

	public function test_the_headsign_is_the_one_most_trips_on_that_route_show(): void {
		$direction = LineDirections::describe(
			array(
				$this->trip( 't1', 0, 'Dworzec Główny', 'A', 'B' ),
				$this->trip( 't2', 0, 'Dworzec Główny przez Zaspa', 'A', 'B' ),
				$this->trip( 't3', 0, 'Dworzec Główny', 'A', 'B' ),
			)
		)[0];

		$this->assertSame( 'Dworzec Główny', $direction['headsign'] );
		$this->assertSame( array( 't2' ), array_keys( $direction['trip_notes'] ) );
	}

	public function test_without_direction_ids_each_headsign_is_a_direction(): void {
		$directions = LineDirections::describe(
			array(
				$this->trip( 't1', null, 'Oliwa', 'A', 'B' ),
				$this->trip( 't2', null, 'Zaspa', 'B', 'A' ),
			)
		);

		$this->assertSame( array( 'Oliwa', 'Zaspa' ), array_column( $directions, 'headsign' ) );
		$this->assertSame( array( null, null ), array_column( $directions, 'direction_id' ) );
	}

	public function test_numeric_ids_and_headsigns_survive_as_strings(): void {
		$direction = LineDirections::describe(
			array(
				$this->trip( '1001', 0, '123', '1013', '1014' ),
				$this->trip( '1002', 0, '123', '1013', '1014' ),
				$this->trip( '1003', 0, '456', '1013', '1014' ),
			)
		)[0];

		$this->assertSame( array( '1013', '1014' ), $direction['stop_ids'] );
		$this->assertSame( '123', $direction['headsign'] );
		$this->assertSame( '456', LineDirection::from_array( $direction + array( 'stop_names' => array() ) )->note_for( '1003' )?->to );
	}

	public function test_a_direction_knows_its_stops_branches_and_where_it_ends(): void {
		$direction = LineDirection::from_array(
			array(
				'direction_id'   => 0,
				'headsign'       => 'Oliwa',
				'stop_ids'       => array( 'A', 'B', 'C' ),
				'branches'       => array(
					array(
						'leaves_after' => 0,
						'rejoins_at'   => 1,
						'stop_ids'     => array( 'X' ),
					),
					array(
						'leaves_after' => -1,
						'rejoins_at'   => 1,
						'stop_ids'     => array( 'Depot' ),
					),
				),
				'ends_early_at'  => array(),
				'starts_late_at' => array(),
				'trip_notes'     => array(),
				'stop_names'     => array( 'X' => 'School' ),
			)
		);

		$this->assertTrue( $direction->has_stop( 'X' ) );
		$this->assertSame( 'School', $direction->stop( 'X' )?->name );
		$this->assertFalse( $direction->has_stop( 'Z' ) );
		$this->assertTrue( $direction->ends_at( 'C' ) );
		$this->assertCount( 1, $direction->branches_after( 0 ) );
		$this->assertTrue( $direction->branches_after( 0 )[0]->is_detour() );
		$this->assertCount( 1, $direction->branches_joining_at( 1 ) );
		$this->assertSame( array(), $direction->branches_after( -1 ) );
	}

	private function trip( string $trip_id, ?int $direction_id, string $headsign, string ...$stop_ids ): array {
		return array(
			'trip_id'      => $trip_id,
			'direction_id' => $direction_id,
			'headsign'     => $headsign,
			'stop_ids'     => $stop_ids,
		);
	}
}
