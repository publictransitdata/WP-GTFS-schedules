<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Admin;

defined( 'ABSPATH' ) || exit;

final class Brand {

	public const WEBSITE = 'https://publictransitdata.net/';
	public const ISSUES  = 'https://github.com/publictransitdata/WP-GTFS-schedules/issues';
	public const EMAIL   = 'contact@publictransitdata.net';

	private const MENU_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="12 36 126 78" fill="black" fill-rule="evenodd">'
		. '<path d="M34.5 42.95H104.1V54.45H34.5Z"/>'
		. '<path d="M55.55 54.45H67.05V62A18.25 18.25 0 0 1 48.8 80.25V68.75A6.75 6.75 0 0 0 55.55 62Z"/>'
		. '<path d="M25.05 68.75H48.8V80.25H36.55V92.5H25.05Z"/>'
		. '<path d="M68.25 54.45H79.75V107H68.25Z"/>'
		. '<path d="M104.1 42.95A31.9 31.9 0 0 1 104.1 106.75V95.25A20.4 20.4 0 0 0 104.1 54.45Z"/>'
		. '<path d="M13.9 48a11.1 11.1 0 1 0 22.2 0a11.1 11.1 0 1 0-22.2 0ZM16.9 48a8.1 8.1 0 1 0 16.2 0a8.1 8.1 0 1 0-16.2 0Z"/>'
		. '<path d="M19.4 100.7a11.1 11.1 0 1 0 22.2 0a11.1 11.1 0 1 0-22.2 0ZM22.4 100.7a8.1 8.1 0 1 0 16.2 0a8.1 8.1 0 1 0-16.2 0Z"/>'
		. '<path d="M83.9 100.7a11.1 11.1 0 1 0 22.2 0a11.1 11.1 0 1 0-22.2 0ZM86.9 100.7a8.1 8.1 0 1 0 16.2 0a8.1 8.1 0 1 0-16.2 0Z"/>'
		. '</svg>';

	public static function logo_url(): string {
		return plugins_url( 'assets/images/ptd-logo.svg', PTD_GTFS_SCHEDULES_FILE );
	}

	public static function menu_icon(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress repaints only base64 data URIs (svg-painter.js).
		return 'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON );
	}
}
