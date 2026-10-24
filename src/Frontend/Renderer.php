<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use DateTimeImmutable;
use GtfsTimetables\FeedInfo;
use GtfsTimetables\Network\Lines;
use GtfsTimetables\Schedule\Departures;
use GtfsTimetables\Schedule\ServiceDay;
use GtfsTimetables\Schedule\StopTimetable;
use GtfsTimetables\Schedule\Stops;

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
	 * @param LineListSettings $settings
	 * @param bool             $is_block Rendered by the Lines block, whose wrapper carries the block's own classes and styles.
	 */
	public static function line_list( LineListSettings $settings, bool $is_block = false ): string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'GTFS Timetables: no feed has been imported yet.', 'gtfs-timetables' ) );
		}

		$groups = ( new Lines() )->grouped( $settings->modes, $settings->night_lines_apart );

		if ( ! $groups ) {
			return Templates::notice_for_editors( __( 'GTFS Timetables: the feed has no lines of the chosen transport modes.', 'gtfs-timetables' ) );
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
	 * The line from ?line= in the address. Without it the page lists every line, each linking back here;
	 * the editor preview shows the first line instead.
	 *
	 * @param bool $is_block Rendered by the Line block, whose wrapper carries the block's own classes and styles.
	 */
	public static function line_page( bool $is_block = false ): string {
		if ( ! FeedInfo::is_loaded() ) {
			return Templates::notice_for_editors( __( 'GTFS Timetables: no feed has been imported yet.', 'gtfs-timetables' ) );
		}

		$lines    = new Lines();
		$route_id = LinePage::requested_route_id();

		if ( '' === $route_id && ! wp_is_serving_rest_request() ) {
			return self::line_list( new LineListSettings(), $is_block );
		}

		$line = '' === $route_id ? ( $lines->all()[0] ?? null ) : $lines->find( $route_id );

		if ( null === $line ) {
			return '<p class="gtfs-notice">' . esc_html__( 'This line is not in the current timetable. Choose a line:', 'gtfs-timetables' ) . '</p>'
				. self::line_list( new LineListSettings(), $is_block );
		}

		Assets::enqueue_styles();

		return Templates::render(
			'line',
			array(
				'line'               => $line,
				'wrapper_attributes' => self::wrapper_attributes( array( 'class' => 'gtfs-line' ), $is_block ),
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
