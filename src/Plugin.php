<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables;

use GtfsTimetables\Admin\ImportAjax;
use GtfsTimetables\Admin\SettingsPage;
use GtfsTimetables\Cli\Command;
use GtfsTimetables\Frontend\Assets;
use GtfsTimetables\Frontend\Blocks;
use GtfsTimetables\Frontend\LinePage;
use GtfsTimetables\Frontend\RestController;
use GtfsTimetables\Frontend\Shortcodes;
use GtfsTimetables\Import\ImportScheduler;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		Schema::upgrade_if_needed();
		ImportScheduler::register();

		add_action( 'init', array( self::class, 'load_translations' ) );
		add_action( 'init', array( Assets::class, 'register' ) );
		add_action( 'init', array( Shortcodes::class, 'register' ) );
		add_action( 'init', array( Blocks::class, 'register' ) );
		LinePage::register();
		add_action( 'enqueue_block_editor_assets', array( Blocks::class, 'describe_feed_to_editor' ) );
		add_action( 'rest_api_init', array( RestController::class, 'register_routes' ) );

		if ( is_admin() ) {
			SettingsPage::register();
			ImportAjax::register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'gtfs-timetables', Command::class );
		}
	}

	public static function load_translations(): void {
		load_plugin_textdomain( 'gtfs-timetables', false, dirname( plugin_basename( GTFS_TIMETABLES_FILE ) ) . '/languages' );
	}

	public static function activate(): void {
		Schema::create( Schema::LIVE );
		Schema::remember_version();
		ImportScheduler::reschedule();
	}

	public static function deactivate(): void {
		ImportScheduler::unschedule_all();
	}
}
