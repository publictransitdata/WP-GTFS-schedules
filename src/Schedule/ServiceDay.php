<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;
use GtfsTimetables\FeedInfo;

defined( 'ABSPATH' ) || exit;

final class ServiceDay {

	public static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', FeedInfo::timezone() );
	}

	public static function today(): DateTimeImmutable {
		return new DateTimeImmutable( 'today', FeedInfo::timezone() );
	}

	public static function from_ymd( string $date ): ?DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, FeedInfo::timezone() );

		return $parsed && $parsed->format( 'Y-m-d' ) === $date ? $parsed : null;
	}

	/**
	 * The moment GTFS time 00:00:00 refers to. The reference defines it as
	 * "noon minus 12 hours", which differs from midnight on days when clocks change.
	 */
	public static function start( DateTimeImmutable $date ): DateTimeImmutable {
		$noon = $date->setTime( 12, 0 );

		return $noon->setTimestamp( $noon->getTimestamp() - 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Service days whose trips can still be running at the given moment:
	 * yesterday's late-night trips, today's and tomorrow's early ones.
	 *
	 * @return DateTimeImmutable[]
	 */
	public static function dates_around( DateTimeImmutable $moment ): array {
		$today = $moment->setTime( 0, 0 );

		return array( $today->modify( '-1 day' ), $today, $today->modify( '+1 day' ) );
	}
}
