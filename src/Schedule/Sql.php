<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use PtdGtfsSchedules\Schema;

defined( 'ABSPATH' ) || exit;

final class Sql {

	/**
	 * "%s, %s, %s" for use in an IN (...) clause passed to $wpdb->prepare().
	 */
	public static function placeholders( array $values ): string {
		return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	}

	/**
	 * Departures where riders can board at the given stops on the given services.
	 * Expects the stop ids and then the service ids as prepare() parameters.
	 */
	public static function select_departures( array $stop_ids, array $service_ids ): string {
		$stop_times = Schema::table( 'stop_times' );
		$trips      = Schema::table( 'trips' );
		$routes     = Schema::table( 'routes' );

		return "SELECT st.trip_id, st.stop_id, st.departure_secs,
				COALESCE(NULLIF(st.stop_headsign, ''), t.trip_headsign) AS headsign,
				r.route_id, r.route_short_name, r.route_long_name, r.route_color, r.route_text_color
			FROM {$stop_times} st
			INNER JOIN {$trips} t ON t.trip_id = st.trip_id
			INNER JOIN {$routes} r ON r.route_id = t.route_id
			WHERE st.stop_id IN (" . self::placeholders( $stop_ids ) . ')
				AND t.service_id IN (' . self::placeholders( $service_ids ) . ')
				AND st.pickup_type <> 1';
	}
}
