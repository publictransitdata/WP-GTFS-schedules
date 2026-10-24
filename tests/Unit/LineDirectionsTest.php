<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Network\LineDirection;
use GtfsTimetables\Network\LineDirections;
use GtfsTimetables\Network\LineStop;
use PHPUnit\Framework\TestCase;

final class LineDirectionsTest extends TestCase {

	public function test_each_direction_follows_the_route_most_of_its_trips_take(): void {
		$routes = LineDirections::usual_routes(
			array(
				$this->trip( 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 0, 'Jelitkowo', 'A', 'B', 'C', 'D' ),
				$this->trip( 0, 'Strzyża PKM', 'A', 'B' ),
				$this->trip( 1, 'Lawendowe Wzgórze', 'D', 'C', 'B', 'A' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'direction_id' => 0,
					'headsign'     => 'Jelitkowo',
					'stop_ids'     => array( 'A', 'B', 'C', 'D' ),
				),
				array(
					'direction_id' => 1,
					'headsign'     => 'Lawendowe Wzgórze',
					'stop_ids'     => array( 'D', 'C', 'B', 'A' ),
				),
			),
			$routes
		);
	}

	public function test_the_headsign_is_the_one_most_trips_on_that_route_show(): void {
		$routes = LineDirections::usual_routes(
			array(
				$this->trip( 0, 'Dworzec Główny', 'A', 'B' ),
				$this->trip( 0, 'Dworzec Główny przez Zaspa', 'A', 'B' ),
				$this->trip( 0, 'Dworzec Główny', 'A', 'B' ),
			)
		);

		$this->assertSame( 'Dworzec Główny', $routes[0]['headsign'] );
	}

	public function test_without_direction_ids_each_headsign_is_a_direction(): void {
		$routes = LineDirections::usual_routes(
			array(
				$this->trip( null, 'Oliwa', 'A', 'B' ),
				$this->trip( null, 'Zaspa', 'B', 'A' ),
			)
		);

		$this->assertSame( array( 'Oliwa', 'Zaspa' ), array_column( $routes, 'headsign' ) );
		$this->assertSame( array( null, null ), array_column( $routes, 'direction_id' ) );
	}

	public function test_numeric_stop_ids_survive_as_strings(): void {
		$routes = LineDirections::usual_routes( array( $this->trip( 0, '123', '1013' ) ) );

		$this->assertSame( array( '1013' ), $routes[0]['stop_ids'] );
		$this->assertSame( '123', $routes[0]['headsign'] );
	}

	public function test_a_direction_knows_its_stops_and_where_it_ends(): void {
		$direction = new LineDirection( 0, 'Jelitkowo', array( new LineStop( 'A', 'Start' ), new LineStop( 'B', 'End' ) ) );

		$this->assertTrue( $direction->has_stop( 'A' ) );
		$this->assertFalse( $direction->has_stop( 'Z' ) );
		$this->assertTrue( $direction->ends_at( 'B' ) );
		$this->assertFalse( $direction->ends_at( 'A' ) );
	}

	private function trip( ?int $direction_id, string $headsign, string ...$stop_ids ): array {
		return array(
			'direction_id' => $direction_id,
			'headsign'     => $headsign,
			'stop_ids'     => $stop_ids,
		);
	}
}
