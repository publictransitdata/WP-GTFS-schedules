<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use DateTimeImmutable;
use PtdGtfsSchedules\Network\Line;
use PtdGtfsSchedules\Network\LineDirection;
use PtdGtfsSchedules\Network\Lines;

defined( 'ABSPATH' ) || exit;

/**
 * The page that shows one line, e.g. /timetable/?line=408. Lines blocks link to it on their own:
 * it is whichever published page holds the Line block (or [ptd_gtfs_line]), found again after every change to posts.
 * With ?trip= it shows one trip of the line instead, from its first stop to its last.
 *
 * The link uses the feed's route_id: line numbers are not unique, and database ids change with every import.
 */
final class LinePage {

	public const QUERY_ARG = 'line';

	/**
	 * The id of the rider's stop in a trip, so the browser scrolls to it.
	 */
	public const TRIP_STOP_ANCHOR = 'gtfs-trip-from';

	private const DIRECTION_ARG = 'direction';
	private const STOP_ARG      = 'stop';
	private const TRIP_ARG      = 'trip';
	private const OPTION        = 'ptd_gtfs_schedules_line_page';

	public static function register(): void {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action( $hook, array( self::class, 'forget' ) );
		}

		add_filter( 'document_title_parts', array( self::class, 'title_with_line' ) );
		add_filter( 'the_title', array( self::class, 'page_heading' ), 10, 2 );
		add_filter( 'wp_robots', array( self::class, 'keep_trips_out_of_search' ) );
	}

	public static function forget(): void {
		delete_option( self::OPTION );
	}

	/**
	 * @return string Empty when the site has no line page yet.
	 */
	public static function url( Line $line ): string {
		$url = self::page_url( array( self::QUERY_ARG => $line->badge->route_id ) );

		/**
		 * Filters the address of a line's page, for sites with their own routing.
		 *
		 * @param string $url  Empty when the site has no line page.
		 * @param Line   $line
		 */
		return (string) apply_filters( 'ptd_gtfs_schedules_line_url', $url, $line );
	}

	/**
	 * @param string            $route_id
	 * @param string            $trip_id
	 * @param DateTimeImmutable $service_date Trips after midnight belong to the day before.
	 * @param string            $stop_id      Where the rider looks at the trip from.
	 * @return string Empty when the site has no line page yet.
	 */
	public static function trip_url( string $route_id, string $trip_id, DateTimeImmutable $service_date, string $stop_id ): string {
		$url = self::page_url(
			array(
				self::QUERY_ARG            => $route_id,
				self::TRIP_ARG             => $trip_id,
				Shortcodes::DATE_QUERY_ARG => $service_date->format( 'Y-m-d' ),
				self::STOP_ARG             => $stop_id,
			)
		);

		$url = '' === $url ? '' : $url . '#' . self::TRIP_STOP_ANCHOR;

		/**
		 * Filters the address of a trip, for sites with their own routing.
		 *
		 * @param string            $url          Empty when the site has no line page.
		 * @param string            $route_id
		 * @param string            $trip_id
		 * @param DateTimeImmutable $service_date
		 * @param string            $stop_id
		 */
		return (string) apply_filters( 'ptd_gtfs_schedules_trip_url', $url, $route_id, $trip_id, $service_date, $stop_id );
	}

	public static function requested_route_id(): string {
		return self::query_arg( self::QUERY_ARG );
	}

	/**
	 * @return int Position in the line's list of directions.
	 */
	public static function requested_direction(): int {
		return absint( self::query_arg( self::DIRECTION_ARG ) );
	}

	/**
	 * The stop from the address when the direction calls there, else the first stop where riders can get on.
	 */
	public static function requested_stop( LineDirection $direction ): string {
		$stop_id = self::requested_stop_id();

		return $direction->has_stop( $stop_id ) ? $stop_id : ( $direction->first_boarding_stop()->stop_id ?? '' );
	}

	public static function requested_stop_id(): string {
		return self::query_arg( self::STOP_ARG );
	}

	public static function requested_trip_id(): string {
		return self::query_arg( self::TRIP_ARG );
	}

	/**
	 * The current page with another direction; the stop is dropped, as it belongs to the old direction.
	 */
	public static function direction_url( int $direction ): string {
		return add_query_arg( self::DIRECTION_ARG, $direction, remove_query_arg( self::STOP_ARG ) );
	}

	public static function stop_url( string $stop_id ): string {
		return add_query_arg( self::STOP_ARG, rawurlencode( $stop_id ) );
	}

	/**
	 * From a trip back to the timetable of one of its stops, on the same day.
	 */
	public static function timetable_url( int $direction, string $stop_id ): string {
		return add_query_arg(
			array(
				self::DIRECTION_ARG => $direction,
				self::STOP_ARG      => rawurlencode( $stop_id ),
			),
			remove_query_arg( self::TRIP_ARG )
		);
	}

	/**
	 * Every trip on every day has an address; search engines need only the timetables.
	 *
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public static function keep_trips_out_of_search( array $robots ): array {
		if ( '' !== self::requested_trip_id() && is_singular() && self::page_id() === get_queried_object_id() ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * "Line N8" in the browser tab and search results instead of the page's own title.
	 *
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public static function title_with_line( array $parts ): array {
		$line = is_singular() ? self::shown_line( get_queried_object_id() ) : null;

		if ( $line ) {
			$parts['title'] = esc_html( self::line_title( $line ) );
		}

		return $parts;
	}

	/**
	 * "Line N8" as the heading of the line page, wherever the theme prints the page title.
	 */
	public static function page_heading( string $title, int $post_id = 0 ): string {
		$line = in_the_loop() || is_singular() ? self::shown_line( $post_id ) : null;

		return $line ? esc_html( self::line_title( $line ) ) : $title;
	}

	private static function shown_line( int $post_id ): ?Line {
		$route_id = self::requested_route_id();

		if ( '' === $route_id || self::page_id() !== $post_id ) {
			return null;
		}

		return ( new Lines() )->find( $route_id );
	}

	private static function line_title( Line $line ): string {
		/* translators: %s: line name, e.g. "N8" */
		return sprintf( __( 'Line %s', 'ptd-gtfs-schedules' ), $line->name() );
	}

	/**
	 * @param array<string, string> $args
	 */
	private static function page_url( array $args ): string {
		$page = self::page_id();

		return $page ? add_query_arg( array_map( 'rawurlencode', $args ), get_permalink( $page ) ) : '';
	}

	private static function query_arg( string $name ): string {
		return sanitize_text_field( wp_unslash( $_GET[ $name ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	}

	private static function page_id(): int {
		$page = get_option( self::OPTION );

		if ( false === $page ) {
			$page = self::find_page();
			update_option( self::OPTION, $page );
		}

		return (int) $page;
	}

	/**
	 * The oldest published page or post with the Line block or the [ptd_gtfs_line] shortcode.
	 */
	private static function find_page(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM %i
				WHERE post_status = 'publish' AND post_type IN ('page', 'post')
					AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
				ORDER BY ID LIMIT 1",
				$wpdb->posts,
				'%' . $wpdb->esc_like( '<!-- wp:ptd-gtfs-schedules/line ' ) . '%',
				'%' . $wpdb->esc_like( '[ptd_gtfs_line]' ) . '%',
				'%' . $wpdb->esc_like( '[ptd_gtfs_line ' ) . '%'
			)
		);
	}
}
