<?php
/**
 * Uninstall handler for Smooth Booking.
 *
 * @package SmoothBooking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/smooth-booking.php';

use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Plugin;

if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $site_id ) {
        switch_to_blog( (int) $site_id );
        smooth_booking_drop_schema();
        restore_current_blog();
    }
} else {
    smooth_booking_drop_schema();
}

/**
 * Drop Smooth Booking schema for current site.
 */
function smooth_booking_drop_schema(): void {
    $plugin        = Plugin::instance();
    $schema        = $plugin->getContainer()->get( SchemaManager::class );

    $schema->drop_schema();
}
