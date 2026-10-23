<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use GtfsTimetables\Settings;

defined( 'ABSPATH' ) || exit;

final class Templates {

	/**
	 * Themes can override a template by copying it to "gtfs-timetables/{name}.php" in the theme.
	 */
	public static function render( string $name, array $args ): string {
		$path = locate_template( "gtfs-timetables/{$name}.php" );

		if ( '' === $path ) {
			$path = GTFS_TIMETABLES_DIR . "/templates/{$name}.php";
		}

		ob_start();
		load_template( $path, false, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Configuration problems are only worth showing to people who can fix them.
	 */
	public static function notice_for_editors( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return '<p class="gtfs-notice">' . esc_html( $message ) . '</p>';
	}

	public static function data_attribution(): string {
		$attribution = Settings::get( 'attribution' );

		return '' === $attribution ? '' : '<p class="gtfs-attribution">' . esc_html( $attribution ) . '</p>';
	}
}
