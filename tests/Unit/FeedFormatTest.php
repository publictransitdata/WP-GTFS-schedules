<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Feed\FeedFile;
use GtfsTimetables\Feed\FeedFormat;
use PHPUnit\Framework\TestCase;

final class FeedFormatTest extends TestCase {

	public function test_a_stop_time_is_stored_as_seconds_into_the_service_day(): void {
		$stop_time = $this->import_row(
			'stop_times.txt',
			array(
				'trip_id'        => 'T1',
				'arrival_time'   => '28:16:00',
				'departure_time' => '28:18:00',
				'stop_id'        => '1030',
				'stop_sequence'  => '4',
				'pickup_type'    => '0',
			)
		);

		$this->assertSame( 28 * 3600 + 18 * 60, $stop_time['departure_secs'] );
		$this->assertSame( 4, $stop_time['stop_sequence'] );
	}

	public function test_a_stop_time_keeps_whether_riders_can_get_on_and_off(): void {
		$stop_time = $this->import_row(
			'stop_times.txt',
			array(
				'trip_id'        => 'T1',
				'departure_time' => '08:00:00',
				'stop_id'        => '36499',
				'stop_sequence'  => '0',
				'pickup_type'    => '1',
				'drop_off_type'  => '3',
			)
		);

		$this->assertSame( 1, $stop_time['pickup_type'] );
		$this->assertSame( 3, $stop_time['drop_off_type'] );
	}

	public function test_a_stop_time_without_departure_time_uses_the_arrival_time(): void {
		$stop_time = $this->import_row(
			'stop_times.txt',
			array(
				'trip_id'        => 'T1',
				'arrival_time'   => '08:00:00',
				'departure_time' => '',
				'stop_id'        => '1030',
				'stop_sequence'  => '1',
			)
		);

		$this->assertSame( 8 * 3600, $stop_time['departure_secs'] );
	}

	public function test_a_stop_time_without_any_time_is_skipped(): void {
		$file = $this->feed_file( 'stop_times.txt' );

		$this->assertNull(
			$file->map(
				array(
					'trip_id'        => 'T1',
					'arrival_time'   => '',
					'departure_time' => '',
					'stop_id'        => '1030',
					'stop_sequence'  => '2',
				)
			)
		);
	}

	public function test_route_colours_are_normalised_and_invalid_ones_dropped(): void {
		$route = $this->import_row(
			'routes.txt',
			array(
				'route_id'         => '408',
				'route_short_name' => 'N8',
				'route_color'      => '#ff8800',
				'route_text_color' => 'white',
			)
		);

		$this->assertSame( 'FF8800', $route['route_color'] );
		$this->assertSame( '', $route['route_text_color'] );
	}

	public function test_a_route_without_a_type_is_treated_as_a_bus(): void {
		$route = $this->import_row( 'routes.txt', array( 'route_id' => '408' ) );

		$this->assertSame( 3, $route['route_type'] );
	}

	public function test_calendar_dates_are_stored_as_yyyymmdd_numbers(): void {
		$exception = $this->import_row(
			'calendar_dates.txt',
			array(
				'service_id'     => '120261024',
				'date'           => '20261024',
				'exception_type' => '1',
			)
		);

		$this->assertSame( 20261024, $exception['service_date'] );
		$this->assertSame( 1, $exception['exception_type'] );
	}

	public function test_only_1_marks_a_weekday_as_running(): void {
		$service = $this->import_row(
			'calendar.txt',
			array(
				'service_id' => 'weekdays',
				'monday'     => '1',
				'tuesday'    => ' 1 ',
				'saturday'   => '0',
				'sunday'     => 'yes',
				'start_date' => '20261001',
				'end_date'   => '20261231',
			)
		);

		$this->assertSame( 1, $service['monday'] );
		$this->assertSame( 1, $service['tuesday'] );
		$this->assertSame( 0, $service['saturday'] );
		$this->assertSame( 0, $service['sunday'] );
	}

	private function import_row( string $file_name, array $csv_row ): array {
		$file = $this->feed_file( $file_name );

		return array_combine( $file->columns, (array) $file->map( $csv_row ) );
	}

	private function feed_file( string $name ): FeedFile {
		foreach ( FeedFormat::files() as $file ) {
			if ( $file->name === $name ) {
				return $file;
			}
		}

		$this->fail( "FeedFormat does not import {$name}" );
	}
}
