<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Stops WP-Cron, the admin page and WP-CLI from running import steps at the same time.
 * add_option() is atomic because option names are unique in the database.
 */
final class ImportLock {

	private const OPTION = 'gtfs_timetables_import_lock';

	public static function acquire( int $seconds ): bool {
		if ( self::is_held() ) {
			return false;
		}

		delete_option( self::OPTION );

		return add_option( self::OPTION, time() + $seconds, '', false );
	}

	public static function is_held(): bool {
		wp_cache_delete( self::OPTION, 'options' );

		return (int) get_option( self::OPTION, 0 ) > time();
	}

	public static function release(): void {
		delete_option( self::OPTION );
	}
}
