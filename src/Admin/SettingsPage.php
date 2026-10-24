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
 * The plugin's admin page, with a menu item of its own: feed settings and import, a stop finder that
 * gives editors ready-to-paste shortcodes, a guide to getting started, and where to reach us.
 */
final class SettingsPage {

	public const SLUG = 'ptd-gtfs-schedules';

	private const MENU_TITLE    = 'GTFS Schedules';
	private const PAGE_TITLE    = 'PTD GTFS Schedules';
	private const SECTION_PARAM = 'section';
	private const SEARCH_PARAM  = 'ptd_gtfs_stop_search';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PTD_GTFS_SCHEDULES_FILE ), array( self::class, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( self::class, 'add_row_links' ), 10, 2 );
	}

	/** The logo stands for "PTD" next to the menu title. */
	public static function add_page(): void {
		add_menu_page( self::PAGE_TITLE, self::MENU_TITLE, 'manage_options', self::SLUG, array( self::class, 'render' ), Brand::menu_icon() );
	}

	public static function register_setting(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array( 'sanitize_callback' => array( Settings::class, 'sanitize' ) )
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'ptd-gtfs-schedules-admin', plugins_url( 'assets/css/admin.css', PTD_GTFS_SCHEDULES_FILE ), array(), PTD_GTFS_SCHEDULES_VERSION );
		wp_enqueue_script(
			'ptd-gtfs-schedules-admin-import',
			plugins_url( 'assets/js/admin-import.js', PTD_GTFS_SCHEDULES_FILE ),
			array(),
			PTD_GTFS_SCHEDULES_VERSION,
			array( 'in_footer' => true )
		);
	}

	public static function url( string $section = '' ): string {
		$query = array(
			'page'              => self::SLUG,
			self::SECTION_PARAM => $section,
		);

		return add_query_arg( array_filter( $query ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public static function add_settings_link( array $links ): array {
		return array( 'settings' => sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'ptd-gtfs-schedules' ) ) ) + $links;
	}

	/**
	 * The author's name in the same row already links to the website.
	 *
	 * @param string[] $links
	 * @param string   $plugin_file
	 * @return string[]
	 */
	public static function add_row_links( array $links, string $plugin_file ): array {
		if ( plugin_basename( PTD_GTFS_SCHEDULES_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = self::external_link( Brand::ISSUES, __( 'Report a problem or suggest an idea', 'ptd-gtfs-schedules' ) );

		return $links;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$section = self::current_section();
		?>
		<div class="wrap ptd-gtfs-admin">
			<header class="ptd-gtfs-admin__header">
				<h1 class="ptd-gtfs-admin__title">
					<img class="ptd-gtfs-admin__logo" src="<?php echo esc_url( Brand::logo_url() ); ?>" alt="PTD" width="81" height="50">
					<?php echo esc_html( self::MENU_TITLE ); ?>
				</h1>
				<p class="ptd-gtfs-admin__version">
					<?php
					/* translators: %s: version number of the plugin */
					echo esc_html( sprintf( __( 'Version %s', 'ptd-gtfs-schedules' ), PTD_GTFS_SCHEDULES_VERSION ) );
					?>
				</p>
			</header>
			<hr class="wp-header-end">
			<?php settings_errors(); ?>

			<div class="ptd-gtfs-admin__layout">
				<nav class="ptd-gtfs-admin__nav" aria-label="<?php esc_attr_e( 'Sections', 'ptd-gtfs-schedules' ); ?>">
					<?php foreach ( self::sections() as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>" <?php echo $key === $section ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="ptd-gtfs-admin__content">
					<?php
					if ( 'stops' === $section ) {
						self::render_stop_finder();
					} elseif ( 'start' === $section ) {
						self::render_getting_started();
					} else {
						self::render_settings_form();
						self::render_import_panel();
					}
					?>
				</div>

				<?php self::render_sidebar(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @return array<string, string> Key in the address => name in the navigation.
	 */
	private static function sections(): array {
		return array(
			'feed'  => __( 'Feed and import', 'ptd-gtfs-schedules' ),
			'stops' => __( 'Find a stop', 'ptd-gtfs-schedules' ),
			'start' => __( 'Getting started', 'ptd-gtfs-schedules' ),
		);
	}

	private static function current_section(): string {
		$section = sanitize_key( wp_unslash( $_GET[ self::SECTION_PARAM ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.

		return array_key_exists( $section, self::sections() ) ? $section : 'feed';
	}

	private static function external_link( string $url, string $text, string $css_class = '' ): string {
		return sprintf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="noopener">%3$s<span class="screen-reader-text"> %4$s</span></a>',
			esc_attr( $css_class ),
			esc_url( $url ),
			esc_html( $text ),
			esc_html__( '(opens in a new tab)', 'ptd-gtfs-schedules' )
		);
	}

	private static function render_settings_form(): void {
		$field = fn( string $key ) => Settings::OPTION . "[{$key}]";
		?>
		<section class="ptd-gtfs-admin__card">
			<h2><?php esc_html_e( 'Feed', 'ptd-gtfs-schedules' ); ?></h2>
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
		</section>
		<?php
	}

	private static function render_import_panel(): void {
		$importer = new Importer();
		$state    = ImportState::load();
		$feed     = FeedInfo::all();
		?>
		<section class="ptd-gtfs-admin__card">
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
		</section>
		<?php
	}

	private static function updates_automatically(): bool {
		return '' !== Settings::get( 'feed_url' ) && 'off' !== Settings::get( 'refresh_frequency' );
	}

	private static function render_stop_finder(): void {
		$search = sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$stops  = ( '' !== $search && FeedInfo::is_loaded() ) ? ( new Stops() )->search( $search ) : array();
		?>
		<section class="ptd-gtfs-admin__card">
			<h2><?php esc_html_e( 'Find a stop', 'ptd-gtfs-schedules' ); ?></h2>
			<p><?php esc_html_e( 'Each stop has shortcodes you can paste into any page: one for its next departures, one for its timetable.', 'ptd-gtfs-schedules' ); ?></p>
			<form method="get" class="ptd-gtfs-admin__search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="<?php echo esc_attr( self::SECTION_PARAM ); ?>" value="stops">
				<label class="screen-reader-text" for="ptd-gtfs-stop-search"><?php esc_html_e( 'Stop name, code or id', 'ptd-gtfs-schedules' ); ?></label>
				<input type="search" id="ptd-gtfs-stop-search" class="regular-text" name="<?php echo esc_attr( self::SEARCH_PARAM ); ?>" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Stop name, code or id', 'ptd-gtfs-schedules' ); ?>">
				<?php submit_button( __( 'Search', 'ptd-gtfs-schedules' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( '' !== $search && ! $stops ) : ?>
				<p><?php esc_html_e( 'No stops found.', 'ptd-gtfs-schedules' ); ?></p>
			<?php endif; ?>

			<?php if ( $stops ) : ?>
				<table class="widefat striped ptd-gtfs-admin__stops">
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
		</section>
		<?php
	}

	private static function render_getting_started(): void {
		$steps = array(
			array(
				__( 'Import the timetable', 'ptd-gtfs-schedules' ),
				sprintf(
					/* translators: %s: link to the "Feed and import" section */
					__( 'In %s, enter the address of the GTFS feed your transport authority publishes, or upload its .zip file, and import it. The plugin can then keep it up to date every night.', 'ptd-gtfs-schedules' ),
					sprintf( '<a href="%s">%s</a>', esc_url( self::url( 'feed' ) ), esc_html__( 'Feed and import', 'ptd-gtfs-schedules' ) )
				),
			),
			array(
				__( 'List the lines', 'ptd-gtfs-schedules' ),
				__( 'Add the Lines block to a page, for example the home page. It shows every line, grouped by transport mode.', 'ptd-gtfs-schedules' ),
			),
			array(
				__( 'Give the lines a page', 'ptd-gtfs-schedules' ),
				__( 'Create one page with the Line timetable block. Every Lines block on the site links there, and it shows the route and timetables of the chosen line.', 'ptd-gtfs-schedules' ),
			),
		);
		?>
		<section class="ptd-gtfs-admin__card">
			<h2><?php esc_html_e( 'Getting started', 'ptd-gtfs-schedules' ); ?></h2>
			<ol class="ptd-gtfs-admin__steps">
				<?php foreach ( $steps as list( $title, $text ) ) : ?>
					<li>
						<h3><?php echo esc_html( $title ); ?></h3>
						<p><?php echo wp_kses( $text, array( 'a' => array( 'href' => array() ) ) ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
	}

	private static function render_sidebar(): void {
		$allowed = array(
			'a'    => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
				'class'  => array(),
			),
			'span' => array( 'class' => array() ),
		);
		?>
		<aside class="ptd-gtfs-admin__sidebar">
			<section class="ptd-gtfs-admin__card ptd-gtfs-admin__card--accent">
				<h2><?php esc_html_e( 'Problems and ideas', 'ptd-gtfs-schedules' ); ?></h2>
				<p><?php esc_html_e( 'Something does not work, or a feature is missing? Tell us on GitHub: every report helps us make the plugin better.', 'ptd-gtfs-schedules' ); ?></p>
				<p><?php echo wp_kses( self::external_link( Brand::ISSUES, __( 'Report a problem or suggest an idea', 'ptd-gtfs-schedules' ), 'button' ), $allowed ); ?></p>
			</section>

			<section class="ptd-gtfs-admin__card">
				<h2><?php esc_html_e( 'Contact', 'ptd-gtfs-schedules' ); ?></h2>
				<ul class="ptd-gtfs-admin__contacts">
					<li>
						<span class="dashicons dashicons-email" aria-hidden="true"></span>
						<a href="<?php echo esc_url( 'mailto:' . Brand::EMAIL ); ?>"><?php echo esc_html( Brand::EMAIL ); ?></a>
					</li>
					<li>
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
						<?php echo wp_kses( self::external_link( Brand::WEBSITE, 'publictransitdata.net' ), $allowed ); ?>
					</li>
				</ul>
				<p class="description"><?php esc_html_e( 'PTD GTFS Schedules is made by Public Transit Data.', 'ptd-gtfs-schedules' ); ?></p>
			</section>
		</aside>
		<?php
	}
}
