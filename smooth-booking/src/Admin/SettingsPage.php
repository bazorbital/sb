<?php
/**
 * Settings page for Smooth Booking.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\SchemaStatusService;

use function __;
use function absint;
use function add_query_arg;
use function add_settings_field;
use function add_settings_section;
use function admin_url;
use function check_admin_referer;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_attr_e;
use function get_option;
use function is_rtl;
use function is_wp_error;
use function plugins_url;
use function register_setting;
use function sanitize_text_field;
use function settings_fields;
use function wp_die;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function selected;
use function is_array;

/**
 * Registers Settings API integration.
 */
class SettingsPage {
    use AdminStylesTrait;
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
     * @var BusinessHoursService
     */
    private BusinessHoursService $business_hours_service;

    /**
     * Constructor.
     */
    public function __construct( SchemaStatusService $schema_service, BusinessHoursService $business_hours_service ) {
        $this->schema_service          = $schema_service;
        $this->business_hours_service = $business_hours_service;
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

        $status                 = $this->schema_service->get_status();
        $is_error               = is_wp_error( $status );
        $general_section        = 'smooth-booking-settings-general';
        $business_hours_section = 'smooth-booking-settings-business-hours';
        $schema_section         = 'smooth-booking-settings-schema';

        $locations            = $this->business_hours_service->list_locations();
        $selected_location_id = $this->determine_selected_location_id( $locations );
        $business_hours_data  = $this->business_hours_service->get_empty_template();
        $business_hours_error = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        $business_hours_saved = isset( $_GET['smooth_booking_business_hours_saved'] );

        if ( $selected_location_id > 0 ) {
            $hours_result = $this->business_hours_service->get_location_hours( $selected_location_id );

            if ( is_wp_error( $hours_result ) ) {
                $business_hours_error = $hours_result->get_error_message();
            } else {
                $business_hours_data = $hours_result;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        if ( isset( $_GET['smooth_booking_business_hours_error'] ) ) {
            $business_hours_error = sanitize_text_field( wp_unslash( $_GET['smooth_booking_business_hours_error'] ) );
        }

        $days         = $this->business_hours_service->get_days();
        $time_options = $this->business_hours_service->get_time_options();
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-settings-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Smooth Booking settings', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Configure plugin behaviour and review the database schema health status.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <div class="smooth-booking-settings-layout">
                    <aside class="smooth-booking-settings-sidebar">
                        <nav class="smooth-booking-settings-nav" aria-label="<?php esc_attr_e( 'Smooth Booking settings sections', 'smooth-booking' ); ?>">
                            <button
                                type="button"
                                class="smooth-booking-settings-nav__button is-active"
                                data-section="general"
                                aria-controls="<?php echo esc_attr( $general_section ); ?>"
                                aria-current="true"
                            >
                                <?php esc_html_e( 'General settings', 'smooth-booking' ); ?>
                            </button>
                            <button
                                type="button"
                                class="smooth-booking-settings-nav__button"
                                data-section="business-hours"
                                aria-controls="<?php echo esc_attr( $business_hours_section ); ?>"
                                aria-current="false"
                            >
                                <?php esc_html_e( 'Business Hours', 'smooth-booking' ); ?>
                            </button>
                            <button
                                type="button"
                                class="smooth-booking-settings-nav__button"
                                data-section="schema"
                                aria-controls="<?php echo esc_attr( $schema_section ); ?>"
                                aria-current="false"
                            >
                                <?php esc_html_e( 'Schema status', 'smooth-booking' ); ?>
                            </button>
                        </nav>
                    </aside>
                    <div class="smooth-booking-settings-main">
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--general is-active"
                            id="<?php echo esc_attr( $general_section ); ?>"
                            data-section="general"
                        >
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
                        </section>
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--business-hours"
                            id="<?php echo esc_attr( $business_hours_section ); ?>"
                            data-section="business-hours"
                        >
                            <div class="smooth-booking-card smooth-booking-settings-card smooth-booking-business-hours">
                                <?php if ( empty( $locations ) ) : ?>
                                    <p class="description"><?php esc_html_e( 'Create at least one location to configure default business hours.', 'smooth-booking' ); ?></p>
                                <?php else : ?>
                                    <?php if ( $business_hours_saved ) : ?>
                                        <div class="notice notice-success is-dismissible">
                                            <p><?php esc_html_e( 'Business hours updated successfully.', 'smooth-booking' ); ?></p>
                                        </div>
                                    <?php elseif ( ! empty( $business_hours_error ) ) : ?>
                                        <div class="notice notice-error">
                                            <p><?php echo esc_html( $business_hours_error ); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <form method="get" class="smooth-booking-business-hours__location">
                                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                                        <label class="smooth-booking-business-hours__label" for="smooth-booking-business-hours-location">
                                            <?php esc_html_e( 'Location', 'smooth-booking' ); ?>
                                        </label>
                                        <div class="smooth-booking-business-hours__location-select">
                                            <select name="business_hours_location" id="smooth-booking-business-hours-location" class="smooth-booking-business-hours__select">
                                                <?php foreach ( $locations as $location ) : ?>
                                                    <option value="<?php echo esc_attr( (string) $location->get_id() ); ?>" <?php selected( $selected_location_id, $location->get_id() ); ?>>
                                                        <?php echo esc_html( $location->get_name() ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="button">
                                                <?php esc_html_e( 'Change location', 'smooth-booking' ); ?>
                                            </button>
                                        </div>
                                    </form>

                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-business-hours__form">
                                        <?php wp_nonce_field( 'smooth_booking_save_business_hours' ); ?>
                                        <input type="hidden" name="action" value="smooth_booking_save_business_hours" />
                                        <input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $selected_location_id ); ?>" />
                                        <p class="description">
                                            <?php esc_html_e( "Please note, the business hours below work as a template for all new staff members. To render a list of available time slots the system takes into account only staff members' schedule, not the company business hours. Be sure to check the schedule of your staff members if you have some unexpected behavior of the booking system.", 'smooth-booking' ); ?>
                                        </p>
                                        <p class="description">
                                            <?php esc_html_e( 'Please note that business hours you set here will be used as visible hours in Calendar for all staff members if you enable "Show only business hours in the calendar" in Settings > Calendar.', 'smooth-booking' ); ?>
                                        </p>
                                        <div class="smooth-booking-business-hours__grid">
                                            <?php foreach ( $days as $day_index => $day ) :
                                                $day_data        = $business_hours_data[ $day_index ] ?? [ 'open' => '', 'close' => '', 'is_closed' => true ];
                                                $open_select_id  = 'smooth-booking-business-hours-open-' . $day_index;
                                                $close_select_id = 'smooth-booking-business-hours-close-' . $day_index;
                                                ?>
                                                <fieldset class="smooth-booking-business-hours__day">
                                                    <legend><?php echo esc_html( $day['label'] ); ?></legend>
                                                    <div class="smooth-booking-business-hours__time">
                                                        <label for="<?php echo esc_attr( $open_select_id ); ?>">
                                                            <span><?php esc_html_e( 'Opening time', 'smooth-booking' ); ?></span>
                                                            <select name="hours[<?php echo esc_attr( (string) $day_index ); ?>][open]" id="<?php echo esc_attr( $open_select_id ); ?>">
                                                                <option value=""><?php esc_html_e( 'Select time', 'smooth-booking' ); ?></option>
                                                                <?php foreach ( $time_options as $value => $label ) : ?>
                                                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $day_data['open'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label for="<?php echo esc_attr( $close_select_id ); ?>">
                                                            <span><?php esc_html_e( 'Closing time', 'smooth-booking' ); ?></span>
                                                            <select name="hours[<?php echo esc_attr( (string) $day_index ); ?>][close]" id="<?php echo esc_attr( $close_select_id ); ?>">
                                                                <option value=""><?php esc_html_e( 'Select time', 'smooth-booking' ); ?></option>
                                                                <?php foreach ( $time_options as $value => $label ) : ?>
                                                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $day_data['close'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    </div>
                                                    <label class="smooth-booking-business-hours__closed">
                                                        <input type="checkbox" name="hours[<?php echo esc_attr( (string) $day_index ); ?>][is_closed]" value="1" <?php checked( ! empty( $day_data['is_closed'] ) ); ?> />
                                                        <span><?php esc_html_e( 'Closed all day', 'smooth-booking' ); ?></span>
                                                    </label>
                                                </fieldset>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="smooth-booking-form-actions">
                                            <button type="submit" class="sba-btn sba-btn--primary sba-btn__large">
                                                <?php esc_html_e( 'Save business hours', 'smooth-booking' ); ?>
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </section>
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--schema"
                            id="<?php echo esc_attr( $schema_section ); ?>"
                            data-section="schema"
                        >
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
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Determine which location should be selected.
     *
     * @param Location[] $locations Locations list.
     */
    private function determine_selected_location_id( array $locations ): int {
        $default = 0;

        if ( ! empty( $locations ) ) {
            $first   = reset( $locations );
            $default = $first instanceof Location ? $first->get_id() : 0;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading selection for display only.
        if ( isset( $_GET['business_hours_location'] ) ) {
            $requested = absint( wp_unslash( $_GET['business_hours_location'] ) );

            foreach ( $locations as $location ) {
                if ( $location instanceof Location && $location->get_id() === $requested ) {
                    return $requested;
                }
            }
        }

        return $default;
    }

    /**
     * Handle saving business hours submissions.
     */
    public function handle_business_hours_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_business_hours' );

        $location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;

        $hours_input = [];

        if ( isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ) {
            $hours_input = wp_unslash( $_POST['hours'] );
        }

        if ( ! is_array( $hours_input ) ) {
            $hours_input = [];
        }

        $result = $this->business_hours_service->save_location_hours( $location_id, $hours_input );

        $redirect = add_query_arg(
            [
                'page' => self::MENU_SLUG,
            ],
            admin_url( 'admin.php' )
        );

        if ( $location_id > 0 ) {
            $redirect = add_query_arg( 'business_hours_location', $location_id, $redirect );
        }

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg(
                'smooth_booking_business_hours_error',
                $result->get_error_message(),
                $redirect
            );
        } else {
            $redirect = add_query_arg( 'smooth_booking_business_hours_saved', '1', $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Enqueue admin assets for the settings screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-admin-settings',
            plugins_url( 'assets/css/admin-settings.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-admin-settings',
            plugins_url( 'assets/js/admin-settings.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [],
            SMOOTH_BOOKING_VERSION,
            true
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
