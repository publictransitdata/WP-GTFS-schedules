<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Admin;

use PtdGtfsSchedules\FeedInfo;
use PtdGtfsSchedules\Import\Importer;
use PtdGtfsSchedules\Import\ImportState;
use PtdGtfsSchedules\Schedule\Stops;
use PtdGtfsSchedules\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → PTD GTFS Schedules: feed settings, import status and a stop finder
 * that gives editors ready-to-paste shortcodes.
 */
final class SettingsPage {

	private const SLUG         = 'ptd-gtfs-schedules';
	private const SEARCH_PARAM = 'ptd_gtfs_stop_search';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	public static function add_page(): void {
		add_options_page(
			__( 'PTD GTFS Schedules', 'ptd-gtfs-schedules' ),
			__( 'PTD GTFS Schedules', 'ptd-gtfs-schedules' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	public static function register_setting(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array( 'sanitize_callback' => array( Settings::class, 'sanitize' ) )
		);
	}

	public static function enqueue_scripts( string $hook_suffix ): void {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'ptd-gtfs-schedules-admin-import',
			plugins_url( 'assets/js/admin-import.js', PTD_GTFS_SCHEDULES_FILE ),
			array(),
			PTD_GTFS_SCHEDULES_VERSION,
			array( 'in_footer' => true )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PTD GTFS Schedules', 'ptd-gtfs-schedules' ); ?></h1>
			<?php
			self::render_settings_form();
			self::render_import_panel();
			self::render_stop_finder();
			?>
		</div>
		<?php
	}

	private static function render_settings_form(): void {
		$field = fn( string $key ) => Settings::OPTION . "[{$key}]";
		?>
		<form method="post" action="options.php">
			<?php settings_fields( self::SLUG ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gtfs-feed-url"><?php esc_html_e( 'Feed URL', 'ptd-gtfs-schedules' ); ?></label></th>
					<td>
						<input type="url" id="gtfs-feed-url" class="large-text code" name="<?php echo esc_attr( $field( 'feed_url' ) ); ?>" value="<?php echo esc_attr( Settings::get( 'feed_url' ) ); ?>" placeholder="https://example.com/gtfs.zip">
						<p class="description"><?php esc_html_e( 'Link to the GTFS .zip published by the transport operator.', 'ptd-gtfs-schedules' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gtfs-refresh"><?php esc_html_e( 'Update automatically', 'ptd-gtfs-schedules' ); ?></label></th>
					<td>
						<select id="gtfs-refresh" name="<?php echo esc_attr( $field( 'refresh_frequency' ) ); ?>">
							<?php foreach ( Settings::refresh_frequencies() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( Settings::get( 'refresh_frequency' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Runs at night through WP-Cron. On sites with little traffic, set up a real cron job for wp-cron.php or use WP-CLI: wp ptd-gtfs-schedules import', 'ptd-gtfs-schedules' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gtfs-attribution"><?php esc_html_e( 'Data source credit', 'ptd-gtfs-schedules' ); ?></label></th>
					<td>
						<input type="text" id="gtfs-attribution" class="regular-text" name="<?php echo esc_attr( $field( 'attribution' ) ); ?>" value="<?php echo esc_attr( Settings::get( 'attribution' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Shown under timetables. Many open data licences require crediting the publisher, e.g. "Data: City transport authority, CC BY 4.0". Leave empty to show nothing.', 'ptd-gtfs-schedules' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_import_panel(): void {
		$importer = new Importer();
		$state    = ImportState::load();
		$feed     = FeedInfo::all();
		?>
		<h2><?php esc_html_e( 'Import', 'ptd-gtfs-schedules' ); ?></h2>

		<?php if ( FeedInfo::is_loaded() ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: date and time, 2: time zone */
					esc_html__( 'Current data imported %1$s. Feed time zone: %2$s.', 'ptd-gtfs-schedules' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $feed['imported_at'] ) ),
					esc_html( FeedInfo::timezone()->getName() )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $feed['uses_frequencies'] ) ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'This feed describes some trips with frequencies.txt ("every 10 minutes"). Those trips are not shown yet.', 'ptd-gtfs-schedules' ); ?></p></div>
		<?php endif; ?>

		<div
			data-gtfs-import
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( ImportAjax::NONCE_ACTION ) ); ?>"
			data-running="<?php echo $state->is_running() ? '1' : '0'; ?>"
			data-max-upload-bytes="<?php echo esc_attr( (string) FeedUpload::max_bytes() ); ?>"
			data-too-large-message="<?php echo esc_attr( FeedUpload::too_large_message() ); ?>"
			data-uploading-message="<?php esc_attr_e( 'Uploading the file…', 'ptd-gtfs-schedules' ); ?>"
		>
			<p>
				<button type="button" class="button button-primary" data-gtfs-import-url <?php disabled( '' === Settings::get( 'feed_url' ) ); ?>><?php esc_html_e( 'Import from feed URL', 'ptd-gtfs-schedules' ); ?></button>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Or upload a GTFS .zip file:', 'ptd-gtfs-schedules' ); ?>
					<input type="file" accept=".zip,application/zip" name="<?php echo esc_attr( FeedUpload::FIELD ); ?>" data-gtfs-import-file>
				</label>
				<button type="button" class="button" data-gtfs-import-upload disabled><?php esc_html_e( 'Upload and import', 'ptd-gtfs-schedules' ); ?></button>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: maximum upload size, e.g. "64 MB" */
					esc_html__( 'Maximum file size: %s. For larger feeds use the feed URL or WP-CLI: wp ptd-gtfs-schedules import --file=feed.zip', 'ptd-gtfs-schedules' ),
					esc_html( size_format( FeedUpload::max_bytes() ) )
				);
				?>
				<?php if ( self::updates_automatically() ) : ?>
					<br><?php esc_html_e( 'Automatic updates import from the feed URL and will replace data from an uploaded file.', 'ptd-gtfs-schedules' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<label><input type="checkbox" data-gtfs-import-force> <?php esc_html_e( 'Re-import even if the feed has not changed', 'ptd-gtfs-schedules' ); ?></label>
			</p>
			<p data-gtfs-import-status><?php echo esc_html( $importer->summary( $state ) ); ?></p>
		</div>
		<?php
	}

	private static function updates_automatically(): bool {
		return '' !== Settings::get( 'feed_url' ) && 'off' !== Settings::get( 'refresh_frequency' );
	}

	private static function render_stop_finder(): void {
		$search = sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$stops  = ( '' !== $search && FeedInfo::is_loaded() ) ? ( new Stops() )->search( $search ) : array();
		?>
		<h2><?php esc_html_e( 'Find a stop', 'ptd-gtfs-schedules' ); ?></h2>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<input type="search" name="<?php echo esc_attr( self::SEARCH_PARAM ); ?>" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Stop name, code or id', 'ptd-gtfs-schedules' ); ?>">
			<?php submit_button( __( 'Search', 'ptd-gtfs-schedules' ), 'secondary', '', false ); ?>
		</form>

		<?php if ( '' !== $search && ! $stops ) : ?>
			<p><?php esc_html_e( 'No stops found.', 'ptd-gtfs-schedules' ); ?></p>
		<?php endif; ?>

		<?php if ( $stops ) : ?>
			<table class="widefat striped" style="margin-top:1em">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Stop', 'ptd-gtfs-schedules' ); ?></th>
						<th><?php esc_html_e( 'Code', 'ptd-gtfs-schedules' ); ?></th>
						<th><?php esc_html_e( 'Shortcodes', 'ptd-gtfs-schedules' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stops as $stop ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $stop->stop_name ); ?>
								<?php if ( 1 === (int) $stop->location_type ) : ?>
									<em>(<?php esc_html_e( 'station, includes all its platforms', 'ptd-gtfs-schedules' ); ?>)</em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $stop->stop_code ); ?></td>
							<td>
								<code>[ptd_gtfs_departures stop="<?php echo esc_html( $stop->stop_id ); ?>"]</code><br>
								<code>[ptd_gtfs_timetable stop="<?php echo esc_html( $stop->stop_id ); ?>"]</code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
