<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use PtdGtfsSchedules\Network\Line;
use PtdGtfsSchedules\Network\LineDirection;
use PtdGtfsSchedules\Network\Lines;

defined( 'ABSPATH' ) || exit;

/**
 * The page that shows one line, e.g. /timetable/?line=408. Lines blocks link to it on their own:
 * it is whichever published page holds the Line block (or [ptd_gtfs_line]), found again after every change to posts.
 *
 * The link uses the feed's route_id: line numbers are not unique, and database ids change with every import.
 */
final class LinePage {

	public const QUERY_ARG = 'line';

	private const DIRECTION_ARG = 'direction';
	private const STOP_ARG      = 'stop';
	private const OPTION        = 'ptd_gtfs_schedules_line_page';

	public static function register(): void {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action( $hook, array( self::class, 'forget' ) );
		}

		add_filter( 'document_title_parts', array( self::class, 'title_with_line' ) );
		add_filter( 'the_title', array( self::class, 'page_heading' ), 10, 2 );
	}

	public static function forget(): void {
		delete_option( self::OPTION );
	}

	/**
	 * @return string Empty when the site has no line page yet.
	 */
	public static function url( Line $line ): string {
		$page = self::page_id();
		$url  = $page ? add_query_arg( self::QUERY_ARG, rawurlencode( $line->badge->route_id ), get_permalink( $page ) ) : '';

		/**
		 * Filters the address of a line's page, for sites with their own routing.
		 *
		 * @param string $url  Empty when the site has no line page.
		 * @param Line   $line
		 */
		return (string) apply_filters( 'ptd_gtfs_schedules_line_url', $url, $line );
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
		$stop_id = self::query_arg( self::STOP_ARG );

		return $direction->has_stop( $stop_id ) ? $stop_id : ( $direction->first_boarding_stop()->stop_id ?? '' );
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
	 * "Line N8" in the browser tab and search results instead of the page's own title.
	 *
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public static function title_with_line( array $parts ): array {
		$line = is_singular() ? self::shown_line( get_queried_object_id() ) : null;

		if ( $line ) {
			$parts['title'] = self::line_title( $line );
		}

		return $parts;
	}

	/**
	 * "Line N8" as the heading of the line page, wherever the theme prints the page title.
	 */
	public static function page_heading( string $title, int $post_id = 0 ): string {
		$line = in_the_loop() || is_singular() ? self::shown_line( $post_id ) : null;

		return $line ? self::line_title( $line ) : $title;
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
