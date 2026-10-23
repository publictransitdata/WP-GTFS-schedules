<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use DateTimeImmutable;
use GtfsTimetables\FeedInfo;
use GtfsTimetables\Schedule\Departures;
use GtfsTimetables\Schedule\ServiceDay;
use GtfsTimetables\Schedule\StopTimetable;
use GtfsTimetables\Schedule\Stops;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the HTML of the departure board and the stop timetable.
 * Shared by the shortcodes and the REST endpoint that refreshes boards.
 */
final class Renderer {

	public const MAX_DEPARTURES = 30;

	public static function departure_board( string $stop_id, int $limit ): string {
		$stop = self::find_stop( $stop_id );

		if ( is_string( $stop ) ) {
			return $stop;
		}

		$now = ServiceDay::now();

		return Templates::render(
			'departures',
			array(
				'stop'        => $stop,
				'now'         => $now,
				'departures'  => ( new Departures() )->next_from( $stop->stop_id, $now, $limit ),
				'refresh_url' => RestController::departures_url( $stop->stop_id, $limit ),
				'attribution' => Templates::data_attribution(),
			)
		);
	}

	public static function stop_timetable( string $stop_id, string $route_id, DateTimeImmutable $date ): string {
		$stop = self::find_stop( $stop_id );

		if ( is_string( $stop ) ) {
			return $stop;
		}

		return Templates::render(
			'timetable',
			array(
				'stop'        => $stop,
				'date'        => $date,
				'sections'    => ( new StopTimetable() )->for_date( $stop->stop_id, $date, $route_id ),
				'attribution' => Templates::data_attribution(),
			)
		);
	}

	/**
	 * @return object|string The stop, or a notice explaining why it cannot be shown.
	 */
	private static function find_stop( string $stop_id ): object|string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'GTFS Timetables: no feed has been imported yet.', 'gtfs-timetables' ) );
		}

		$stop = ( new Stops() )->find( $stop_id );

		if ( null === $stop ) {
			/* translators: %s: stop id */
			return Templates::notice_for_editors( sprintf( __( 'GTFS Timetables: there is no stop with id "%s" in the feed.', 'gtfs-timetables' ), $stop_id ) );
		}

		return $stop;
	}
}
