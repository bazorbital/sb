<?php
/**
 * Settings page for Smooth Booking.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\SchemaStatusService;

/**
 * Registers Settings API integration.
 */
class SettingsPage {
    /**
     * Option name for general plugin settings.
     */
    private const OPTION_NAME = 'smooth_booking_settings';

    /**
     * Capability required to manage settings.
     */
    private const CAPABILITY = 'manage_options';

    /**
     * Menu slug.
     */
    private const MENU_SLUG = 'smooth-booking-settings';

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
     * Register admin menu entry.
     */
    public function register_menu(): void {
        add_options_page(
            __( 'Smooth Booking Settings', 'smooth-booking' ),
            __( 'Smooth Booking', 'smooth-booking' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings(): void {
        register_setting(
            self::OPTION_NAME,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => [ 'auto_repair_schema' => 1 ],
            ]
        );

        add_settings_section(
            'smooth_booking_general_section',
            __( 'General', 'smooth-booking' ),
            '__return_false',
            self::MENU_SLUG
        );

        add_settings_field(
            'smooth_booking_auto_repair_schema',
            __( 'Automatically repair schema on load', 'smooth-booking' ),
            [ $this, 'render_auto_repair_field' ],
            self::MENU_SLUG,
            'smooth_booking_general_section'
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array<string, mixed> $input Submitted values.
     *
     * @return array<string, int>
     */
    public function sanitize_settings( $input ): array {
        $sanitized = [];
        $sanitized['auto_repair_schema'] = empty( $input['auto_repair_schema'] ) ? 0 : 1;

        return $sanitized;
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        $option   = get_option( self::OPTION_NAME, [ 'auto_repair_schema' => 1 ] );
        $status   = $this->schema_service->get_status();
        $is_error = is_wp_error( $status );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Smooth Booking Settings', 'smooth-booking' ); ?></h1>
            <?php if ( $is_error ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $status->get_error_message() ); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e( 'Schema health summary:', 'smooth-booking' ); ?>
                        <?php echo $this->schema_service->schema_is_healthy() ? esc_html__( 'All tables exist.', 'smooth-booking' ) : esc_html__( 'One or more tables are missing.', 'smooth-booking' ); ?>
                    </p>
                    <ul>
                        <?php foreach ( $status as $table_name => $exists ) : ?>
                            <li>
                                <code><?php echo esc_html( $table_name ); ?></code>:
                                <?php echo $exists ? esc_html__( 'OK', 'smooth-booking' ) : esc_html__( 'Missing', 'smooth-booking' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( self::OPTION_NAME );
                do_settings_sections( self::MENU_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render checkbox for auto repair setting.
     */
    public function render_auto_repair_field(): void {
        $option = get_option( self::OPTION_NAME, [ 'auto_repair_schema' => 1 ] );
        ?>
        <label for="smooth-booking-auto-repair-schema">
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_repair_schema]" id="smooth-booking-auto-repair-schema" value="1" <?php checked( ! empty( $option['auto_repair_schema'] ) ); ?> />
            <?php esc_html_e( 'Check schema existence on each load and repair automatically if needed.', 'smooth-booking' ); ?>
        </label>
        <?php
    }
}
