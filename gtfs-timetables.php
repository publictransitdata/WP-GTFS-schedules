<?php
/**
 * Plugin Name:       GTFS Timetables
 * Description:       Shows public transport schedules from a GTFS feed.
 * Version:           0.3.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            PUBLICTRANSITDATA.NET
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gtfs-timetables
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

define( 'GTFS_TIMETABLES_VERSION', '0.3.1' );
define( 'GTFS_TIMETABLES_FILE', __FILE__ );
define( 'GTFS_TIMETABLES_DIR', __DIR__ );

require __DIR__ . '/src/autoload.php';

register_activation_hook( __FILE__, array( GtfsTimetables\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( GtfsTimetables\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( GtfsTimetables\Plugin::class, 'boot' ) );
