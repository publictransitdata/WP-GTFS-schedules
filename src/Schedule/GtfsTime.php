<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * GTFS times count from the start of the service day and may pass 24:00,
 * e.g. "25:10:00" is 01:10 on the next calendar day.
 */
final class GtfsTime {

	public static function to_seconds( string $time ): ?int {
		if ( ! preg_match( '/^(\d{1,3}):([0-5]\d):([0-5]\d)$/', trim( $time ), $parts ) ) {
			return null;
		}

		return (int) $parts[1] * HOUR_IN_SECONDS + (int) $parts[2] * MINUTE_IN_SECONDS + (int) $parts[3];
	}
}
