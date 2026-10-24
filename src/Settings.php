<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'ptd_gtfs_schedules_settings';

	private const DEFAULTS = array(
		'feed_url'          => '',
		'refresh_frequency' => 'daily',
		'attribution'       => '',
	);

	public static function get( string $key ): string {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );

		return (string) ( $settings[ $key ] ?? '' );
	}

	public static function refresh_frequencies(): array {
		return array(
			'off'    => __( 'Manual only', 'ptd-gtfs-schedules' ),
			'daily'  => __( 'Daily', 'ptd-gtfs-schedules' ),
			'weekly' => __( 'Weekly', 'ptd-gtfs-schedules' ),
		);
	}

	public static function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$frequency = sanitize_key( $input['refresh_frequency'] ?? '' );

		return array(
			'feed_url'          => esc_url_raw( trim( (string) ( $input['feed_url'] ?? '' ) ), array( 'http', 'https' ) ),
			'refresh_frequency' => array_key_exists( $frequency, self::refresh_frequencies() ) ? $frequency : self::DEFAULTS['refresh_frequency'],
			'attribution'       => sanitize_text_field( $input['attribution'] ?? '' ),
		);
	}
}
