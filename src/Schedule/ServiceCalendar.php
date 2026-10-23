<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;
use GtfsTimetables\FeedInfo;
use GtfsTimetables\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Answers "which services run on this date": the weekly pattern from calendar.txt,
 * plus services added and minus services removed in calendar_dates.txt.
 */
final class ServiceCalendar {

	private const SERVICE_ADDED   = 1;
	private const SERVICE_REMOVED = 2;

	/**
	 * @return string[] service_id values.
	 */
	public function services_on( DateTimeImmutable $date ): array {
		$cache_key = 'gtfs_tt_services_' . substr( FeedInfo::version(), 0, 12 ) . '_' . $date->format( 'Ymd' );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$services = $this->find_services( (int) $date->format( 'Ymd' ), strtolower( $date->format( 'l' ) ) );
		set_transient( $cache_key, $services, DAY_IN_SECONDS );

		return $services;
	}

	private function find_services( int $day, string $weekday ): array {
		$weekly  = $this->weekly_services( $day, $weekday );
		$added   = $this->exceptions( $day, self::SERVICE_ADDED );
		$removed = $this->exceptions( $day, self::SERVICE_REMOVED );

		return array_values( array_diff( array_unique( array_merge( $weekly, $added ) ), $removed ) );
	}

	private function weekly_services( int $day, string $weekday ): array {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT service_id FROM %i WHERE %i = 1 AND start_date <= %d AND end_date >= %d',
				Schema::table( 'calendar' ),
				$weekday,
				$day,
				$day
			)
		);
	}

	private function exceptions( int $day, int $exception_type ): array {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT service_id FROM %i WHERE service_date = %d AND exception_type = %d',
				Schema::table( 'calendar_dates' ),
				$day,
				$exception_type
			)
		);
	}
}
