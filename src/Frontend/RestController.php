<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/ptd-gtfs-schedules/v1/departures?stop=123&limit=8
 *
 * Boards on cached pages fetch fresh departures from here every minute.
 */
final class RestController {

	private const NAMESPACE     = 'ptd-gtfs-schedules/v1';
	private const CACHE_SECONDS = 30;

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/departures',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'departures' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'stop'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => 8,
						'minimum' => 1,
						'maximum' => Renderer::MAX_DEPARTURES,
					),
				),
			)
		);
	}

	public static function departures_url( string $stop_id, int $limit ): string {
		return add_query_arg(
			array(
				'stop'  => rawurlencode( $stop_id ),
				'limit' => $limit,
			),
			rest_url( self::NAMESPACE . '/departures' )
		);
	}

	public static function departures( WP_REST_Request $request ): WP_REST_Response {
		$html     = Renderer::departure_board( (string) $request['stop'], (int) $request['limit'] );
		$response = new WP_REST_Response( array( 'html' => $html ) );
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_SECONDS );

		return $response;
	}
}
