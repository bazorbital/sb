<?php
/**
 * Settings page for Smooth Booking.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\SchemaStatusService;

use function __;
use function add_settings_field;
use function add_settings_section;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function get_option;
use function is_rtl;
use function is_wp_error;
use function plugins_url;
use function register_setting;
use function settings_fields;
use function wp_die;
use function wp_enqueue_style;

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
    public const MENU_SLUG = 'smooth-booking-settings';

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
     * Register submenu entry under the Smooth Booking menu.
     */
    public function register_submenu( string $parent_slug ): void {
        add_submenu_page(
            $parent_slug,
            __( 'Smooth Booking Settings', 'smooth-booking' ),
            __( 'Beállítások', 'smooth-booking' ),
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
            __( 'General settings', 'smooth-booking' ),
            '__return_false',
            self::MENU_SLUG
        );

        add_settings_field(
            'smooth_booking_auto_repair_schema',
            __( 'Automatically repair schema on load', 'smooth-booking' ),
            [ $this, 'render_auto_repair_field' ],
            self::MENU_SLUG,
            'smooth_booking_general_section',
            [
                'label_for' => 'smooth-booking-auto-repair-schema',
            ]
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
        $sanitized                              = [];
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

        $status   = $this->schema_service->get_status();
        $is_error = is_wp_error( $status );
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-settings-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Smooth Booking settings', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Configure plugin behaviour and review the database schema health status.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <?php if ( $is_error ) : ?>
                    <div class="smooth-booking-card smooth-booking-settings-status smooth-booking-settings-status--error">
                        <div class="smooth-booking-settings-status__header">
                            <span class="smooth-booking-settings-status__icon dashicons dashicons-warning" aria-hidden="true"></span>
                            <div class="smooth-booking-settings-status__text">
                                <h2><?php esc_html_e( 'Schema status', 'smooth-booking' ); ?></h2>
                                <p class="description"><?php echo esc_html( $status->get_error_message() ); ?></p>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="smooth-booking-card smooth-booking-settings-status">
                        <div class="smooth-booking-settings-status__header">
                            <span class="smooth-booking-settings-status__icon dashicons <?php echo $this->schema_service->schema_is_healthy() ? 'dashicons-yes-alt' : 'dashicons-info-outline'; ?>" aria-hidden="true"></span>
                            <div class="smooth-booking-settings-status__text">
                                <h2><?php esc_html_e( 'Schema status', 'smooth-booking' ); ?></h2>
                                <p class="description"><?php esc_html_e( 'Overview of the database tables required by Smooth Booking.', 'smooth-booking' ); ?></p>
                            </div>
                        </div>
                        <ul class="smooth-booking-schema-status">
                            <?php foreach ( $status as $table_name => $exists ) : ?>
                                <li class="smooth-booking-schema-status__item <?php echo $exists ? 'is-healthy' : 'is-missing'; ?>">
                                    <span class="smooth-booking-schema-status__name"><code><?php echo esc_html( $table_name ); ?></code></span>
                                    <span class="smooth-booking-schema-status__state">
                                        <?php echo $exists ? esc_html__( 'Available', 'smooth-booking' ) : esc_html__( 'Missing', 'smooth-booking' ); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="options.php" method="post" class="smooth-booking-settings-form">
                    <?php settings_fields( self::OPTION_NAME ); ?>
                    <div class="smooth-booking-card smooth-booking-settings-card">
                        <?php do_settings_sections( self::MENU_SLUG ); ?>
                        <div class="smooth-booking-form-actions">
                            <button type="submit" class="sba-btn sba-btn--primary sba-btn__large">
                                <?php esc_html_e( 'Save changes', 'smooth-booking' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for the settings screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

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

        wp_enqueue_style(
            'smooth-booking-admin-base',
            plugins_url( 'assets/css/design/smooth-booking-admin.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-components' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style(
            'smooth-booking-admin-shared',
            plugins_url( 'assets/css/admin-shared.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-base' ],
            SMOOTH_BOOKING_VERSION
        );

        if ( is_rtl() ) {
            wp_enqueue_style(
                'smooth-booking-admin-rtl',
                plugins_url( 'assets/css/design/smooth-booking-admin-rtl.css', SMOOTH_BOOKING_PLUGIN_FILE ),
                [ 'smooth-booking-admin-base' ],
                SMOOTH_BOOKING_VERSION
            );
        }

        wp_enqueue_style(
            'smooth-booking-admin-settings',
            plugins_url( 'assets/css/admin-settings.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );
    }

    /**
     * Render checkbox for auto repair setting.
     */
    public function render_auto_repair_field(): void {
        $option         = get_option( self::OPTION_NAME, [ 'auto_repair_schema' => 1 ] );
        $description_id = 'smooth-booking-auto-repair-schema-description';
        ?>
        <div class="smooth-booking-toggle-field">
            <label class="smooth-booking-toggle" for="smooth-booking-auto-repair-schema">
                <input
                    type="checkbox"
                    class="smooth-booking-toggle__input"
                    name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_repair_schema]"
                    id="smooth-booking-auto-repair-schema"
                    value="1"
                    aria-describedby="<?php echo esc_attr( $description_id ); ?>"
                    <?php checked( ! empty( $option['auto_repair_schema'] ) ); ?>
                />
                <span class="smooth-booking-toggle__control" aria-hidden="true"></span>
                <span class="smooth-booking-toggle__label"><?php esc_html_e( 'Automatically check and repair the schema on each load.', 'smooth-booking' ); ?></span>
            </label>
            <p class="description" id="<?php echo esc_attr( $description_id ); ?>">
                <?php esc_html_e( 'Enable this option to keep the Smooth Booking database tables healthy without manual intervention.', 'smooth-booking' ); ?>
            </p>
        </div>
        <?php
    }
}
