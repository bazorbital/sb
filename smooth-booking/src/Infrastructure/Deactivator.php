<?php
/**
 * Deactivation handler.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Infrastructure;

/**
 * Handles plugin deactivation.
 */
class Deactivator {
/**
 * Runs on deactivation.
 */
public static function deactivate(): void {
wp_clear_scheduled_hook( 'smooth_booking_purge_cache' );
flush_rewrite_rules();
}
}
