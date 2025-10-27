<?php
/**
 * Shared helpers for Smooth Booking admin styles.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use function array_merge;
use function array_unique;
use function plugins_url;
use function wp_enqueue_style;

/**
 * Provides helper methods for enqueuing shared admin styles.
 */
trait AdminStylesTrait {
    /**
     * Enqueue shared Smooth Booking admin styles.
     *
     * @param string[] $dependencies Additional stylesheet handles to depend on.
     */
    private function enqueue_admin_styles( array $dependencies = [] ): void {
        wp_enqueue_style(
            'smooth-booking-admin-variables',
            plugins_url( 'assets/css/design/smooth-booking-variables.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style(
            'smooth-booking-admin-components',
            plugins_url( 'assets/css/design/smooth-booking-admin-components.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-variables' ],
            SMOOTH_BOOKING_VERSION
        );

        $dependencies = array_merge( $dependencies, [ 'smooth-booking-admin-components' ] );

        wp_enqueue_style(
            'smooth-booking-admin',
            plugins_url( 'assets/css/design/smooth-booking-admin.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            array_unique( $dependencies ),
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style(
            'smooth-booking-admin-shared',
            plugins_url( 'assets/css/admin-shared.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin' ],
            SMOOTH_BOOKING_VERSION
        );
    }
}
