<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;
use PtdGtfsSchedules\Schema;

defined( 'ABSPATH' ) || exit;

final class Trips {

	public function __construct(
		private readonly ServiceCalendar $calendar = new ServiceCalendar(),
	) {}

	/**
	 * @return Trip|null Null when the feed has no such trip, e.g. after an import gave trips new ids.
	 */
	public function find( string $trip_id, DateTimeImmutable $service_date ): ?Trip {
		global $wpdb;

		$trip = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT t.trip_id, t.trip_headsign AS headsign, t.direction_id, t.service_id,
					r.route_id, r.route_short_name, r.route_long_name, r.route_color, r.route_text_color
				FROM %i t INNER JOIN %i r ON r.route_id = t.route_id
				WHERE t.trip_id = %s',
				Schema::table( 'trips' ),
				Schema::table( 'routes' ),
				$trip_id
			)
		);

		if ( null === $trip ) {
			return null;
		}

		$stop_times = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT st.stop_id, s.stop_name, st.departure_secs, st.arrival_secs, st.pickup_type, st.drop_off_type
				FROM %i st LEFT JOIN %i s ON s.stop_id = st.stop_id
				WHERE st.trip_id = %s
				ORDER BY st.stop_sequence',
				Schema::table( 'stop_times' ),
				Schema::table( 'stops' ),
				$trip_id
			)
		);

		return Trip::from_rows( $trip, $stop_times, $service_date );
	}

	public function runs_that_day( Trip $trip ): bool {
		return in_array( $trip->service_id, $this->calendar->services_on( $trip->service_date ), true );
	}
}
