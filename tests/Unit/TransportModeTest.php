<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use PtdGtfsSchedules\Network\TransportMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransportModeTest extends TestCase {

	#[DataProvider( 'route_types' )]
	public function test_recognises_basic_and_extended_route_types( int $route_type, TransportMode $mode ): void {
		$this->assertSame( $mode, TransportMode::from_route_type( $route_type ) );
	}

	public static function route_types(): array {
		return array(
			'tram'                     => array( 0, TransportMode::Tram ),
			'extended tram (Gdańsk)'   => array( 900, TransportMode::Tram ),
			'metro'                    => array( 1, TransportMode::Metro ),
			'extended underground'     => array( 402, TransportMode::Metro ),
			'rail'                     => array( 2, TransportMode::Rail ),
			'extended regional rail'   => array( 106, TransportMode::Rail ),
			'bus'                      => array( 3, TransportMode::Bus ),
			'extended bus (Gdańsk)'    => array( 700, TransportMode::Bus ),
			'extended night bus'       => array( 705, TransportMode::Bus ),
			'coach'                    => array( 200, TransportMode::Bus ),
			'trolleybus'               => array( 11, TransportMode::Trolleybus ),
			'extended trolleybus'      => array( 800, TransportMode::Trolleybus ),
			'ferry'                    => array( 4, TransportMode::Ferry ),
			'extended water transport' => array( 1000, TransportMode::Ferry ),
			'funicular'                => array( 7, TransportMode::Cable ),
			'extended aerial lift'     => array( 1300, TransportMode::Cable ),
			'unknown'                  => array( 1700, TransportMode::Other ),
		);
	}

	public function test_modes_are_listed_from_rapid_transit_to_buses(): void {
		$this->assertSame(
			array( 'metro', 'tram', 'trolleybus', 'bus', 'rail', 'ferry', 'cable', 'other' ),
			array_map( fn( TransportMode $mode ) => $mode->value, TransportMode::cases() )
		);
	}
}
