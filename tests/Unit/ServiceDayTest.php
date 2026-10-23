<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use GtfsTimetables\Schedule\ServiceDay;
use PHPUnit\Framework\TestCase;

/**
 * In Europe/Warsaw clocks go forward on 2026-03-29 and back on 2026-10-25.
 */
final class ServiceDayTest extends TestCase {

	public function test_an_ordinary_service_day_starts_at_midnight(): void {
		$this->assertSame( '2026-10-23 00:00', $this->start_of( '2026-10-23' ) );
	}

	public function test_when_clocks_go_forward_the_service_day_starts_at_23_00_the_evening_before(): void {
		$this->assertSame( '2026-03-28 23:00', $this->start_of( '2026-03-29' ) );
	}

	public function test_when_clocks_go_back_the_service_day_starts_at_01_00(): void {
		$this->assertSame( '2026-10-25 01:00', $this->start_of( '2026-10-25' ) );
	}

	public function test_trips_from_yesterday_today_and_tomorrow_can_be_running_at_any_moment(): void {
		$dates = ServiceDay::dates_around( $this->warsaw( '2026-10-23 00:30' ) );

		$this->assertSame(
			array( '2026-10-22', '2026-10-23', '2026-10-24' ),
			array_map( fn( DateTimeImmutable $date ) => $date->format( 'Y-m-d' ), $dates )
		);
	}

	private function start_of( string $date ): string {
		return ServiceDay::start( $this->warsaw( $date ) )->format( 'Y-m-d H:i' );
	}

	private function warsaw( string $moment ): DateTimeImmutable {
		return new DateTimeImmutable( $moment, new DateTimeZone( 'Europe/Warsaw' ) );
	}
}
