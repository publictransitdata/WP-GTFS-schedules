<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use PtdGtfsSchedules\Network\Line;
use PtdGtfsSchedules\Network\TransportMode;
use PtdGtfsSchedules\Schedule\RouteBadge;
use PHPUnit\Framework\TestCase;

final class LineTest extends TestCase {

	public function test_lines_are_ordered_by_number_not_alphabetically(): void {
		$this->assertSame(
			array( '2', '10', '100', '154', 'C10', 'M32', 'N1', 'N8', 'N14', 'T8' ),
			$this->ordered( '154', 'N14', '2', 'T8', '100', 'N1', 'M32', '10', 'C10', 'N8' )
		);
	}

	public function test_the_feeds_own_order_comes_first(): void {
		$lines = array(
			$this->line( 'Z', 2 ),
			$this->line( 'A' ),
			$this->line( 'M', 1 ),
		);

		usort( $lines, array( Line::class, 'in_network_order' ) );

		$this->assertSame( array( 'M', 'Z', 'A' ), array_map( fn( Line $line ) => $line->name(), $lines ) );
	}

	public function test_lines_named_n_and_a_digit_look_like_night_lines(): void {
		$this->assertTrue( $this->line( 'N8' )->looks_like_night_line() );
		$this->assertTrue( $this->line( 'n14' )->looks_like_night_line() );
		$this->assertFalse( $this->line( '8' )->looks_like_night_line() );
		$this->assertFalse( $this->line( 'Nord' )->looks_like_night_line() );
	}

	/**
	 * @return string[]
	 */
	private function ordered( string ...$names ): array {
		$lines = array_map( fn( string $name ) => $this->line( $name ), $names );
		usort( $lines, array( Line::class, 'in_network_order' ) );

		return array_map( fn( Line $line ) => $line->name(), $lines );
	}

	private function line( string $name, ?int $sort_order = null ): Line {
		return new Line( new RouteBadge( $name, $name, '', '' ), TransportMode::Bus, array(), $sort_order );
	}
}
