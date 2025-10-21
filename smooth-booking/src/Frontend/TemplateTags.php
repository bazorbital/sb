<?php
/**
 * Template tags for Smooth Booking frontend output.
 *
 * @package SmoothBooking\Frontend
 */

namespace SmoothBooking\Frontend {

use SmoothBooking\Domain\SchemaStatusService;
use SmoothBooking\Plugin;

    /**
     * Provide template tag rendering.
     */
    class TemplateTags {
        /**
         * Output schema status list.
         */
        public static function display_schema_status(): void {
            $service = Plugin::instance()->getContainer()->get( SchemaStatusService::class );
            $status  = $service->get_status();

            if ( is_wp_error( $status ) ) {
                echo '<div class="smooth-booking-error">' . esc_html( $status->get_error_message() ) . '</div>';
                return;
            }

            echo '<div class="smooth-booking-schema-status"><ul>';
            foreach ( $status as $table_name => $exists ) {
                printf(
                    '<li><code>%1$s</code>: <span class="%3$s">%2$s</span></li>',
                    esc_html( $table_name ),
                    esc_html( $exists ? __( 'OK', 'smooth-booking' ) : __( 'Missing', 'smooth-booking' ) ),
                    $exists ? 'status-ok' : 'status-missing'
                );
            }
            echo '</ul></div>';
        }
    }
}

namespace {
    use SmoothBooking\Frontend\TemplateTags;

    if ( ! function_exists( 'the_smooth_booking_schema_status' ) ) {
        /**
         * Output Smooth Booking schema status.
         */
        function the_smooth_booking_schema_status(): void {
            TemplateTags::display_schema_status();
        }
    }
}
