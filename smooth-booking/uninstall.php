<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SmoothBooking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

require_once __DIR__ . '/src/Infrastructure/CacheRepository.php';

SmoothBooking\Infrastructure\CacheRepository::flush();

delete_option( 'smooth_booking_options' );
delete_option( 'smooth_booking_version' );
delete_option( 'smooth_booking_cached_calendars' );

$bookings = get_posts(
[
'post_type'      => 'sb_booking',
'post_status'    => 'any',
'posts_per_page' => -1,
'fields'         => 'ids',
]
);

foreach ( $bookings as $booking_id ) {
wp_delete_post( $booking_id, true );
}
