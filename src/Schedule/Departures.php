<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class Departures {

	public function __construct(
		private readonly ServiceCalendar $calendar = new ServiceCalendar(),
		private readonly Stops $stops = new Stops(),
	) {}

	/**
	 * @return Departure[] The next departures from a stop, soonest first.
	 */
	public function next_from( string $stop_id, DateTimeImmutable $now, int $limit ): array {
		$stop_ids   = $this->stops->with_platforms( $stop_id );
		$departures = array();

		foreach ( ServiceDay::dates_around( $now ) as $date ) {
			$day_start         = ServiceDay::start( $date );
			$seconds_into_day  = $now->getTimestamp() - $day_start->getTimestamp();
			$services_that_day = $this->calendar->services_on( $date );

			foreach ( $this->find( $stop_ids, $services_that_day, $seconds_into_day, $limit ) as $row ) {
				$departures[] = Departure::from_row( $row, $day_start );
			}
		}

		usort( $departures, fn( Departure $a, Departure $b ) => $a->time <=> $b->time );

		return array_slice( $departures, 0, $limit );
	}

	private function find( array $stop_ids, array $service_ids, int $from_seconds, int $limit ): array {
		global $wpdb;

		if ( ! $service_ids ) {
			return array();
		}

		$sql = Sql::select_departures( $stop_ids, $service_ids ) . '
			AND st.departure_secs >= %d
			ORDER BY st.departure_secs
			LIMIT %d';

		$parameters = array_merge( $stop_ids, $service_ids, array( max( 0, $from_seconds ), $limit ) );

		return $wpdb->get_results( $wpdb->prepare( $sql, $parameters ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
