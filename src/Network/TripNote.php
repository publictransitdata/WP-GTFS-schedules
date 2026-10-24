<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

defined( 'ABSPATH' ) || exit;

/**
 * How a trip differs from the usual one of its direction, as timetables explain it in a footnote.
 */
final class TripNote {

	public function __construct(
		public readonly ?string $to,
		public readonly ?string $via,
	) {}

	public function text(): string {
		if ( null !== $this->to && null !== $this->via ) {
			/* translators: 1: where the trip ends, 2: a stop off the usual route */
			return sprintf( __( 'to %1$s, via %2$s', 'ptd-gtfs-schedules' ), $this->to, $this->via );
		}

		return null !== $this->to
			/* translators: %s: where the trip ends */
			? sprintf( __( 'to %s', 'ptd-gtfs-schedules' ), $this->to )
			/* translators: %s: a stop off the usual route */
			: sprintf( __( 'via %s', 'ptd-gtfs-schedules' ), (string) $this->via );
	}
}
