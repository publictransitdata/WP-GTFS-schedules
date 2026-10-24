<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use PtdGtfsSchedules\Network\RouteComparison;
use PHPUnit\Framework\TestCase;

final class RouteComparisonTest extends TestCase {

	private const USUAL = array( 'A', 'B', 'C', 'D', 'E' );

	public function test_the_usual_route_has_no_differences(): void {
		$this->assertSame( $this->differences( array( 0, 1, 2, 3, 4 ) ), RouteComparison::compare( self::USUAL, self::USUAL ) );
	}

	public function test_a_detour_leaves_the_route_and_rejoins_it(): void {
		$this->assertSame(
			$this->differences( array( 0, 1, 3, 4 ), array( $this->branch( 1, 3, 'X', 'Y' ) ) ),
			RouteComparison::compare( self::USUAL, array( 'A', 'B', 'X', 'Y', 'D', 'E' ) )
		);
	}

	public function test_a_trip_that_ends_early_turns_back_at_a_stop_of_the_route(): void {
		$this->assertSame(
			$this->differences( array( 0, 1, 2 ), array(), 2 ),
			RouteComparison::compare( self::USUAL, array( 'A', 'B', 'C' ) )
		);
	}

	public function test_a_trip_that_starts_later_joins_at_a_stop_of_the_route(): void {
		$this->assertSame(
			$this->differences( array( 2, 3, 4 ), array(), null, 2 ),
			RouteComparison::compare( self::USUAL, array( 'C', 'D', 'E' ) )
		);
	}

	public function test_a_trip_to_the_depot_carries_on_past_the_route(): void {
		$this->assertSame(
			$this->differences( array( 0, 1, 2, 3 ), array( $this->branch( 3, null, 'Depot' ) ) ),
			RouteComparison::compare( self::USUAL, array( 'A', 'B', 'C', 'D', 'Depot' ) )
		);
	}

	public function test_a_trip_from_the_depot_joins_the_route_on_the_way(): void {
		$this->assertSame(
			$this->differences( array( 1, 2, 3, 4 ), array( $this->branch( -1, 1, 'Depot' ) ) ),
			RouteComparison::compare( self::USUAL, array( 'Depot', 'B', 'C', 'D', 'E' ) )
		);
	}

	/**
	 * @param int[]    $shared Positions of the usual stops the variant calls at.
	 * @param array    $branches
	 * @param int|null $ends_early_at
	 * @param int|null $starts_late_at
	 */
	private function differences( array $shared, array $branches = array(), ?int $ends_early_at = null, ?int $starts_late_at = null ): array {
		return array(
			'branches'       => $branches,
			'ends_early_at'  => $ends_early_at,
			'starts_late_at' => $starts_late_at,
			'shared'         => $shared,
		);
	}

	private function branch( int $leaves_after, ?int $rejoins_at, string ...$stop_ids ): array {
		return array(
			'leaves_after' => $leaves_after,
			'rejoins_at'   => $rejoins_at,
			'stop_ids'     => $stop_ids,
		);
	}
}
