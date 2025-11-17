<?php
/**
 * Plugin Name:       Smooth Booking
 * Plugin URI:        https://example.com/smooth-booking
 * Description:       Booking management plugin providing staff calendar management based on the vkurko/calendar integration.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Smooth Booking Team
 * Author URI:        https://example.com
 * Text Domain:       smooth-booking
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

define( 'SMOOTH_BOOKING_VERSION', '1.1.0' );
define( 'SMOOTH_BOOKING_PLUGIN_FILE', __FILE__ );
define( 'SMOOTH_BOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMOOTH_BOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( SMOOTH_BOOKING_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
require_once SMOOTH_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
} else {
spl_autoload_register(
static function ( $class ) {
if ( 0 !== strpos( $class, 'SmoothBooking\\' ) ) {
return;
}

$path = SMOOTH_BOOKING_PLUGIN_DIR . 'src/' . str_replace( [ 'SmoothBooking\\', '\\' ], [ '', '/' ], $class ) . '.php';

if ( file_exists( $path ) ) {
require_once $path;
}
}
);
}

use SmoothBooking\Plugin;
use SmoothBooking\Infrastructure\Activator;
use SmoothBooking\Infrastructure\Deactivator;

register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivator::class, 'deactivate' ] );

add_action(
'plugins_loaded',
static function () {
load_plugin_textdomain( 'smooth-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

$plugin = new Plugin( __FILE__ );
$plugin->run();
}
);
