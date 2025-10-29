<?php
/**
 * Settings page for Smooth Booking.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Holidays\Holiday;
use SmoothBooking\Domain\Holidays\HolidayService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\SchemaStatusService;
use SmoothBooking\Domain\Notifications\EmailSettingsService;

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
use function sanitize_email;
use function is_email;
use function get_option;
use function is_rtl;
use function is_wp_error;
use function plugins_url;
use function register_setting;
use function sanitize_key;
use function sanitize_textarea_field;
use function sanitize_text_field;
use function settings_fields;
use function wp_enqueue_script;
use function wp_die;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_localize_script;
use function wp_safe_redirect;
use function wp_unslash;
use function selected;
use function is_array;
use function gmdate;
use function max;
use function min;
use function rawurlencode;

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
     * @var HolidayService
     */
    private HolidayService $holiday_service;

    /**
     * @var EmailSettingsService
     */
    private EmailSettingsService $email_settings;

    /**
     * Constructor.
     */
    public function __construct( SchemaStatusService $schema_service, BusinessHoursService $business_hours_service, HolidayService $holiday_service, EmailSettingsService $email_settings ) {
        $this->schema_service           = $schema_service;
        $this->business_hours_service   = $business_hours_service;
        $this->holiday_service          = $holiday_service;
        $this->email_settings           = $email_settings;
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
        $holidays_section       = 'smooth-booking-settings-holidays';
        $schema_section         = 'smooth-booking-settings-schema';

        $email_section = 'smooth-booking-settings-email';

        $sections = [
            'general'        => $general_section,
            'email'          => $email_section,
            'business-hours' => $business_hours_section,
            'holidays'       => $holidays_section,
            'schema'         => $schema_section,
        ];

        $active_section = $this->determine_active_section( array_keys( $sections ) );
        $display_year   = $this->determine_display_year();

        $locations            = $this->business_hours_service->list_locations();
        $selected_location_id = $this->determine_selected_location_id( $locations );
        $holiday_location_id  = $this->determine_selected_location_id( $locations, 'holidays_location' );
        $business_hours_data  = $this->business_hours_service->get_empty_template();
        $business_hours_error = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        $business_hours_saved = isset( $_GET['smooth_booking_business_hours_saved'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        $holiday_saved   = isset( $_GET['smooth_booking_holiday_saved'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        $holiday_deleted = isset( $_GET['smooth_booking_holiday_deleted'] );
        $holiday_error   = '';
        $holidays        = [];

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

        if ( $holiday_location_id > 0 ) {
            $holidays_result = $this->holiday_service->get_location_holidays( $holiday_location_id );

            if ( is_wp_error( $holidays_result ) ) {
                $holiday_error = $holidays_result->get_error_message();
            } else {
                $holidays = $holidays_result;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displaying admin feedback.
        if ( isset( $_GET['smooth_booking_holiday_error'] ) ) {
            $holiday_error = sanitize_text_field( wp_unslash( $_GET['smooth_booking_holiday_error'] ) );
        }

        $days         = $this->business_hours_service->get_days();
        $time_options = $this->business_hours_service->get_time_options();
        $email_settings   = $this->email_settings->get_settings();
        $email_saved      = isset( $_GET['smooth_booking_email_saved'] );
        $email_error      = '';
        $email_tested     = isset( $_GET['smooth_booking_email_test'] );

        if ( isset( $_GET['smooth_booking_email_error'] ) ) {
            $email_error = sanitize_text_field( wp_unslash( (string) $_GET['smooth_booking_email_error'] ) );
        }

        $nav_items    = [
            'general'        => __( 'General settings', 'smooth-booking' ),
            'email'          => __( 'Email', 'smooth-booking' ),
            'business-hours' => __( 'Business Hours', 'smooth-booking' ),
            'holidays'       => __( 'Holidays', 'smooth-booking' ),
            'schema'         => __( 'Schema status', 'smooth-booking' ),
        ];
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
                            <?php foreach ( $nav_items as $section_key => $label ) :
                                $section_id = $sections[ $section_key ] ?? '';
                                $is_active  = $section_key === $active_section;
                                ?>
                                <button
                                    type="button"
                                    class="smooth-booking-settings-nav__button<?php echo $is_active ? ' is-active' : ''; ?>"
                                    data-section="<?php echo esc_attr( $section_key ); ?>"
                                    aria-controls="<?php echo esc_attr( $section_id ); ?>"
                                    aria-current="<?php echo $is_active ? 'true' : 'false'; ?>"
                                >
                                    <?php echo esc_html( $label ); ?>
                                </button>
                            <?php endforeach; ?>
                        </nav>
                    </aside>
                    <div class="smooth-booking-settings-main" data-default-section="<?php echo esc_attr( $active_section ); ?>">
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--general<?php echo 'general' === $active_section ? ' is-active' : ''; ?>"
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
                            class="smooth-booking-settings-section smooth-booking-settings-section--email<?php echo 'email' === $active_section ? ' is-active' : ''; ?>"
                            id="<?php echo esc_attr( $email_section ); ?>"
                            data-section="email"
                        >
                            <div class="smooth-booking-card smooth-booking-settings-card smooth-booking-email-settings">
                                <?php if ( $email_saved ) : ?>
                                    <div class="notice notice-success is-dismissible">
                                        <p><?php esc_html_e( 'Email settings updated successfully.', 'smooth-booking' ); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $email_tested ) : ?>
                                    <div class="notice notice-success is-dismissible">
                                        <p><?php esc_html_e( 'Test email sent. Please check your inbox.', 'smooth-booking' ); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ( ! empty( $email_error ) ) : ?>
                                    <div class="notice notice-error">
                                        <p><?php echo esc_html( $email_error ); ?></p>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-email-settings__form">
                                    <?php wp_nonce_field( 'smooth_booking_save_email_settings' ); ?>
                                    <input type="hidden" name="action" value="smooth_booking_save_email_settings" />
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <tr>
                                                <th scope="row"><label for="smooth-booking-email-sender-name"><?php esc_html_e( 'Sender name', 'smooth-booking' ); ?></label></th>
                                                <td>
                                                    <input type="text" id="smooth-booking-email-sender-name" name="email_settings[sender_name]" class="regular-text" value="<?php echo esc_attr( $email_settings['sender_name'] ?? '' ); ?>" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><label for="smooth-booking-email-sender-email"><?php esc_html_e( 'Sender email', 'smooth-booking' ); ?></label></th>
                                                <td>
                                                    <input type="email" id="smooth-booking-email-sender-email" name="email_settings[sender_email]" class="regular-text" value="<?php echo esc_attr( $email_settings['sender_email'] ?? '' ); ?>" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e( 'Send emails as', 'smooth-booking' ); ?></th>
                                                <td>
                                                    <label class="smooth-booking-radio-inline"><input type="radio" name="email_settings[send_format]" value="html" <?php checked( ( $email_settings['send_format'] ?? 'html' ), 'html' ); ?> /> <?php esc_html_e( 'HTML', 'smooth-booking' ); ?></label>
                                                    <label class="smooth-booking-radio-inline"><input type="radio" name="email_settings[send_format]" value="text" <?php checked( ( $email_settings['send_format'] ?? 'html' ), 'text' ); ?> /> <?php esc_html_e( 'Text', 'smooth-booking' ); ?></label>
                                                    <p class="description"><?php esc_html_e( 'HTML allows formatting, colors, fonts, positioning, etc. With Text you must use Text mode of rich-text editors below. On some servers only text emails are sent successfully.', 'smooth-booking' ); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e( 'Reply directly to customers', 'smooth-booking' ); ?></th>
                                                <td>
                                                    <label class="smooth-booking-radio-inline"><input type="radio" name="email_settings[reply_to_customer]" value="1" <?php checked( ! empty( $email_settings['reply_to_customer'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'smooth-booking' ); ?></label>
                                                    <label class="smooth-booking-radio-inline"><input type="radio" name="email_settings[reply_to_customer]" value="0" <?php checked( empty( $email_settings['reply_to_customer'] ) ); ?> /> <?php esc_html_e( 'Disabled', 'smooth-booking' ); ?></label>
                                                    <p class="description"><?php esc_html_e( 'If this option is enabled then the email address of the customer is used as a sender email address for notifications sent to staff members and administrators.', 'smooth-booking' ); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><label for="smooth-booking-email-retry"><?php esc_html_e( 'Scheduled notifications retry period', 'smooth-booking' ); ?></label></th>
                                                <td>
                                                    <select id="smooth-booking-email-retry" name="email_settings[retry_period_hours]" class="regular-text">
                                                        <?php foreach ( $this->email_settings->get_retry_period_options() as $value => $label ) : ?>
                                                            <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) ( $email_settings['retry_period_hours'] ?? 1 ), $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="description"><?php esc_html_e( 'Set period of time when system will attempt to deliver notification to user. Notification will be discarded after period expiration.', 'smooth-booking' ); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><label for="smooth-booking-email-gateway"><?php esc_html_e( 'Mail gateway', 'smooth-booking' ); ?></label></th>
                                                <td>
                                                    <select id="smooth-booking-email-gateway" name="email_settings[mail_gateway]" class="regular-text smooth-booking-email-gateway">
                                                        <option value="wordpress" <?php selected( ( $email_settings['mail_gateway'] ?? 'wordpress' ), 'wordpress' ); ?>><?php esc_html_e( 'WordPress mail', 'smooth-booking' ); ?></option>
                                                        <option value="smtp" <?php selected( ( $email_settings['mail_gateway'] ?? 'wordpress' ), 'smtp' ); ?>><?php esc_html_e( 'SMTP', 'smooth-booking' ); ?></option>
                                                    </select>
                                                    <div class="smooth-booking-email-smtp-settings<?php echo ( ( $email_settings['mail_gateway'] ?? 'wordpress' ) === 'smtp' ) ? ' is-visible' : ''; ?>">
                                                        <label for="smooth-booking-email-smtp-host" class="screen-reader-text"><?php esc_html_e( 'SMTP hostname', 'smooth-booking' ); ?></label>
                                                        <input type="text" id="smooth-booking-email-smtp-host" name="email_settings[smtp][host]" class="regular-text" placeholder="<?php esc_attr_e( 'Hostname', 'smooth-booking' ); ?>" value="<?php echo esc_attr( $email_settings['smtp']['host'] ?? '' ); ?>" />
                                                        <input type="text" id="smooth-booking-email-smtp-port" name="email_settings[smtp][port]" class="small-text" placeholder="<?php esc_attr_e( 'Port', 'smooth-booking' ); ?>" value="<?php echo esc_attr( $email_settings['smtp']['port'] ?? '' ); ?>" />
                                                        <input type="text" id="smooth-booking-email-smtp-username" name="email_settings[smtp][username]" class="regular-text" placeholder="<?php esc_attr_e( 'Username', 'smooth-booking' ); ?>" value="<?php echo esc_attr( $email_settings['smtp']['username'] ?? '' ); ?>" />
                                                        <input type="password" id="smooth-booking-email-smtp-password" name="email_settings[smtp][password]" class="regular-text" placeholder="<?php esc_attr_e( 'Password', 'smooth-booking' ); ?>" value="" autocomplete="new-password" />
                                                        <select id="smooth-booking-email-smtp-secure" name="email_settings[smtp][secure]" class="regular-text">
                                                            <option value="disabled" <?php selected( ( $email_settings['smtp']['secure'] ?? 'disabled' ), 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'smooth-booking' ); ?></option>
                                                            <option value="ssl" <?php selected( ( $email_settings['smtp']['secure'] ?? 'disabled' ), 'ssl' ); ?>><?php esc_html_e( 'SSL', 'smooth-booking' ); ?></option>
                                                            <option value="tls" <?php selected( ( $email_settings['smtp']['secure'] ?? 'disabled' ), 'tls' ); ?>><?php esc_html_e( 'TLS', 'smooth-booking' ); ?></option>
                                                        </select>
                                                    </div>
                                                    <p class="description"><?php esc_html_e( 'Select a mail gateway that will be used to send email notifications. For more information, see the documentation page.', 'smooth-booking' ); ?></p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="smooth-booking-form-actions">
                                        <button type="submit" class="sba-btn sba-btn--primary sba-btn__large"><?php esc_html_e( 'Save email settings', 'smooth-booking' ); ?></button>
                                    </div>
                                </form>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-email-settings__test">
                                    <?php wp_nonce_field( 'smooth_booking_send_test_email' ); ?>
                                    <input type="hidden" name="action" value="smooth_booking_send_test_email" />
                                    <input type="email" name="test_email" class="regular-text" value="<?php echo esc_attr( $email_settings['sender_email'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Recipient email address', 'smooth-booking' ); ?>" />
                                    <button type="submit" class="sba-btn sba-btn__medium sba-btn__filled"><?php esc_html_e( 'Send test email', 'smooth-booking' ); ?></button>
                                </form>
                            </div>
                        </section>
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--business-hours<?php echo 'business-hours' === $active_section ? ' is-active' : ''; ?>"
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
                                        <input type="hidden" name="smooth_booking_section" value="business-hours" />
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
                                        <input type="hidden" name="smooth_booking_section" value="business-hours" />
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
                            class="smooth-booking-settings-section smooth-booking-settings-section--holidays<?php echo 'holidays' === $active_section ? ' is-active' : ''; ?>"
                            id="<?php echo esc_attr( $holidays_section ); ?>"
                            data-section="holidays"
                        >
                            <div class="smooth-booking-card smooth-booking-settings-card smooth-booking-holidays">
                                <?php if ( empty( $locations ) ) : ?>
                                    <p class="description"><?php esc_html_e( 'Create at least one location to configure holidays.', 'smooth-booking' ); ?></p>
                                <?php else : ?>
                                    <?php if ( $holiday_saved ) : ?>
                                        <div class="notice notice-success is-dismissible">
                                            <p><?php esc_html_e( 'Holiday saved successfully.', 'smooth-booking' ); ?></p>
                                        </div>
                                    <?php elseif ( $holiday_deleted ) : ?>
                                        <div class="notice notice-success is-dismissible">
                                            <p><?php esc_html_e( 'Holiday deleted successfully.', 'smooth-booking' ); ?></p>
                                        </div>
                                    <?php elseif ( ! empty( $holiday_error ) ) : ?>
                                        <div class="notice notice-error">
                                            <p><?php echo esc_html( $holiday_error ); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <form method="get" class="smooth-booking-holidays__location">
                                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                                        <input type="hidden" name="smooth_booking_section" value="holidays" />
                                        <input type="hidden" name="holidays_year" value="<?php echo esc_attr( (string) $display_year ); ?>" data-year-sync="true" />
                                        <label class="smooth-booking-holidays__label" for="smooth-booking-holidays-location">
                                            <?php esc_html_e( 'Location', 'smooth-booking' ); ?>
                                        </label>
                                        <div class="smooth-booking-holidays__location-select">
                                            <select name="holidays_location" id="smooth-booking-holidays-location" class="smooth-booking-holidays__select">
                                                <?php foreach ( $locations as $location ) : ?>
                                                    <option value="<?php echo esc_attr( (string) $location->get_id() ); ?>" <?php selected( $holiday_location_id, $location->get_id() ); ?>>
                                                        <?php echo esc_html( $location->get_name() ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="button">
                                                <?php esc_html_e( 'Change location', 'smooth-booking' ); ?>
                                            </button>
                                        </div>
                                    </form>

                                    <?php if ( $holiday_location_id > 0 ) : ?>
                                        <div class="smooth-booking-holidays__content" data-smooth-booking-holidays="true">
                                            <div class="smooth-booking-holidays__intro">
                                                <p class="description">
                                                    <?php esc_html_e( 'Select individual days or a date range in the calendar, then confirm the note and whether the closure repeats every year.', 'smooth-booking' ); ?>
                                                </p>
                                            </div>
                                            <div class="smooth-booking-holidays__calendar-wrapper">
                                                <div class="smooth-booking-holidays__calendar-header">
                                                    <button type="button" class="button button-secondary" data-holiday-year-control="previous" aria-label="<?php esc_attr_e( 'Previous year', 'smooth-booking' ); ?>">&lsaquo;</button>
                                                    <div class="smooth-booking-holidays__calendar-year" data-year-display><?php echo esc_html( (string) $display_year ); ?></div>
                                                    <button type="button" class="button button-secondary" data-holiday-year-control="next" aria-label="<?php esc_attr_e( 'Next year', 'smooth-booking' ); ?>">&rsaquo;</button>
                                                </div>
                                                <div class="smooth-booking-holidays__calendar" data-holiday-calendar></div>
                                            </div>
                                            <div class="smooth-booking-holidays__forms">
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-holidays__form">
                                                    <?php wp_nonce_field( 'smooth_booking_save_holiday' ); ?>
                                                    <input type="hidden" name="action" value="smooth_booking_save_holiday" />
                                                    <input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $holiday_location_id ); ?>" />
                                                    <input type="hidden" name="display_year" id="smooth-booking-holidays-year" value="<?php echo esc_attr( (string) $display_year ); ?>" data-year-sync="true" />
                                                    <input type="hidden" name="smooth_booking_section" value="holidays" />
                                                    <div class="smooth-booking-holidays__fields">
                                                        <label for="smooth-booking-holiday-start">
                                                            <span><?php esc_html_e( 'Start date', 'smooth-booking' ); ?></span>
                                                            <input type="date" id="smooth-booking-holiday-start" name="start_date" required />
                                                        </label>
                                                        <label for="smooth-booking-holiday-end">
                                                            <span><?php esc_html_e( 'End date', 'smooth-booking' ); ?></span>
                                                            <input type="date" id="smooth-booking-holiday-end" name="end_date" required />
                                                        </label>
                                                        <label for="smooth-booking-holiday-note">
                                                            <span><?php esc_html_e( 'Note', 'smooth-booking' ); ?></span>
                                                            <input type="text" id="smooth-booking-holiday-note" name="note" maxlength="255" placeholder="<?php echo esc_attr__( 'We are not working on this day', 'smooth-booking' ); ?>" />
                                                        </label>
                                                    </div>
                                                    <div class="smooth-booking-holidays__controls">
                                                        <label class="smooth-booking-holidays__repeat">
                                                            <input type="checkbox" name="is_recurring" value="1" />
                                                            <span><?php esc_html_e( 'Repeat every year', 'smooth-booking' ); ?></span>
                                                        </label>
                                                        <button type="button" class="button button-link smooth-booking-holidays__clear" data-holiday-clear>
                                                            <?php esc_html_e( 'Clear selection', 'smooth-booking' ); ?>
                                                        </button>
                                                    </div>
                                                    <div class="smooth-booking-form-actions">
                                                        <button type="submit" class="sba-btn sba-btn--primary sba-btn__large">
                                                            <?php esc_html_e( 'Save holiday', 'smooth-booking' ); ?>
                                                        </button>
                                                    </div>
                                                </form>
                                                <div class="smooth-booking-holidays__list-wrapper">
                                                    <h3><?php esc_html_e( 'Configured holidays', 'smooth-booking' ); ?></h3>
                                                    <?php if ( empty( $holidays ) ) : ?>
                                                        <p class="description"><?php esc_html_e( 'No holidays have been configured for this location yet.', 'smooth-booking' ); ?></p>
                                                    <?php else : ?>
                                                        <ul class="smooth-booking-holidays__list">
                                                            <?php foreach ( $holidays as $holiday ) : ?>
                                                                <?php
                                                                if ( ! $holiday instanceof Holiday ) {
                                                                    continue;
                                                                }

                                                                ?>
                                                                <li class="smooth-booking-holidays__list-item">
                                                                    <div class="smooth-booking-holidays__list-text">
                                                                        <span class="smooth-booking-holidays__list-date"><?php echo esc_html( $holiday->get_date() ); ?></span>
                                                                        <span class="smooth-booking-holidays__list-type">
                                                                            <?php echo $holiday->is_recurring() ? esc_html__( 'Repeats annually', 'smooth-booking' ) : esc_html__( 'One-time', 'smooth-booking' ); ?>
                                                                        </span>
                                                                        <?php if ( '' !== $holiday->get_note() ) : ?>
                                                                            <span class="smooth-booking-holidays__list-note"><?php echo esc_html( $holiday->get_note() ); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-holidays__delete-form">
                                                                        <?php wp_nonce_field( 'smooth_booking_delete_holiday_' . $holiday->get_id() ); ?>
                                                                        <input type="hidden" name="action" value="smooth_booking_delete_holiday" />
                                                                        <input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $holiday_location_id ); ?>" />
                                                                        <input type="hidden" name="holiday_id" value="<?php echo esc_attr( (string) $holiday->get_id() ); ?>" />
                                                                        <input type="hidden" name="display_year" value="<?php echo esc_attr( (string) $display_year ); ?>" />
                                                                        <input type="hidden" name="smooth_booking_section" value="holidays" />
                                                                        <button type="submit" class="button-link-delete">
                                                                            <?php esc_html_e( 'Delete', 'smooth-booking' ); ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                        <section
                            class="smooth-booking-settings-section smooth-booking-settings-section--schema<?php echo 'schema' === $active_section ? ' is-active' : ''; ?>"
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
    private function determine_selected_location_id( array $locations, string $query_var = 'business_hours_location' ): int {
        $default = 0;

        if ( ! empty( $locations ) ) {
            $first   = reset( $locations );
            $default = $first instanceof Location ? $first->get_id() : 0;
        }

        $query_var = sanitize_key( $query_var );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading selection for display only.
        if ( isset( $_GET[ $query_var ] ) ) {
            $requested = absint( wp_unslash( $_GET[ $query_var ] ) );

            foreach ( $locations as $location ) {
                if ( $location instanceof Location && $location->get_id() === $requested ) {
                    return $requested;
                }
            }
        }

        return $default;
    }

    /**
     * Determine the active settings section.
     *
     * @param string[] $allowed Allowed section slugs.
     */
    private function determine_active_section( array $allowed ): string {
        $default = 'general';

        if ( empty( $allowed ) ) {
            return $default;
        }

        if ( ! in_array( $default, $allowed, true ) ) {
            $first = reset( $allowed );
            $default = is_string( $first ) ? $first : 'general';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display purposes only.
        if ( isset( $_GET['smooth_booking_section'] ) ) {
            $requested = sanitize_key( wp_unslash( $_GET['smooth_booking_section'] ) );

            if ( in_array( $requested, $allowed, true ) ) {
                return $requested;
            }
        }

        return $default;
    }

    /**
     * Determine which year should be displayed in the holidays calendar.
     */
    private function determine_display_year(): int {
        $current = (int) gmdate( 'Y' );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display purposes only.
        if ( isset( $_GET['holidays_year'] ) ) {
            $year = absint( wp_unslash( $_GET['holidays_year'] ) );

            if ( $year > 0 ) {
                return $this->sanitize_year( $year );
            }
        }

        return $this->sanitize_year( $current );
    }

    /**
     * Normalise year values for holiday navigation.
     */
    private function sanitize_year( int $year ): int {
        return max( 1970, min( 2100, $year ) );
    }

    /**
     * Build redirect URL for holiday operations.
     */
    private function build_holidays_redirect( int $location_id, int $year ): string {
        $redirect = add_query_arg(
            [
                'page'                   => self::MENU_SLUG,
                'smooth_booking_section' => 'holidays',
            ],
            admin_url( 'admin.php' )
        );

        if ( $location_id > 0 ) {
            $redirect = add_query_arg( 'holidays_location', absint( $location_id ), $redirect );
        }

        $redirect = add_query_arg( 'holidays_year', $year, $redirect );

        return $redirect;
    }

    /**
     * Convert holiday objects into serialisable arrays for scripts.
     *
     * @param Holiday[] $holidays Holidays collection.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepare_holidays_for_script( array $holidays ): array {
        $prepared = [];

        foreach ( $holidays as $holiday ) {
            if ( ! $holiday instanceof Holiday ) {
                continue;
            }

            $prepared[] = [
                'id'           => $holiday->get_id(),
                'location_id'  => $holiday->get_location_id(),
                'date'         => $holiday->get_date(),
                'note'         => $holiday->get_note(),
                'is_recurring' => $holiday->is_recurring(),
            ];
        }

        return $prepared;
    }

    /**
     * Handle saving email settings submissions.
     */
    public function handle_email_settings_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_email_settings' );

        $input = [];

        if ( isset( $_POST['email_settings'] ) && is_array( $_POST['email_settings'] ) ) {
            $input = wp_unslash( $_POST['email_settings'] );
        }

        if ( ! is_array( $input ) ) {
            $input = [];
        }

        $sanitized = $this->email_settings->sanitize_settings( $input );
        $redirect  = add_query_arg(
            [
                'page'                   => self::MENU_SLUG,
                'smooth_booking_section' => 'email',
            ],
            admin_url( 'admin.php' )
        );

        $error_message = '';

        if ( empty( $sanitized['sender_email'] ) || ! is_email( (string) $sanitized['sender_email'] ) ) {
            $error_message = __( 'Please provide a valid sender email address.', 'smooth-booking' );
        } elseif ( 'smtp' === ( $sanitized['mail_gateway'] ?? 'wordpress' ) ) {
            $host = isset( $sanitized['smtp']['host'] ) ? trim( (string) $sanitized['smtp']['host'] ) : '';

            if ( '' === $host ) {
                $error_message = __( 'Please provide an SMTP hostname when using the SMTP gateway.', 'smooth-booking' );
            }
        }

        if ( '' === $error_message ) {
            $this->email_settings->save_settings( $input );
            $redirect = add_query_arg( 'smooth_booking_email_saved', '1', $redirect );
        } else {
            $redirect = add_query_arg( 'smooth_booking_email_error', $error_message, $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle sending a test email.
     */
    public function handle_send_test_email(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_send_test_email' );

        $recipient = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['test_email'] ) ) : '';
        $redirect  = add_query_arg(
            [
                'page'                   => self::MENU_SLUG,
                'smooth_booking_section' => 'email',
            ],
            admin_url( 'admin.php' )
        );

        $result = $this->email_settings->send_test_email( $recipient );

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg( 'smooth_booking_email_error', $result->get_error_message(), $redirect );
        } else {
            $redirect = add_query_arg( 'smooth_booking_email_test', '1', $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
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

        $redirect = add_query_arg( 'smooth_booking_section', 'business-hours', $redirect );

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
     * Handle saving holiday submissions.
     */
    public function handle_holiday_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_holiday' );

        $location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
        $start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $note        = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        $is_recurring = ! empty( $_POST['is_recurring'] );
        $year         = isset( $_POST['display_year'] ) ? absint( wp_unslash( $_POST['display_year'] ) ) : (int) gmdate( 'Y' );
        $year         = $this->sanitize_year( $year );

        $payload = [
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'note'         => $note,
            'is_recurring' => $is_recurring,
        ];

        $result = $this->holiday_service->save_location_holiday( $location_id, $payload );

        $redirect = $this->build_holidays_redirect( $location_id, $year );

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg(
                'smooth_booking_holiday_error',
                rawurlencode( $result->get_error_message() ),
                $redirect
            );
        } else {
            $redirect = add_query_arg( 'smooth_booking_holiday_saved', '1', $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle deleting a holiday.
     */
    public function handle_holiday_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smooth-booking' ) );
        }

        $holiday_id  = isset( $_POST['holiday_id'] ) ? absint( wp_unslash( $_POST['holiday_id'] ) ) : 0;
        $location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;

        check_admin_referer( 'smooth_booking_delete_holiday_' . $holiday_id );

        $year = isset( $_POST['display_year'] ) ? absint( wp_unslash( $_POST['display_year'] ) ) : (int) gmdate( 'Y' );
        $year = $this->sanitize_year( $year );

        $result = $this->holiday_service->delete_location_holiday( $location_id, $holiday_id );

        $redirect = $this->build_holidays_redirect( $location_id, $year );

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg(
                'smooth_booking_holiday_error',
                rawurlencode( $result->get_error_message() ),
                $redirect
            );
        } else {
            $redirect = add_query_arg( 'smooth_booking_holiday_deleted', '1', $redirect );
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

        wp_enqueue_script(
            'smooth-booking-admin-holidays',
            plugins_url( 'assets/js/admin-holidays.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-settings' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $locations           = $this->business_hours_service->list_locations();
        $holiday_location_id = $this->determine_selected_location_id( $locations, 'holidays_location' );
        $display_year        = $this->determine_display_year();
        $holidays_data       = [];

        if ( $holiday_location_id > 0 ) {
            $holidays_result = $this->holiday_service->get_location_holidays( $holiday_location_id );

            if ( ! is_wp_error( $holidays_result ) ) {
                $holidays_data = $this->prepare_holidays_for_script( $holidays_result );
            }
        }

        $localization = [
            'holidays'         => $holidays_data,
            'currentYear'      => $display_year,
            'selectedLocation' => $holiday_location_id,
            'l10n'             => [
                'defaultNote'     => __( 'We are not working on this day', 'smooth-booking' ),
                'recurringLabel'  => __( 'Repeats annually', 'smooth-booking' ),
                'singleLabel'     => __( 'One-time', 'smooth-booking' ),
                'selectionCleared'=> __( 'Selection cleared.', 'smooth-booking' ),
            ],
        ];

        wp_localize_script( 'smooth-booking-admin-holidays', 'smoothBookingHolidays', $localization );
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
