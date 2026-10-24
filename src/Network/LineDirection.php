<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

defined( 'ABSPATH' ) || exit;

/**
 * One way along a line: where it goes and the stops of its usual route, in order.
 */
final class LineDirection {

	/**
	 * @param int|null   $direction_id From trips.txt; null when the feed does not tell directions apart.
	 * @param string     $headsign
	 * @param LineStop[] $stops        A loop may visit the same stop twice.
	 */
	public function __construct(
		public readonly ?int $direction_id,
		public readonly string $headsign,
		public readonly array $stops,
	) {}

	public function has_stop( string $stop_id ): bool {
		foreach ( $this->stops as $stop ) {
			if ( $stop->stop_id === $stop_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Riders cannot board at the end of the route, so it has no departures to show.
	 */
	public function ends_at( string $stop_id ): bool {
		return $this->stops && $this->stops[ array_key_last( $this->stops ) ]->stop_id === $stop_id;
	}
}
