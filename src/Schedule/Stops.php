<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use GtfsTimetables\Schema;

defined( 'ABSPATH' ) || exit;

final class Stops {

	public function find( string $stop_id ): ?object {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE stop_id = %s', Schema::table( 'stops' ), $stop_id ) );
	}

	/**
	 * A station (location_type 1) has no departures of its own; they belong to its platforms.
	 *
	 * @return string[] The stop itself and any platforms that belong to it.
	 */
	public function with_platforms( string $stop_id ): array {
		global $wpdb;

		$platforms = $wpdb->get_col( $wpdb->prepare( 'SELECT stop_id FROM %i WHERE parent_station = %s', Schema::table( 'stops' ), $stop_id ) );

		return array_merge( array( $stop_id ), $platforms );
	}

	public function search( string $term, int $limit = 50 ): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT stop_id, stop_code, stop_name, location_type, parent_station FROM %i
				WHERE stop_name LIKE %s OR stop_code = %s OR stop_id = %s
				ORDER BY stop_name, stop_code
				LIMIT %d',
				Schema::table( 'stops' ),
				$like,
				$term,
				$term,
				$limit
			)
		);
	}
}
