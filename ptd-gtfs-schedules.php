<?php
/**
 * Plugin Name:       PTD GTFS Schedules
 * Plugin URI:        https://github.com/publictransitdata/WP-GTFS-schedules
 * Description:       Shows public transport schedules from a GTFS feed.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            PUBLICTRANSITDATA.NET
 * Author URI:        https://publictransitdata.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ptd-gtfs-schedules
 * Domain Path:       /languages
 *
 * Copyright (C) 2026 PUBLICTRANSITDATA.NET
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'PTD_GTFS_SCHEDULES_VERSION', '0.2.0' );
define( 'PTD_GTFS_SCHEDULES_FILE', __FILE__ );
define( 'PTD_GTFS_SCHEDULES_DIR', __DIR__ );

require __DIR__ . '/src/autoload.php';

register_activation_hook( __FILE__, array( PtdGtfsSchedules\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( PtdGtfsSchedules\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( PtdGtfsSchedules\Plugin::class, 'boot' ) );
