<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PtdGtfsSchedules\Schedule\Departure;
use PtdGtfsSchedules\Schedule\ServiceDay;
use PHPUnit\Framework\TestCase;

final class DepartureTest extends TestCase {

	public function test_scheduled_times_stay_on_the_clock_when_clocks_go_forward(): void {
		$departure = $this->departure( '2026-03-29', '08:00:00' );

		$this->assertSame( '2026-03-29 08:00', $departure->time->format( 'Y-m-d H:i' ) );
	}

	public function test_scheduled_times_stay_on_the_clock_when_clocks_go_back(): void {
		$departure = $this->departure( '2026-10-25', '08:00:00' );

		$this->assertSame( '2026-10-25 08:00', $departure->time->format( 'Y-m-d H:i' ) );
	}

	public function test_a_time_after_24_00_departs_on_the_next_calendar_day(): void {
		$departure = $this->departure( '2026-10-23', '25:10:00' );

		$this->assertSame( '2026-10-24 01:10', $departure->time->format( 'Y-m-d H:i' ) );
	}

	public function test_a_departure_within_the_current_minute_is_labelled_now(): void {
		$departure = $this->departure( '2026-10-23', '08:00:00' );

		$this->assertSame( 'now', $departure->label( $this->warsaw( '2026-10-23 08:00:40' ) ) );
	}

	public function test_departures_within_half_an_hour_show_a_countdown(): void {
		$departure = $this->departure( '2026-10-23', '08:30:00' );

		$this->assertSame( 'in 5 min', $departure->label( $this->warsaw( '2026-10-23 08:25:00' ) ) );
		$this->assertSame( 'in 30 min', $departure->label( $this->warsaw( '2026-10-23 08:00:00' ) ) );
	}

	public function test_later_departures_show_the_clock_time(): void {
		$departure = $this->departure( '2026-10-23', '08:31:00' );

		$this->assertSame( '08:31', $departure->label( $this->warsaw( '2026-10-23 08:00:00' ) ) );
	}

	private function departure( string $service_date, string $gtfs_time ): Departure {
		list( $hours, $minutes, $seconds ) = array_map( 'intval', explode( ':', $gtfs_time ) );

		$row = (object) array(
			'departure_secs'   => $hours * 3600 + $minutes * 60 + $seconds,
			'headsign'         => 'Dworzec Główny',
			'route_id'         => '154',
			'route_short_name' => '154',
			'route_long_name'  => '',
			'route_color'      => '',
			'route_text_color' => '',
		);

		return Departure::from_row( $row, ServiceDay::start( $this->warsaw( $service_date ) ) );
	}

	private function warsaw( string $moment ): DateTimeImmutable {
		return new DateTimeImmutable( $moment, new DateTimeZone( 'Europe/Warsaw' ) );
	}
}
