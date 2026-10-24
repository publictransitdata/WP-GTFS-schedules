<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules;

defined( 'ABSPATH' ) || exit;

/**
 * Values computed from the live feed, kept for a day. Keys include the feed version,
 * so a new import never serves values from the previous feed.
 */
final class FeedCache {

	private const PREFIX = 'ptd_gtfs_cache_';

	/**
	 * @template T
	 * @param string        $name
	 * @param callable(): T $compute
	 * @return T
	 */
	public static function remember( string $name, callable $compute ): mixed {
		$key    = self::PREFIX . $name . '_' . substr( FeedInfo::version(), 0, 12 );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $compute();
		set_transient( $key, $value, DAY_IN_SECONDS );

		return $value;
	}

	/**
	 * Keys contain the feed version and often a date, so they cannot be deleted one by one by name.
	 * Sites with a persistent object cache keep transients there; those expire within a day.
	 */
	public static function forget_all(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%'
			)
		);
	}
}
