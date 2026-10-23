<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'GtfsTimetables\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_path = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$path          = __DIR__ . '/' . $relative_path . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);
