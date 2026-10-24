<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules;

use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * What we know about the feed that is currently live on the site.
 */
final class FeedInfo {

	private const OPTION = 'ptd_gtfs_schedules_feed';

	public static function save( array $info ): void {
		update_option( self::OPTION, $info, false );
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	public static function all(): array {
		return (array) get_option( self::OPTION, array() );
	}

	public static function is_loaded(): bool {
		return '' !== self::version();
	}

	public static function version(): string {
		return (string) ( self::all()['version'] ?? '' );
	}

	public static function timezone(): DateTimeZone {
		try {
			return new DateTimeZone( (string) ( self::all()['timezone'] ?? '' ) );
		} catch ( \Exception $unknown_timezone ) {
			return wp_timezone();
		}
	}
}
