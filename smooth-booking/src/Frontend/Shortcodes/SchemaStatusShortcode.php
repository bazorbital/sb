<?php
/**
 * Shortcode implementation for schema status.
 *
 * @package SmoothBooking\Frontend\Shortcodes
 */

namespace SmoothBooking\Frontend\Shortcodes;

use SmoothBooking\Domain\SchemaStatusService;

/**
 * Registers shortcode for displaying schema status.
 */
class SchemaStatusShortcode {
    /**
     * Shortcode tag name.
     */
    private const TAG = 'smooth_booking_schema_status';

    /**
     * @var SchemaStatusService
     */
    private SchemaStatusService $schema_service;

    /**
     * Constructor.
     */
    public function __construct( SchemaStatusService $schema_service ) {
        $this->schema_service = $schema_service;
    }

    /**
     * Register the shortcode with WordPress.
     */
    public function register(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
    }

    /**
     * Render the shortcode output.
     *
     * @param array<string, string> $atts Shortcode attributes.
     */
    public function render( $atts = [] ): string {
        $atts = shortcode_atts(
            [ 'show_missing_only' => 'no' ],
            array_change_key_case( (array) $atts, CASE_LOWER ),
            self::TAG
        );

        $status = $this->schema_service->get_status();

        if ( is_wp_error( $status ) ) {
            return sprintf( '<div class="smooth-booking-error">%s</div>', esc_html( $status->get_error_message() ) );
        }

        $items = [];
        foreach ( $status as $table_name => $exists ) {
            if ( 'yes' === $atts['show_missing_only'] && $exists ) {
                continue;
            }

            $items[] = sprintf(
                '<li><code>%1$s</code>: <span class="%3$s">%2$s</span></li>',
                esc_html( $table_name ),
                esc_html( $exists ? __( 'OK', 'smooth-booking' ) : __( 'Missing', 'smooth-booking' ) ),
                $exists ? 'status-ok' : 'status-missing'
            );
        }

        if ( empty( $items ) ) {
            return '<div class="smooth-booking-schema-status">' . esc_html__( 'All tables are present.', 'smooth-booking' ) . '</div>';
        }

        return '<div class="smooth-booking-schema-status"><ul>' . implode( '', $items ) . '</ul></div>';
    }
}
