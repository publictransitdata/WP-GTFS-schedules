<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Tests\Unit;

use PtdGtfsSchedules\Schedule\GtfsTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GtfsTimeTest extends TestCase {

	public function test_counts_seconds_from_the_start_of_the_service_day(): void {
		$this->assertSame( 8 * 3600 + 5 * 60 + 30, GtfsTime::to_seconds( '08:05:30' ) );
	}

	public function test_times_after_midnight_continue_past_24_hours(): void {
		$this->assertSame( 25 * 3600 + 10 * 60, GtfsTime::to_seconds( '25:10:00' ) );
	}

	public function test_accepts_single_digit_hours_and_surrounding_spaces(): void {
		$this->assertSame( 7 * 3600, GtfsTime::to_seconds( ' 7:00:00 ' ) );
	}

	#[DataProvider( 'malformed_times' )]
	public function test_rejects_malformed_times( string $time ): void {
		$this->assertNull( GtfsTime::to_seconds( $time ) );
	}

	public static function malformed_times(): array {
		return array(
			'empty'          => array( '' ),
			'no seconds'     => array( '12:00' ),
			'60 minutes'     => array( '08:60:00' ),
			'60 seconds'     => array( '08:00:60' ),
			'not a time'     => array( 'noon' ),
			'decimal number' => array( '8.5' ),
		);
	}
}
