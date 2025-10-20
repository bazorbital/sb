<?php
/**
 * Registers Gutenberg block for schema status.
 *
 * @package SmoothBooking\Frontend\Blocks
 */

namespace SmoothBooking\Frontend\Blocks;

use SmoothBooking\Domain\SchemaStatusService;

/**
 * Gutenberg integration for schema status block.
 */
class SchemaStatusBlock {
    /**
     * Block name.
     */
    private const BLOCK_NAME = 'smooth-booking/schema-status';

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
     * Register the block type and assets.
     */
    public function register(): void {
        $asset_file = SMOOTH_BOOKING_PLUGIN_DIR . 'assets/js/schema-status-block.js';
        $style_file = SMOOTH_BOOKING_PLUGIN_DIR . 'assets/css/schema-status-block.css';

        wp_register_script(
            'smooth-booking-schema-status-block-editor',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/js/schema-status-block.js',
            [ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' ],
            file_exists( $asset_file ) ? filemtime( $asset_file ) : SMOOTH_BOOKING_VERSION,
            true
        );

        wp_register_style(
            'smooth-booking-schema-status-block-editor',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/css/schema-status-block.css',
            [],
            file_exists( $style_file ) ? filemtime( $style_file ) : SMOOTH_BOOKING_VERSION
        );

        register_block_type(
            self::BLOCK_NAME,
            [
                'editor_script'   => 'smooth-booking-schema-status-block-editor',
                'editor_style'    => 'smooth-booking-schema-status-block-editor',
                'render_callback' => [ $this, 'render' ],
                'attributes'      => [
                    'showMissingOnly' => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * Render callback for dynamic block.
     *
     * @param array<string, mixed> $attributes Block attributes.
     */
    public function render( array $attributes ): string {
        $status = $this->schema_service->get_status();

        if ( is_wp_error( $status ) ) {
            return '<div class="smooth-booking-error">' . esc_html( $status->get_error_message() ) . '</div>';
        }

        $items = [];
        foreach ( $status as $table_name => $exists ) {
            if ( ! empty( $attributes['showMissingOnly'] ) && $exists ) {
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
