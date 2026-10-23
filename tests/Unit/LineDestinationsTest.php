<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Network\Lines;
use PHPUnit\Framework\TestCase;

/**
 * Headsign counts as the database returns them: most frequent first.
 */
final class LineDestinationsTest extends TestCase {

	public function test_the_most_frequent_headsign_of_each_direction_names_both_ends(): void {
		$destinations = Lines::destinations_from(
			array(
				$this->headsign( '1', 'Lawendowe Wzgórze' ),
				$this->headsign( '0', 'Jelitkowo' ),
				$this->headsign( '0', 'Strzyża PKM' ),
				$this->headsign( '0', 'Nowy Port Zajezdnia' ),
			)
		);

		$this->assertSame( array( 'Jelitkowo', 'Lawendowe Wzgórze' ), $destinations );
	}

	public function test_a_loop_line_has_one_destination(): void {
		$destinations = Lines::destinations_from(
			array(
				$this->headsign( '0', 'Dworzec Główny' ),
				$this->headsign( '1', 'Dworzec Główny' ),
			)
		);

		$this->assertSame( array( 'Dworzec Główny' ), $destinations );
	}

	public function test_without_directions_the_two_most_frequent_headsigns_are_used(): void {
		$destinations = Lines::destinations_from(
			array(
				$this->headsign( null, 'Zaspa' ),
				$this->headsign( null, 'Orunia Górna' ),
				$this->headsign( null, 'Brzeźno' ),
			)
		);

		$this->assertSame( array( 'Orunia Górna', 'Zaspa' ), $destinations );
	}

	public function test_empty_headsigns_are_ignored(): void {
		$this->assertSame( array(), Lines::destinations_from( array( $this->headsign( '0', ' ' ) ) ) );
	}

	private function headsign( ?string $direction_id, string $name ): array {
		return array(
			'direction_id'  => $direction_id,
			'trip_headsign' => $name,
		);
	}
}
