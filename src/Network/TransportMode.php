<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

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

	public function label(): string {
		return match ( $this ) {
			self::Metro      => __( 'Metro', 'gtfs-timetables' ),
			self::Tram       => __( 'Trams', 'gtfs-timetables' ),
			self::Trolleybus => __( 'Trolleybuses', 'gtfs-timetables' ),
			self::Bus        => __( 'Buses', 'gtfs-timetables' ),
			self::Rail       => __( 'Trains', 'gtfs-timetables' ),
			self::Ferry      => __( 'Ferries', 'gtfs-timetables' ),
			self::Cable      => __( 'Cable cars and funiculars', 'gtfs-timetables' ),
			self::Other      => __( 'Other lines', 'gtfs-timetables' ),
		};
	}
}
