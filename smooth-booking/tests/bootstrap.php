<?php
/**
 * PHPUnit bootstrap file for Smooth Booking.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( '\\WP_Mock' ) ) {
    require_once __DIR__ . '/stubs/wp-mock.php';
}
