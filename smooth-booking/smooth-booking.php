<?php
/**
 * Plugin Name: Smooth Booking
 * Plugin URI:  https://example.com/plugins/smooth-booking
 * Description: Bootstraps the Smooth Booking environment and ensures database schema integrity for booking operations.
 * Version:     0.18.19
 * Author:      Smooth Booking Contributors
 * Author URI:  https://example.com
 * Text Domain: smooth-booking
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMOOTH_BOOKING_VERSION', '0.18.19' );
define( 'SMOOTH_BOOKING_PLUGIN_FILE', __FILE__ );
define( 'SMOOTH_BOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMOOTH_BOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

autoload_smooth_booking();

register_activation_hook( __FILE__, [ '\\SmoothBooking\\Infrastructure\\Setup\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\SmoothBooking\\Infrastructure\\Setup\\Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', 'smooth_booking_bootstrap_plugin', 5 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    add_action( 'plugins_loaded', 'smooth_booking_register_cli_commands', 20 );
}

/**
 * Load the Composer autoloader if available.
 *
 * @return void
 */
function autoload_smooth_booking(): void {
    $autoloader = SMOOTH_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';

    if ( file_exists( $autoloader ) ) {
        require_once $autoloader;
    } else {
        spl_autoload_register( static function ( string $class ): void {
            if ( strncmp( 'SmoothBooking\\', $class, 14 ) !== 0 ) {
                return;
            }

            $relative = substr( $class, 14 );
            $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $file     = SMOOTH_BOOKING_PLUGIN_DIR . 'src/' . $relative . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }
}

/**
 * Bootstrap the plugin services.
 *
 * @return void
 */
function smooth_booking_bootstrap_plugin(): void {
    \SmoothBooking\Plugin::instance()->run();
}

/**
 * Register WP-CLI commands.
 *
 * @return void
 */
function smooth_booking_register_cli_commands(): void {
    \SmoothBooking\Plugin::instance()->register_cli();
}
