<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use PtdGtfsSchedules\Schedule\RouteBadge;
use PtdGtfsSchedules\Schedule\TimetableSection;
use PHPUnit\Framework\TestCase;

final class TimetableSectionTest extends TestCase {

	public function test_lists_the_minutes_of_each_hour(): void {
		$section = $this->section_with( '05:03', '05:33', '06:03' );

		$this->assertSame(
			array(
				array( '05', array( '03', '33' ) ),
				array( '06', array( '03' ) ),
			),
			$section->rows()
		);
	}

	public function test_hours_after_midnight_come_after_23_and_show_the_clock_hour(): void {
		$section = $this->section_with( '23:50', '24:10', '25:40' );

		$this->assertSame(
			array(
				array( '23', array( '50' ) ),
				array( '00', array( '10' ) ),
				array( '01', array( '40' ) ),
			),
			$section->rows()
		);
	}

	/**
	 * Night bus N8 in Gdańsk leaves Brama Wyżynna at 04:47 and again at 28:18 on the same service day.
	 */
	public function test_an_early_morning_hour_and_the_same_hour_after_midnight_are_both_kept(): void {
		$section = $this->section_with( '04:47', '23:48', '28:18' );

		$this->assertSame(
			array(
				array( '04', array( '47' ) ),
				array( '23', array( '48' ) ),
				array( '04', array( '18' ) ),
			),
			$section->rows()
		);
	}

	private function section_with( string ...$times ): TimetableSection {
		$section = new TimetableSection( new RouteBadge( 'N8', 'N8', '', '' ), 'Dworzec Główny' );

		foreach ( $times as $time ) {
			list( $hours, $minutes ) = array_map( 'intval', explode( ':', $time ) );
			$section->add_departure( $hours * 3600 + $minutes * 60 );
		}

		return $section;
	}
}
