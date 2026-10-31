<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use DateTimeImmutable;
use PtdGtfsSchedules\FeedInfo;
use PtdGtfsSchedules\Network\Line;
use PtdGtfsSchedules\Network\LineDirection;
use PtdGtfsSchedules\Network\LineDirections;
use PtdGtfsSchedules\Network\Lines;
use PtdGtfsSchedules\Schedule\Departures;
use PtdGtfsSchedules\Schedule\ServiceDay;
use PtdGtfsSchedules\Schedule\StopTimetable;
use PtdGtfsSchedules\Schedule\Stops;
use PtdGtfsSchedules\Schedule\Trip;
use PtdGtfsSchedules\Schedule\Trips;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the HTML of the departure board, the stop timetable and the list of lines.
 * Shared by the blocks, the shortcodes and the REST endpoint that refreshes boards.
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

	public static function stop_timetable( string $stop_id, string $route_id, DateTimeImmutable $date, ?int $direction_id = null ): string {
		$stop = self::find_stop( $stop_id );

		if ( is_string( $stop ) ) {
			return $stop;
		}

		return Templates::render(
			'timetable',
			array(
				'stop'        => $stop,
				'date'        => $date,
				'sections'    => ( new StopTimetable() )->for_date( $stop->stop_id, $date, $route_id, $direction_id ),
				'attribution' => Templates::data_attribution(),
			)
		);
	}

	/**
	 * @param LineListSettings $settings
	 * @param bool             $is_block Rendered by the Lines block, whose wrapper carries the block's own classes and styles.
	 */
	public static function line_list( LineListSettings $settings, bool $is_block = false ): string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'PTD GTFS Schedules: no feed has been imported yet.', 'ptd-gtfs-schedules' ) );
		}

		$groups = ( new Lines() )->grouped( $settings->modes, $settings->night_lines_apart );

		if ( ! $groups ) {
			return Templates::notice_for_editors( __( 'PTD GTFS Schedules: the feed has no lines of the chosen transport modes.', 'ptd-gtfs-schedules' ) );
		}

		Assets::enqueue_styles();

		$wrapper = array( 'class' => "gtfs-lines gtfs-lines--{$settings->layout}" );

		if ( $settings->tabs ) {
			$wrapper['data-gtfs-tabs'] = 'true';
			Assets::enqueue_line_tabs();
		}

		return Templates::render(
			'lines',
			array(
				'groups'             => $groups,
				'settings'           => $settings,
				'line_url'           => array( LinePage::class, 'url' ),
				'wrapper_attributes' => self::wrapper_attributes( $wrapper, $is_block ),
				'id_prefix'          => wp_unique_id( 'gtfs-lines-' ),
				'attribution'        => Templates::data_attribution(),
			)
		);
	}

	/**
	 * The line from ?line= in the address: its directions, the stops of the chosen direction and the
	 * timetable of the chosen stop, or with ?trip= one of its trips. Without a line the page lists
	 * every line, each linking back here; the editor preview shows the first line instead.
	 *
	 * @param bool $is_block Rendered by the Line block, whose wrapper carries the block's own classes and styles.
	 */
	public static function line_page( bool $is_block = false ): string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'PTD GTFS Schedules: no feed has been imported yet.', 'ptd-gtfs-schedules' ) );
		}

		$lines    = new Lines();
		$route_id = LinePage::requested_route_id();

		if ( '' === $route_id && ! wp_is_serving_rest_request() ) {
			return self::line_list( new LineListSettings(), $is_block );
		}

		$line = '' === $route_id ? ( $lines->all()[0] ?? null ) : $lines->find( $route_id );

		if ( null === $line ) {
			return '<p class="gtfs-notice">' . esc_html__( 'This line is not in the current timetable. Choose a line:', 'ptd-gtfs-schedules' ) . '</p>'
				. self::line_list( new LineListSettings(), $is_block );
		}

		$directions = ( new LineDirections() )->of( $line );
		$notice     = '';

		if ( '' !== LinePage::requested_trip_id() ) {
			$trips = new Trips();
			$trip  = $trips->find( LinePage::requested_trip_id(), Shortcodes::requested_date() );
			$trip  = $trip && $trip->route->route_id === $line->badge->route_id ? $trip : null;

			if ( $trip && $trips->runs_that_day( $trip ) ) {
				return self::trip_view( $line, $trip, $directions, $is_block );
			}

			$notice = $trip
				? __( 'This trip does not run on this day.', 'ptd-gtfs-schedules' )
				: __( 'This trip is no longer in the timetable.', 'ptd-gtfs-schedules' );
			$notice = '<p class="gtfs-notice">' . esc_html( $notice ) . '</p>';
		}

		$direction = $directions[ LinePage::requested_direction() ] ?? $directions[0] ?? null;
		$stop_id   = $direction ? LinePage::requested_stop( $direction ) : '';
		$date      = Shortcodes::requested_date();

		Assets::enqueue_styles();
		Assets::enqueue_timetable_clock();
		Assets::enqueue_route_map();

		return $notice . Templates::render(
			'line',
			array(
				'line'               => $line,
				'directions'         => $directions,
				'direction'          => $direction,
				'stop'               => $direction?->stop( $stop_id ),
				'date'               => $date,
				'now'                => ServiceDay::now(),
				'timetable'          => $direction && ! $direction->ends_at( $stop_id ) && $direction->can_board_at( $stop_id )
					? ( new StopTimetable() )->for_line( $stop_id, $date, $line->badge->route_id, $direction )
					: null,
				'direction_url'      => array( LinePage::class, 'direction_url' ),
				'stop_url'           => array( LinePage::class, 'stop_url' ),
				'day_url'            => fn( DateTimeImmutable $day ) => add_query_arg( Shortcodes::DATE_QUERY_ARG, $day->format( 'Y-m-d' ) ),
				'wrapper_attributes' => self::wrapper_attributes( array( 'class' => 'gtfs-line' ), $is_block ),
				'attribution'        => Templates::data_attribution(),
			)
		);
	}

	/**
	 * @param Line            $line
	 * @param Trip            $trip
	 * @param LineDirection[] $directions
	 * @param bool            $is_block
	 */
	private static function trip_view( Line $line, Trip $trip, array $directions, bool $is_block ): string {
		$position  = array_key_first( array_filter( $directions, fn( LineDirection $direction ) => $direction->is_taken_by( $trip ) ) );
		$direction = null === $position ? null : $directions[ $position ];
		$from      = $trip->boarding_position( LinePage::requested_stop_id() );
		$from_stop = null === $from ? null : $trip->calls[ $from ];

		$timetable_url = function ( string $stop_id ) use ( $direction, $position ): string {
			$has_timetable = $direction && $direction->has_stop( $stop_id ) && $direction->can_board_at( $stop_id ) && ! $direction->ends_at( $stop_id );

			return $has_timetable ? LinePage::timetable_url( (int) $position, $stop_id ) : '';
		};

		$back_url = $from_stop ? $timetable_url( $from_stop->stop_id ) : '';

		Assets::enqueue_styles();
		Assets::enqueue_timetable_clock();

		return Templates::render(
			'trip',
			array(
				'line'               => $line,
				'trip'               => $trip,
				'note'               => $direction?->note_for( $trip->trip_id )?->text(),
				'from'               => $from,
				'back_stop'          => '' !== $back_url ? $from_stop : null,
				'now'                => ServiceDay::now(),
				'timetable_url'      => $timetable_url,
				'back_url'           => '' !== $back_url ? $back_url : LinePage::url( $line ),
				'wrapper_attributes' => self::wrapper_attributes( array( 'class' => 'gtfs-trip' ), $is_block ),
				'attribution'        => Templates::data_attribution(),
			)
		);
	}

	/**
	 * @param array<string, string> $attributes
	 */
	private static function wrapper_attributes( array $attributes, bool $is_block ): string {
		return $is_block ? get_block_wrapper_attributes( $attributes ) : self::html_attributes( $attributes );
	}

	/**
	 * @param array<string, string> $attributes
	 */
	private static function html_attributes( array $attributes ): string {
		return implode(
			' ',
			array_map(
				fn( string $name, string $value ) => sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) ),
				array_keys( $attributes ),
				$attributes
			)
		);
	}

	/**
	 * @return object|string The stop, or a notice explaining why it cannot be shown.
	 */
	private static function find_stop( string $stop_id ): object|string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'PTD GTFS Schedules: no feed has been imported yet.', 'ptd-gtfs-schedules' ) );
		}

		$stop = ( new Stops() )->find( $stop_id );

		if ( null === $stop ) {
			/* translators: %s: stop id */
			return Templates::notice_for_editors( sprintf( __( 'PTD GTFS Schedules: there is no stop with id "%s" in the feed.', 'ptd-gtfs-schedules' ), $stop_id ) );
		}

		return $stop;
	}
}
