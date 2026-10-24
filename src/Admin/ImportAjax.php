<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Admin;

use PtdGtfsSchedules\Import\ImportError;
use PtdGtfsSchedules\Import\Importer;
use PtdGtfsSchedules\Import\ImportState;

defined( 'ABSPATH' ) || exit;

/**
 * While the settings page is open it drives the import itself, one step per request,
 * so the import does not depend on WP-Cron and the admin sees live progress.
 */
final class ImportAjax {

	public const NONCE_ACTION = 'ptd_gtfs_schedules_import';

	public static function register(): void {
		add_action( 'wp_ajax_ptd_gtfs_schedules_import_start', array( self::class, 'start' ) );
		add_action( 'wp_ajax_ptd_gtfs_schedules_import_step', array( self::class, 'step' ) );
		add_action( 'wp_ajax_ptd_gtfs_schedules_import_upload', array( self::class, 'upload' ) );
	}

	public static function start(): void {
		self::authorize();

		$force = ! empty( $_POST['force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in authorize().

		$importer = new Importer();
		$importer->start( $force );

		self::respond( $importer, $importer->run_step( Importer::seconds_per_step() ) );
	}

	/**
	 * The nonce comes in the query string: when a file exceeds post_max_size,
	 * PHP drops the whole request body and the nonce would go with it.
	 */
	public static function upload(): void {
		self::authorize();

		$force    = ! empty( $_POST['force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in authorize().
		$importer = new Importer();

		try {
			$importer->start_from_archive( FeedUpload::take_from_request(), $force );
		} catch ( ImportError $error ) {
			wp_send_json_error( array( 'message' => $error->plain_text() ), 400 );
		}

		self::respond( $importer, $importer->run_step( Importer::seconds_per_step() ) );
	}

	public static function step(): void {
		self::authorize();

		$importer = new Importer();

		self::respond( $importer, $importer->run_step( Importer::seconds_per_step() ) );
	}

	private static function authorize(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to import feeds.', 'ptd-gtfs-schedules' ) ), 403 );
		}
	}

	private static function respond( Importer $importer, ImportState $state ): void {
		wp_send_json_success(
			array(
				'running' => $state->is_running(),
				'failed'  => ImportState::FAILED === $state->stage,
				'summary' => $importer->summary( $state ),
			)
		);
	}
}
