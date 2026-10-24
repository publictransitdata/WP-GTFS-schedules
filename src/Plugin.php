<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules;

use PtdGtfsSchedules\Admin\ImportAjax;
use PtdGtfsSchedules\Admin\SettingsPage;
use PtdGtfsSchedules\Cli\Command;
use PtdGtfsSchedules\Frontend\Assets;
use PtdGtfsSchedules\Frontend\Blocks;
use PtdGtfsSchedules\Frontend\LinePage;
use PtdGtfsSchedules\Frontend\RestController;
use PtdGtfsSchedules\Frontend\Shortcodes;
use PtdGtfsSchedules\Import\ImportScheduler;

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
			\WP_CLI::add_command( 'ptd-gtfs-schedules', Command::class );
		}
	}

	public static function load_translations(): void {
		load_plugin_textdomain( 'ptd-gtfs-schedules', false, dirname( plugin_basename( PTD_GTFS_SCHEDULES_FILE ) ) . '/languages' );
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
