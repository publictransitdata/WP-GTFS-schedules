<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

defined( 'ABSPATH' ) || exit;

/**
 * Kinds of transport, in the order line lists show them.
 */
enum TransportMode: string {

	case Metro      = 'metro';
	case Tram       = 'tram';
	case Trolleybus = 'trolleybus';
	case Bus        = 'bus';
	case Rail       = 'rail';
	case Ferry      = 'ferry';
	case Cable      = 'cable';
	case Other      = 'other';

	/**
	 * Understands the basic GTFS route types and the extended ones that many European feeds use.
	 *
	 * @link https://gtfs.org/documentation/schedule/reference/#routestxt
	 * @link https://developers.google.com/transit/gtfs/reference/extended-route-types
	 */
	public static function from_route_type( int $type ): self {
		return match ( true ) {
			1 === $type, $type >= 400 && $type < 500             => self::Metro,
			0 === $type, $type >= 900 && $type < 1000            => self::Tram,
			11 === $type, 800 === $type                          => self::Trolleybus,
			3 === $type, $type >= 700 && $type < 800, $type >= 200 && $type < 300 => self::Bus,
			2 === $type, 12 === $type, $type >= 100 && $type < 200 => self::Rail,
			4 === $type, 1000 === $type, 1200 === $type          => self::Ferry,
			in_array( $type, array( 5, 6, 7 ), true ), $type >= 1300 && $type < 1500 => self::Cable,
			default                                              => self::Other,
		};
	}

	/**
	 * What one line of this mode is called, e.g. above its name on the line's page.
	 */
	public function line_label(): string {
		return match ( $this ) {
			self::Metro      => __( 'Metro line', 'ptd-gtfs-schedules' ),
			self::Tram       => __( 'Tram line', 'ptd-gtfs-schedules' ),
			self::Trolleybus => __( 'Trolleybus line', 'ptd-gtfs-schedules' ),
			self::Bus        => __( 'Bus line', 'ptd-gtfs-schedules' ),
			self::Rail       => __( 'Train line', 'ptd-gtfs-schedules' ),
			self::Ferry      => __( 'Ferry line', 'ptd-gtfs-schedules' ),
			self::Cable      => __( 'Cable car line', 'ptd-gtfs-schedules' ),
			self::Other      => __( 'Transit line', 'ptd-gtfs-schedules' ),
		};
	}

	/**
	 * The name of a group of lines of this mode.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Metro      => __( 'Metro', 'ptd-gtfs-schedules' ),
			self::Tram       => __( 'Trams', 'ptd-gtfs-schedules' ),
			self::Trolleybus => __( 'Trolleybuses', 'ptd-gtfs-schedules' ),
			self::Bus        => __( 'Buses', 'ptd-gtfs-schedules' ),
			self::Rail       => __( 'Trains', 'ptd-gtfs-schedules' ),
			self::Ferry      => __( 'Ferries', 'ptd-gtfs-schedules' ),
			self::Cable      => __( 'Cable cars and funiculars', 'ptd-gtfs-schedules' ),
			self::Other      => __( 'Other lines', 'ptd-gtfs-schedules' ),
		};
	}
}
