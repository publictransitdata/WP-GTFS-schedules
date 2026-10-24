<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use GtfsTimetables\Network\Line;
use GtfsTimetables\Network\Lines;

defined( 'ABSPATH' ) || exit;

/**
 * The page that shows one line, e.g. /timetable/?line=408. Lines blocks link to it on their own:
 * it is whichever published page holds the Line block (or [gtfs_line]), found again after every change to posts.
 *
 * The link uses the feed's route_id: line numbers are not unique, and database ids change with every import.
 */
final class LinePage {

	public const QUERY_ARG = 'line';

	private const OPTION = 'gtfs_timetables_line_page';

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
		return (string) apply_filters( 'gtfs_timetables_line_url', $url, $line );
	}

	public static function requested_route_id(): string {
		return sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
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
		return sprintf( __( 'Line %s', 'gtfs-timetables' ), $line->name() );
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
	 * The oldest published page or post with the Line block or the [gtfs_line] shortcode.
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
				'%' . $wpdb->esc_like( '<!-- wp:gtfs-timetables/line ' ) . '%',
				'%' . $wpdb->esc_like( '[gtfs_line]' ) . '%',
				'%' . $wpdb->esc_like( '[gtfs_line ' ) . '%'
			)
		);
	}
}
