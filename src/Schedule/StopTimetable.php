<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;
use GtfsTimetables\Network\LineDirection;

defined( 'ABSPATH' ) || exit;

final class StopTimetable {

	public function __construct(
		private readonly ServiceCalendar $calendar = new ServiceCalendar(),
		private readonly Stops $stops = new Stops(),
	) {}

	/**
	 * @param string            $stop_id
	 * @param DateTimeImmutable $date
	 * @param string            $route_id     Only this route; every route when empty.
	 * @param int|null          $direction_id Only trips in this direction of the route; both when null.
	 * @return TimetableSection[] One section per route and headsign, sorted by route name.
	 */
	public function for_date( string $stop_id, DateTimeImmutable $date, string $route_id = '', ?int $direction_id = null ): array {
		$sections = array();

		foreach ( $this->find( $this->stops->with_platforms( $stop_id ), $this->calendar->services_on( $date ), $route_id, $direction_id ) as $row ) {
			$key = $row->route_id . "\0" . $row->headsign;

			$sections[ $key ] ??= new TimetableSection( RouteBadge::from_row( $row ), (string) $row->headsign );
			$sections[ $key ]->add_departure( (int) $row->departure_secs );
		}

		usort( $sections, fn( TimetableSection $a, TimetableSection $b ) => strnatcasecmp( $a->sort_key(), $b->sort_key() ) );

		return $sections;
	}

	/**
	 * One line's departures from a stop in one direction, as a single timetable in which
	 * the trips that differ from the usual one are marked with a letter.
	 */
	public function for_line( string $stop_id, DateTimeImmutable $date, string $route_id, LineDirection $direction ): MarkedTimetable {
		$timetable = new MarkedTimetable( ServiceDay::start( $date ) );
		$rows      = $this->find( $this->stops->with_platforms( $stop_id ), $this->calendar->services_on( $date ), $route_id, $direction->direction_id );

		foreach ( $rows as $row ) {
			$timetable->add( (int) $row->departure_secs, $direction->note_for( (string) $row->trip_id )?->text() ?? '' );
		}

		return $timetable;
	}

	private function find( array $stop_ids, array $service_ids, string $route_id, ?int $direction_id ): array {
		global $wpdb;

		if ( ! $service_ids ) {
			return array();
		}

		$sql        = Sql::select_departures( $stop_ids, $service_ids );
		$parameters = array_merge( $stop_ids, $service_ids );

		if ( '' !== $route_id ) {
			$sql         .= ' AND t.route_id = %s';
			$parameters[] = $route_id;
		}

		if ( null !== $direction_id ) {
			$sql         .= ' AND t.direction_id = %d';
			$parameters[] = $direction_id;
		}

		$sql .= ' ORDER BY st.departure_secs';

		return $wpdb->get_results( $wpdb->prepare( $sql, $parameters ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
