<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

use PtdGtfsSchedules\Schedule\RouteBadge;

defined( 'ABSPATH' ) || exit;

/**
 * A line as riders know it: a GTFS route with its number, colours and destinations.
 */
final class Line {

	/**
	 * @param RouteBadge    $badge
	 * @param TransportMode $mode
	 * @param string[]      $destinations Where the line goes, e.g. both termini.
	 * @param int|null      $sort_order   The feed's route_sort_order, when it has one.
	 */
	public function __construct(
		public readonly RouteBadge $badge,
		public readonly TransportMode $mode,
		public readonly array $destinations,
		public readonly ?int $sort_order = null,
	) {}

	public function name(): string {
		return $this->badge->name;
	}

	/**
	 * GTFS has no notion of night lines. Most networks name them N1, N8, N89…
	 */
	public function looks_like_night_line(): bool {
		return 1 === preg_match( '/^N\d/i', $this->name() );
	}

	/**
	 * The feed's own order when it has one (route_sort_order), else by number:
	 * 2, 10, 100 rather than 10, 100, 2.
	 */
	public static function in_network_order( self $a, self $b ): int {
		$by_feed_order = ( $a->sort_order ?? PHP_INT_MAX ) <=> ( $b->sort_order ?? PHP_INT_MAX );

		return 0 !== $by_feed_order ? $by_feed_order : strnatcasecmp( $a->name(), $b->name() );
	}
}
