<?php
/**
 * Plugin activation handler.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Infrastructure;

use SmoothBooking\Infrastructure\PostTypeRegistrar;

/**
 * Handles activation tasks.
 */
class Activator {
/**
 * Runs on activation.
 */
public static function activate(): void {
if ( ! get_option( 'smooth_booking_options' ) ) {
add_option(
'smooth_booking_options',
[
'date_format'        => 'Y-m-d H:i',
'default_employee'   => 0,
'calendar_page_size' => 20,
]
);
}

update_option( 'smooth_booking_version', SMOOTH_BOOKING_VERSION );

PostTypeRegistrar::register_post_type();
flush_rewrite_rules();

if ( ! wp_next_scheduled( 'smooth_booking_purge_cache' ) ) {
wp_schedule_event( time(), 'twicedaily', 'smooth_booking_purge_cache' );
}
}
}
