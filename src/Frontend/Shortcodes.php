<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use DateTimeImmutable;
use GtfsTimetables\Schedule\ServiceDay;

defined( 'ABSPATH' ) || exit;

/**
 * [gtfs_departures stop="123" limit="8"]
 * [gtfs_timetable stop="123" route="7"]
 * [gtfs_lines modes="tram,bus" layout="destinations" tabs="yes" night="mixed" heading="3"]
 */
final class Shortcodes {

	public const DATE_QUERY_ARG = 'gtfs_date';

	public static function register(): void {
		add_shortcode( 'gtfs_departures', array( self::class, 'departures' ) );
		add_shortcode( 'gtfs_timetable', array( self::class, 'timetable' ) );
		add_shortcode( 'gtfs_lines', array( self::class, 'lines' ) );
	}

	public static function lines( array|string $attributes ): string {
		return Renderer::line_list( LineListSettings::from_shortcode( (array) $attributes ) );
	}

	public static function departures( array|string $attributes ): string {
		$attributes = shortcode_atts(
			array(
				'stop'  => '',
				'limit' => 8,
			),
			$attributes,
			'gtfs_departures'
		);

		Assets::enqueue_styles();
		Assets::enqueue_live_refresh();

		$limit = min( Renderer::MAX_DEPARTURES, max( 1, (int) $attributes['limit'] ) );

		return Renderer::departure_board( sanitize_text_field( $attributes['stop'] ), $limit );
	}

	public static function timetable( array|string $attributes ): string {
		$attributes = shortcode_atts(
			array(
				'stop'  => '',
				'route' => '',
			),
			$attributes,
			'gtfs_timetable'
		);

		Assets::enqueue_styles();

		return Renderer::stop_timetable(
			sanitize_text_field( $attributes['stop'] ),
			sanitize_text_field( $attributes['route'] ),
			self::requested_date()
		);
	}

	private static function requested_date(): DateTimeImmutable {
		// Read-only navigation between days, so there is no nonce to check.
		$requested = sanitize_text_field( wp_unslash( $_GET[ self::DATE_QUERY_ARG ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return ServiceDay::from_ymd( $requested ) ?? ServiceDay::today();
	}
}
