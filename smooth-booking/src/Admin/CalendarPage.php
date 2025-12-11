<?php
/**
 * Daily calendar admin screen using EventCalendar layout.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Customers\CustomerTag;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use SmoothBooking\Support\CalendarEventFormatterTrait;
use function __;
use function absint;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_user_locale;
use function is_wp_error;
use function add_query_arg;
use function plugins_url;
use function sanitize_text_field;
use function sanitize_key;
use function selected;
use function sprintf;
use function strtolower;
use function rest_url;
use function wp_add_inline_script;
use function check_admin_referer;
use function wp_date;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_enqueue_media;
use function wp_create_nonce;
use function wp_json_encode;
use function wp_timezone;
use function wp_unslash;
use function sanitize_textarea_field;
use function wp_safe_redirect;
use function wp_get_referer;
use function sanitize_email;
use function strpos;
use function array_merge;
use function is_array;
use function array_unique;
use function in_array;
use function get_users;

/**
 * Renders the Smooth Booking calendar page using a resource-based day view.
 */
class CalendarPage {
    use AdminStylesTrait;
    use CalendarEventFormatterTrait;

    /** Capability required to view the screen. */
    public const CAPABILITY = 'manage_options';

    /** Menu slug used for routing. */
    public const MENU_SLUG = 'smooth-booking-calendar';

    private CalendarService $calendar;

    private LocationService $locations;

    private ServiceService $services;

    private AppointmentService $appointments;

    private GeneralSettings $settings;

    private Logger $logger;

    private CustomerService $customers;

    public function __construct( AppointmentService $appointments, CalendarService $calendar, LocationService $locations, ServiceService $services, GeneralSettings $settings, Logger $logger, CustomerService $customers ) {
        $this->appointments = $appointments;
        $this->calendar     = $calendar;
        $this->locations    = $locations;
        $this->services     = $services;
        $this->settings     = $settings;
        $this->logger       = $logger;
        $this->customers    = $customers;
    }

    /**
     * Render the calendar admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'smooth-booking' ) );
        }

        $locations = $this->locations->list_locations();

        if ( empty( $locations ) ) {
            $this->render_empty_state();
            return;
        }

        $location_id     = $this->determine_location_id( $locations );
        $location        = $this->find_location( $locations, $location_id );
        $timezone        = $this->resolve_timezone( $location );
        $selected_date   = $this->determine_date( $timezone );
        $schedule_result = $this->calendar->get_daily_schedule( $location_id, $selected_date );

        $customer_tags = $this->customers->list_tags();
        $customer_users = get_users(
            [
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'fields'  => [ 'ID', 'display_name', 'user_email' ],
            ]
        );

        wp_enqueue_media();

        if ( is_wp_error( $schedule_result ) ) {
            $this->logger->error(
                sprintf(
                    'Calendar schedule failed for location #%d on %s: %s',
                    $location_id,
                    $selected_date->format( 'Y-m-d' ),
                    $schedule_result->get_error_message()
                )
            );
        }

        $employees         = [];
        $day_events        = [];
        $events            = [];
        $open_time         = $selected_date->setTime( 8, 0 );
        $close_time        = $open_time->add( new DateInterval( 'PT10H' ) );
        $slot_length       = $this->settings->get_time_slot_length();
        $is_closed         = false;
        $has_day_events    = false;
        $range_start       = $selected_date->sub( new DateInterval( 'P7D' ) );
        $range_end         = $selected_date->add( new DateInterval( 'P7D' ) );
        $view_window       = $this->calendar->build_view_window( $open_time, $close_time );

        if ( ! is_wp_error( $schedule_result ) ) {
            $employees      = $this->unique_employees( $schedule_result['employees'] ?? [] );
            $day_events     = $this->build_events( $schedule_result['appointments'] ?? [], $timezone );
            $events         = $this->build_events(
                $schedule_result['window_appointments'] ?? ( $schedule_result['appointments'] ?? [] ),
                $timezone
            );
            $open_time      = isset( $schedule_result['open'] ) && $schedule_result['open'] instanceof DateTimeImmutable
                ? $schedule_result['open']->setTimezone( $timezone )
                : $open_time;
            $close_time     = isset( $schedule_result['close'] ) && $schedule_result['close'] instanceof DateTimeImmutable
                ? $schedule_result['close']->setTimezone( $timezone )
                : $close_time;
            $slot_length    = isset( $schedule_result['slot_length'] ) ? (int) $schedule_result['slot_length'] : $slot_length;
            $is_closed      = ! empty( $schedule_result['is_closed'] );
            $has_day_events = ! empty( $day_events );
            if ( isset( $schedule_result['window_start'] ) && $schedule_result['window_start'] instanceof DateTimeImmutable ) {
                $range_start = $schedule_result['window_start']->setTimezone( $timezone );
            }

            if ( isset( $schedule_result['window_end'] ) && $schedule_result['window_end'] instanceof DateTimeImmutable ) {
                $range_end = $schedule_result['window_end']->setTimezone( $timezone );
            }

            $view_window = $this->calendar->build_view_window( $open_time, $close_time );
        }

        $resources = $this->calendar->build_resources_payload( $employees );
        $service_templates = $this->build_service_templates( $resources );

        $locations_payload = array_values(
            array_filter(
                array_map(
                    static function ( $item ) {
                        return $item instanceof Location
                            ? [ 'id' => $item->get_id(), 'name' => $item->get_name() ]
                            : null;
                    },
                    $locations
                )
            )
        );

        $this->inject_calendar_payload(
            [
                'resources'       => $resources,
                'events'          => $events,
                'selectedDate'    => $selected_date->setTimezone( $timezone )->format( 'Y-m-d' ),
                'openTime'        => $open_time->format( 'H:i:s' ),
                'closeTime'       => $close_time instanceof DateTimeImmutable ? $close_time->format( 'H:i:s' ) : null,
                'scrollTime'      => $view_window['scrollTime'] ?? $open_time->format( 'H:i:s' ),
                'slotDuration'    => $this->calendar->format_slot_duration( $slot_length ),
                'slotLengthMinutes' => $slot_length,
                'slotMinTime'     => $view_window['slotMinTime'] ?? '06:00:00',
                'slotMaxTime'     => $view_window['slotMaxTime'] ?? '22:00:00',
                'locale'          => $this->get_locale_code(),
                'timezone'        => $timezone->getName(),
                'hasEvents'       => ! empty( $events ),
                'hasSelectedDayEvents' => $has_day_events,
                'rangeStart'      => $range_start->setTimezone( $timezone )->format( 'Y-m-d' ),
                'rangeEnd'        => $range_end->setTimezone( $timezone )->format( 'Y-m-d' ),
                'isClosed'        => $is_closed,
                'locationName'    => $location ? $location->get_name() : '',
                'selectedDateIso' => $selected_date->setTimezone( $timezone )->format( DateTimeInterface::ATOM ),
                'endpoint'        => rest_url( 'smooth-booking/v1/calendar/schedule' ),
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'locations'       => $locations_payload,
                'locationId'      => $location_id,
                'services'        => $service_templates,
                'appointmentsEndpoint' => rest_url( 'smooth-booking/v1/appointments' ),
                'customersEndpoint'    => rest_url( 'smooth-booking/v1/customers' ),
            ]
        );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <?php $this->render_notices(); ?>
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Calendar', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php echo esc_html__( 'Review today’s appointments per employee. The view defaults to the current day and uses service colours for each booking.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <form method="get" action="<?php echo esc_attr( admin_url( 'admin.php' ) ); ?>" class="smooth-booking-calendar-filters">
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                    <label>
                        <span><?php echo esc_html__( 'Location', 'smooth-booking' ); ?></span>
                        <select name="location_id" id="smooth-booking-calendar-location">
                            <?php foreach ( $locations as $location_item ) : ?>
                                <?php if ( ! $location_item instanceof Location ) { continue; } ?>
                                <option value="<?php echo esc_attr( (string) $location_item->get_id() ); ?>" <?php selected( $location_item->get_id(), $location_id ); ?>><?php echo esc_html( $location_item->get_name() ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Date', 'smooth-booking' ); ?></span>
                        <input type="date" name="calendar_date" id="smooth-booking-calendar-date" value="<?php echo esc_attr( $selected_date->setTimezone( $timezone )->format( 'Y-m-d' ) ); ?>" />
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Employees', 'smooth-booking' ); ?></span>
                        <select
                            name="resource_filter[]"
                            id="smooth-booking-resource-filter"
                            class="smooth-booking-select2"
                            multiple="multiple"
                            data-placeholder="<?php echo esc_attr__( 'All employees', 'smooth-booking' ); ?>"
                            data-close-on-select="false"
                            data-allow-clear="true"
                        >
                            <?php foreach ( $resources as $resource ) : ?>
                                <?php if ( empty( $resource['id'] ) ) { continue; } ?>
                                <option value="<?php echo esc_attr( (string) $resource['id'] ); ?>"><?php echo esc_html( $resource['title'] ?? '' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Services', 'smooth-booking' ); ?></span>
                        <select
                            name="service_filter[]"
                            id="smooth-booking-service-filter"
                            class="smooth-booking-select2"
                            multiple="multiple"
                            data-placeholder="<?php echo esc_attr__( 'All services', 'smooth-booking' ); ?>"
                            data-close-on-select="false"
                            data-allow-clear="true"
                        ></select>
                    </label>
                    <button type="submit" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-calendar-submit"><?php echo esc_html__( 'Show day', 'smooth-booking' ); ?></button>
                </form>

                <?php if ( is_wp_error( $schedule_result ) ) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html( $schedule_result->get_error_message() ); ?></p></div>
                <?php endif; ?>

                <div class="smooth-booking-calendar-board" id="smooth-booking-calendar-container">
                    <div class="smooth-booking-calendar-meta">
                        <span class="smooth-booking-calendar-meta__item">
                            <?php echo esc_html( sprintf( '%s · %s', $location ? $location->get_name() : __( 'Unknown location', 'smooth-booking' ), $timezone->getName() ) ); ?>
                        </span>
                        <span class="smooth-booking-calendar-meta__item">
                            <?php echo esc_html( wp_date( 'F j, Y', $selected_date->getTimestamp(), $timezone ) ); ?>
                        </span>
                    </div>

                    <?php if ( $is_closed ) : ?>
                        <div class="notice notice-info"><p><?php echo esc_html__( 'The selected location is closed on this day.', 'smooth-booking' ); ?></p></div>
                    <?php endif; ?>

                    <div id="smooth-booking-calendar"></div>
                    <div class="smooth-booking-calendar-empty" data-calendar-empty <?php echo ! empty( $events ) ? 'hidden' : ''; ?>>
                        <p><?php echo esc_html__( 'No appointments scheduled for the selected day.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <dialog id="smooth-booking-calendar-dialog" class="smooth-booking-calendar-dialog" hidden>
                    <form
                        id="smooth-booking-calendar-booking-form"
                        class="smooth-booking-calendar-dialog__form"
                        method="post"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                    >
                        <input type="hidden" name="action" value="smooth_booking_save_calendar_booking" />
                        <?php wp_nonce_field( 'smooth-booking-calendar-booking', 'smooth_booking_calendar_nonce' ); ?>
                        <header class="smooth-booking-calendar-dialog__header">
                            <div>
                                <p class="smooth-booking-calendar-dialog__eyebrow"><?php echo esc_html__( 'New appointment', 'smooth-booking' ); ?></p>
                                <h2 class="smooth-booking-calendar-dialog__title"><?php echo esc_html__( 'Add appointment', 'smooth-booking' ); ?></h2>
                                <p class="smooth-booking-calendar-dialog__meta">
                                    <span id="smooth-booking-calendar-booking-resource-label"></span>
                                    <span aria-hidden="true">·</span>
                                    <span id="smooth-booking-calendar-booking-date"></span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="smooth-booking-calendar-dialog__close"
                                id="smooth-booking-calendar-booking-cancel"
                                aria-label="<?php echo esc_attr__( 'Close', 'smooth-booking' ); ?>"
                                data-smooth-booking-dismiss="dialog"
                            >×</button>
                        </header>

                        <div class="smooth-booking-calendar-dialog__body">
                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Employee', 'smooth-booking' ); ?></span>
                                <select id="smooth-booking-calendar-booking-resource" name="booking-resource"></select>
                            </label>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Service', 'smooth-booking' ); ?></span>
                                <select id="smooth-booking-calendar-booking-service" name="booking-service"></select>
                            </label>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Date', 'smooth-booking' ); ?></span>
                                <input type="date" id="smooth-booking-calendar-booking-date-input" name="booking-date" />
                            </label>

                            <div class="smooth-booking-calendar-dialog__grid">
                                <label class="smooth-booking-calendar-dialog__field">
                                    <span><?php echo esc_html__( 'Start', 'smooth-booking' ); ?></span>
                                    <input type="time" id="smooth-booking-calendar-booking-start" name="booking-start" />
                                </label>
                                <label class="smooth-booking-calendar-dialog__field">
                                    <span><?php echo esc_html__( 'End', 'smooth-booking' ); ?></span>
                                    <input type="time" id="smooth-booking-calendar-booking-end" name="booking-end" />
                                </label>
                            </div>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Customer', 'smooth-booking' ); ?></span>
                                <select id="smooth-booking-calendar-booking-customer" name="booking-customer"></select>
                                <button
                                    type="button"
                                    class="button button-link smooth-booking-calendar-add-customer"
                                    id="smooth-booking-calendar-add-customer"
                                ><?php echo esc_html__( 'Add new customer', 'smooth-booking' ); ?></button>
                            </label>

                            <div class="smooth-booking-calendar-dialog__grid">
                                <label class="smooth-booking-calendar-dialog__field">
                                    <span><?php echo esc_html__( 'Status', 'smooth-booking' ); ?></span>
                                    <select id="smooth-booking-calendar-booking-status" name="booking-status">
                                        <option value="pending"><?php echo esc_html__( 'Pending', 'smooth-booking' ); ?></option>
                                        <option value="confirmed"><?php echo esc_html__( 'Confirmed', 'smooth-booking' ); ?></option>
                                        <option value="completed"><?php echo esc_html__( 'Completed', 'smooth-booking' ); ?></option>
                                        <option value="canceled"><?php echo esc_html__( 'Canceled', 'smooth-booking' ); ?></option>
                                    </select>
                                </label>
                                <label class="smooth-booking-calendar-dialog__field">
                                    <span><?php echo esc_html__( 'Payment status', 'smooth-booking' ); ?></span>
                                    <select id="smooth-booking-calendar-booking-payment" name="booking-payment">
                                        <option value=""><?php echo esc_html__( 'Not set', 'smooth-booking' ); ?></option>
                                        <option value="pending"><?php echo esc_html__( 'Pending', 'smooth-booking' ); ?></option>
                                        <option value="authorized"><?php echo esc_html__( 'Authorized', 'smooth-booking' ); ?></option>
                                        <option value="paid"><?php echo esc_html__( 'Paid', 'smooth-booking' ); ?></option>
                                        <option value="refunded"><?php echo esc_html__( 'Refunded', 'smooth-booking' ); ?></option>
                                        <option value="failed"><?php echo esc_html__( 'Failed', 'smooth-booking' ); ?></option>
                                        <option value="canceled"><?php echo esc_html__( 'Canceled', 'smooth-booking' ); ?></option>
                                    </select>
                                </label>
                            </div>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Customer email', 'smooth-booking' ); ?></span>
                                <input type="email" id="smooth-booking-calendar-booking-customer-email" name="booking-customer-email" />
                            </label>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Customer phone', 'smooth-booking' ); ?></span>
                                <input type="text" id="smooth-booking-calendar-booking-customer-phone" name="booking-customer-phone" />
                            </label>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Internal note', 'smooth-booking' ); ?></span>
                                <textarea id="smooth-booking-calendar-booking-internal-note" name="booking-internal-note" rows="3"></textarea>
                            </label>

                            <label class="smooth-booking-calendar-dialog__field">
                                <span><?php echo esc_html__( 'Customer notes', 'smooth-booking' ); ?></span>
                                <textarea id="smooth-booking-calendar-booking-note" name="booking-note" rows="3"></textarea>
                            </label>

                            <label class="smooth-booking-calendar-dialog__field smooth-booking-calendar-dialog__checkbox">
                                <span><?php echo esc_html__( 'Send notifications', 'smooth-booking' ); ?></span>
                                <input type="checkbox" id="smooth-booking-calendar-booking-notify" name="booking-notify" value="1" />
                            </label>

                            <p class="smooth-booking-calendar-dialog__error" id="smooth-booking-calendar-booking-error" hidden></p>
                        </div>

                        <div class="smooth-booking-calendar-dialog__actions">
                            <button
                                type="button"
                                class="button button-secondary"
                                id="smooth-booking-calendar-booking-cancel-alt"
                                data-smooth-booking-dismiss="dialog"
                            ><?php echo esc_html__( 'Cancel', 'smooth-booking' ); ?></button>
                            <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save appointment', 'smooth-booking' ); ?></button>
                        </div>
                    </form>
                </dialog>

                <dialog id="smooth-booking-calendar-customer-dialog" class="smooth-booking-calendar-dialog smooth-booking-calendar-customer-dialog" hidden>
                    <form
                        id="smooth-booking-calendar-customer-form"
                        class="smooth-booking-calendar-dialog__form"
                        method="post"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                    >
                        <?php wp_nonce_field( 'smooth_booking_save_customer', '_smooth_booking_save_nonce' ); ?>
                        <input type="hidden" name="action" value="smooth_booking_save_customer" />
                        <header class="smooth-booking-calendar-dialog__header">
                            <div>
                                <p class="smooth-booking-calendar-dialog__eyebrow"><?php echo esc_html__( 'New customer', 'smooth-booking' ); ?></p>
                                <h2 class="smooth-booking-calendar-dialog__title"><?php echo esc_html__( 'Add new customer', 'smooth-booking' ); ?></h2>
                            </div>
                            <button
                                type="button"
                                class="smooth-booking-calendar-dialog__close smooth-booking-calendar-customer-dismiss"
                                aria-label="<?php echo esc_attr__( 'Close', 'smooth-booking' ); ?>"
                                data-smooth-booking-customer-dismiss="dialog"
                            >×</button>
                        </header>
                        <div class="smooth-booking-calendar-dialog__body smooth-booking-calendar-customer-dialog__body">
                            <p class="smooth-booking-calendar-dialog__description"><?php echo esc_html__( 'Create a customer without leaving the calendar. The new profile will be added to the list and preselected for this booking.', 'smooth-booking' ); ?></p>
                            <div class="smooth-booking-calendar-customer-scroll">
                                <table class="form-table" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-name"><?php esc_html_e( 'Customer name', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-name" name="customer_name" value="" required /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Profile image', 'smooth-booking' ); ?></th>
                                            <td>
                                                <div class="smooth-booking-avatar-field" data-placeholder="<?php echo esc_attr( '<span class=\"smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder dashicons dashicons-admin-users\" aria-hidden=\"true\"></span>' ); ?>">
                                                    <div class="smooth-booking-avatar-preview">
                                                        <span class="smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder dashicons dashicons-admin-users" aria-hidden="true"></span>
                                                    </div>
                                                    <div class="smooth-booking-avatar-actions">
                                                        <button type="button" class="sba-btn sba-btn__small sba-btn__filled smooth-booking-avatar-select"><?php esc_html_e( 'Choose image', 'smooth-booking' ); ?></button>
                                                        <button type="button" class="sba-btn sba-btn__small sba-btn__filled-light smooth-booking-avatar-remove" style="display:none"><?php esc_html_e( 'Remove', 'smooth-booking' ); ?></button>
                                                        <input type="hidden" name="customer_profile_image_id" value="0" />
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-user-action"><?php esc_html_e( 'WordPress user', 'smooth-booking' ); ?></label></th>
                                            <td>
                                                <select id="smooth-booking-customer-user-action" name="customer_user_action">
                                                    <option value="none"><?php esc_html_e( "Don't create a WordPress user", 'smooth-booking' ); ?></option>
                                                    <option value="create"><?php esc_html_e( 'Create WordPress user', 'smooth-booking' ); ?></option>
                                                    <option value="assign"><?php esc_html_e( 'Assign existing WordPress user', 'smooth-booking' ); ?></option>
                                                </select>
                                                <p class="description"><?php esc_html_e( 'Create or assign a WordPress user account for this customer.', 'smooth-booking' ); ?></p>
                                                <div class="smooth-booking-existing-user-field" style="display:none">
                                                    <label for="smooth-booking-customer-existing-user" class="screen-reader-text"><?php esc_html_e( 'Existing WordPress user', 'smooth-booking' ); ?></label>
                                                    <select id="smooth-booking-customer-existing-user" name="customer_existing_user">
                                                        <option value="0"><?php esc_html_e( 'Select user', 'smooth-booking' ); ?></option>
                                                        <?php foreach ( $customer_users as $user ) : ?>
                                                            <?php if ( ! $user instanceof \WP_User ) { continue; } ?>
                                                            <option value="<?php echo esc_attr( (string) $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-first-name"><?php esc_html_e( 'First name', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-first-name" name="customer_first_name" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-last-name"><?php esc_html_e( 'Last name', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-last-name" name="customer_last_name" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-phone"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-phone" name="customer_phone" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-email"><?php esc_html_e( 'Email', 'smooth-booking' ); ?></label></th>
                                            <td><input type="email" class="regular-text" id="smooth-booking-customer-email" name="customer_email" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-tags"><?php esc_html_e( 'Tags', 'smooth-booking' ); ?></label></th>
                                            <td>
                                                <select id="smooth-booking-customer-tags" name="customer_tags[]" multiple size="5" class="smooth-booking-tags-select">
                                                    <?php foreach ( $customer_tags as $tag ) : ?>
                                                        <?php if ( ! $tag instanceof CustomerTag ) { continue; } ?>
                                                        <option value="<?php echo esc_attr( (string) $tag->get_id() ); ?>"><?php echo esc_html( $tag->get_name() ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="description"><?php esc_html_e( 'Hold CTRL or CMD to select multiple tags.', 'smooth-booking' ); ?></p>
                                                <label for="smooth-booking-customer-new-tags" class="screen-reader-text"><?php esc_html_e( 'Create new tags', 'smooth-booking' ); ?></label>
                                                <input type="text" id="smooth-booking-customer-new-tags" name="customer_new_tags" value="" placeholder="<?php echo esc_attr__( 'Add new tags separated by comma', 'smooth-booking' ); ?>" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-date-of-birth"><?php esc_html_e( 'Date of birth', 'smooth-booking' ); ?></label></th>
                                            <td><input type="date" id="smooth-booking-customer-date-of-birth" name="customer_date_of_birth" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-country"><?php esc_html_e( 'Country', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-country" name="customer_country" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-state-region"><?php esc_html_e( 'State/Region', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-state-region" name="customer_state_region" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-postal-code"><?php esc_html_e( 'Postal code', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-postal-code" name="customer_postal_code" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-city"><?php esc_html_e( 'City', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-city" name="customer_city" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-street-address"><?php esc_html_e( 'Street address', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-street-address" name="customer_street_address" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-additional-address"><?php esc_html_e( 'Additional address', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-additional-address" name="customer_additional_address" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-street-number"><?php esc_html_e( 'Street number', 'smooth-booking' ); ?></label></th>
                                            <td><input type="text" class="regular-text" id="smooth-booking-customer-street-number" name="customer_street_number" value="" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="smooth-booking-customer-notes"><?php esc_html_e( 'Notes', 'smooth-booking' ); ?></label></th>
                                            <td>
                                                <textarea id="smooth-booking-customer-notes" name="customer_notes" rows="4" class="large-text"></textarea>
                                                <p class="description"><?php esc_html_e( 'This text can be inserted into notifications with {client_note} code.', 'smooth-booking' ); ?></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="smooth-booking-calendar-dialog__error" id="smooth-booking-calendar-customer-error" hidden></p>
                            </div>
                        </div>
                        <div class="smooth-booking-calendar-dialog__actions">
                            <button
                                type="button"
                                class="button button-secondary smooth-booking-calendar-customer-dismiss"
                                data-smooth-booking-customer-dismiss="dialog"
                            ><?php echo esc_html__( 'Cancel', 'smooth-booking' ); ?></button>
                            <button type="submit" class="button button-primary" id="smooth-booking-calendar-customer-submit"><?php echo esc_html__( 'Create customer', 'smooth-booking' ); ?></button>
                        </div>
                    </form>
                </dialog>
            </div>
        </div>
        <?php
    }

    /**
     * Handle booking submissions when JavaScript is unavailable.
     */
    public function handle_booking_submit(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to create appointments.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth-booking-calendar-booking', 'smooth_booking_calendar_nonce' );

        $payload = [
            'provider_id'        => isset( $_POST['booking-resource'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['booking-resource'] ) ) ) : 0,
            'service_id'         => isset( $_POST['booking-service'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['booking-service'] ) ) ) : 0,
            'customer_id'        => isset( $_POST['booking-customer'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['booking-customer'] ) ) ) : 0,
            'appointment_date'   => isset( $_POST['booking-date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['booking-date'] ) ) : '',
            'appointment_start'  => isset( $_POST['booking-start'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['booking-start'] ) ) : '',
            'appointment_end'    => isset( $_POST['booking-end'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['booking-end'] ) ) : '',
            'status'             => isset( $_POST['booking-status'] ) ? sanitize_key( wp_unslash( (string) $_POST['booking-status'] ) ) : 'pending',
            'payment_status'     => isset( $_POST['booking-payment'] ) ? sanitize_key( wp_unslash( (string) $_POST['booking-payment'] ) ) : '',
            'notes'              => isset( $_POST['booking-note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['booking-note'] ) ) : '',
            'internal_note'      => isset( $_POST['booking-internal-note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['booking-internal-note'] ) ) : '',
            'send_notifications' => ! empty( $_POST['booking-notify'] ),
            'customer_email'     => isset( $_POST['booking-customer-email'] ) ? sanitize_email( wp_unslash( (string) $_POST['booking-customer-email'] ) ) : '',
            'customer_phone'     => isset( $_POST['booking-customer-phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['booking-customer-phone'] ) ) : '',
        ];

        $this->logger->info(
            sprintf(
                'Calendar modal submission received (provider #%d, service #%d, date %s %s, customer #%d).',
                $payload['provider_id'],
                $payload['service_id'],
                $payload['appointment_date'],
                $payload['appointment_start'],
                $payload['customer_id']
            )
        );

        $result = $this->appointments->create_appointment( $payload );

        if ( is_wp_error( $result ) ) {
            $this->logger->error(
                sprintf(
                    'Calendar booking creation failed: %s (provider #%d, service #%d, date %s %s)',
                    $result->get_error_message(),
                    $payload['provider_id'],
                    $payload['service_id'],
                    $payload['appointment_date'],
                    $payload['appointment_start']
                )
            );

            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $this->logger->info(
            sprintf(
                'Calendar booking created via admin modal for provider #%d, service #%d on %s %s (appointment #%d).',
                $payload['provider_id'],
                $payload['service_id'],
                $payload['appointment_date'],
                $payload['appointment_start'],
                $result->get_id()
            )
        );

        $this->redirect_with_notice( 'created', __( 'Appointment created successfully.', 'smooth-booking' ) );
    }

    /**
     * Redirect back to the calendar with a contextual notice.
     */
    private function redirect_with_notice( string $type, string $message = '' ): void {
        $redirect = wp_get_referer();

        if ( ! $redirect || false === strpos( $redirect, 'page=' . self::MENU_SLUG ) ) {
            $redirect = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) );
        }

        $args = [
            'smooth_booking_calendar_notice'  => $type,
            'smooth_booking_calendar_message' => $message,
        ];

        wp_safe_redirect( add_query_arg( $args, $redirect ) );
        exit;
    }

    /**
     * Render a notice based on query parameters.
     */
    private function render_notices(): void {
        $notice  = isset( $_GET['smooth_booking_calendar_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['smooth_booking_calendar_notice'] ) ) : '';
        $message = isset( $_GET['smooth_booking_calendar_message'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['smooth_booking_calendar_message'] ) ) : '';

        if ( 'created' === $notice && '' === $message ) {
            $message = __( 'Appointment created successfully.', 'smooth-booking' );
        }

        if ( 'error' === $notice && '' === $message ) {
            $message = __( 'Unable to save appointment. Please try again.', 'smooth-booking' );
        }

        if ( ! $notice || '' === $message ) {
            return;
        }

        $class = 'notice-success';

        if ( 'error' === $notice ) {
            $class = 'notice-error';
        }

        ?>
        <div class="notice <?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Enqueue assets for the calendar screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-event-calendar',
            'https://cdn.jsdelivr.net/npm/@event-calendar/build@4.7.1/dist/event-calendar.min.css',
            [],
            '4.7.1'
        );

        wp_enqueue_style(
            'smooth-booking-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0-rc.0'
        );

        wp_enqueue_style(
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/css/admin-calendar.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-event-calendar', 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-event-calendar',
            'https://cdn.jsdelivr.net/npm/@event-calendar/build@4.7.1/dist/event-calendar.min.js',
            [],
            '4.7.1',
            true
        );

        wp_enqueue_script(
            'smooth-booking-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.1.0-rc.0',
            true
        );

        wp_enqueue_script(
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/js/admin-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'smooth-booking-event-calendar', 'smooth-booking-select2' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $localization = [
            'i18n' => [
                'resourceColumn'    => __( 'Employees', 'smooth-booking' ),
                'customerLabel'     => __( 'Customer', 'smooth-booking' ),
                'emailLabel'        => __( 'Email', 'smooth-booking' ),
                'phoneLabel'        => __( 'Phone', 'smooth-booking' ),
                'timeRangeTemplate' => __( '%1$s – %2$s', 'smooth-booking' ),
                'noEvents'          => __( 'No appointments scheduled for this day.', 'smooth-booking' ),
                'loading'           => __( 'Loading calendar…', 'smooth-booking' ),
                'resourcesView'     => __( 'Resources', 'smooth-booking' ),
                'timelineView'      => __( 'Timeline', 'smooth-booking' ),
                'addAppointment'    => __( 'Add appointment', 'smooth-booking' ),
                'bookingValidation' => __( 'Please choose a staff member, service, start, and end time.', 'smooth-booking' ),
                'bookingEndpointMissing' => __( 'Booking endpoint is unavailable. Please reload the page.', 'smooth-booking' ),
                'bookingSaveError'  => __( 'Unable to save appointment. Please try again.', 'smooth-booking' ),
                'bookingSaved'      => __( 'Appointment saved successfully.', 'smooth-booking' ),
                'bookingMoved'      => __( 'Appointment rescheduled.', 'smooth-booking' ),
                'bookingMoveError'  => __( 'Unable to move appointment. Please try again.', 'smooth-booking' ),
            ],
        ];

        $encoded = wp_json_encode( $localization );

        if ( $encoded ) {
            wp_add_inline_script(
                'smooth-booking-admin-calendar',
                'window.SmoothBookingCalendar = window.SmoothBookingCalendar || {}; window.SmoothBookingCalendar = Object.assign(window.SmoothBookingCalendar, ' . $encoded . ');',
                'before'
            );
        }
    }

    /**
     * Render the empty state when no locations exist.
     */
    private function render_empty_state(): void {
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <div class="notice notice-warning"><p><?php echo esc_html__( 'Add a location before viewing the calendar.', 'smooth-booking' ); ?></p></div>
            </div>
        </div>
        <?php
    }

    /**
     * Determine which location should be displayed.
     *
     * @param Location[] $locations Available locations.
     */
    private function determine_location_id( array $locations ): int {
        $requested = isset( $_GET['location_id'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_GET['location_id'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $requested > 0 && $this->find_location( $locations, $requested ) ) {
            return $requested;
        }

        foreach ( $locations as $location ) {
            if ( $location instanceof Location ) {
                return $location->get_id();
            }
        }

        return 0;
    }

    /**
     * Determine the date to display.
     */
    private function determine_date( DateTimeZone $timezone ): DateTimeImmutable {
        $param = isset( $_GET['calendar_date'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['calendar_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $param ) {
            $candidate = DateTimeImmutable::createFromFormat( 'Y-m-d', $param, $timezone );

            if ( $candidate instanceof DateTimeImmutable ) {
                return $candidate;
            }
        }

        return new DateTimeImmutable( 'now', $timezone );
    }

    /**
     * Find a location by id.
     *
     * @param Location[] $locations Locations collection.
     */
    private function find_location( array $locations, int $location_id ): ?Location {
        foreach ( $locations as $location ) {
            if ( $location instanceof Location && $location->get_id() === $location_id ) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Normalise employees so each id is represented once.
     *
     * @param array<int,Employee> $employees Employees list.
     *
     * @return Employee[]
     */
    private function unique_employees( array $employees ): array {
        $seen = [];
        $unique = [];

        foreach ( $employees as $employee ) {
            if ( ! $employee instanceof Employee ) {
                continue;
            }

            $id = $employee->get_id();

            if ( isset( $seen[ $id ] ) ) {
                continue;
            }

            $seen[ $id ] = true;
            $unique[]   = $employee;
        }

        return $unique;
    }

    /**
     * Inject payload for the JavaScript calendar bootstrapping.
     *
     * @param array<string,mixed> $payload Data passed to the front-end script.
     */
    private function inject_calendar_payload( array $payload ): void {
        $encoded = wp_json_encode( $payload );

        if ( ! $encoded ) {
            return;
        }

        wp_add_inline_script(
            'smooth-booking-admin-calendar',
            'window.SmoothBookingCalendarData = ' . $encoded . '; window.SmoothBookingCalendar = window.SmoothBookingCalendar || {}; window.SmoothBookingCalendar.data = window.SmoothBookingCalendarData;',
            'before'
        );
    }

    /**
     * Build service templates for the booking modal from available resources.
     *
     * @param array<int,array<string,mixed>> $resources EventCalendar resources.
     *
     * @return array<int,array<string,mixed>>
     */
    private function build_service_templates( array $resources ): array {
        $service_ids = [];

        foreach ( $resources as $resource ) {
            if ( empty( $resource['serviceIds'] ) || ! is_array( $resource['serviceIds'] ) ) {
                continue;
            }

            $service_ids = array_merge( $service_ids, $resource['serviceIds'] );
        }

        $service_ids = array_values( array_unique( array_map( 'absint', $service_ids ) ) );

        if ( empty( $service_ids ) ) {
            return [];
        }

        $templates = [];
        $services  = $this->services->list_services();

        foreach ( $services as $service ) {
            if ( ! $service instanceof Service || ! in_array( $service->get_id(), $service_ids, true ) ) {
                continue;
            }

            $templates[ $service->get_id() ] = [
                'id'              => $service->get_id(),
                'name'            => $service->get_name(),
                'durationMinutes' => $this->duration_to_minutes( $service->get_duration_key() ),
                'color'           => $this->normalize_color( $service->get_background_color() ),
                'textColor'       => $this->normalize_color( $service->get_text_color(), '#111827' ),
            ];
        }

        return $templates;
    }

    /**
     * Convert duration keys into minute values.
     */
    private function duration_to_minutes( string $key ): int {
        if ( preg_match( '/^(\d+)_minutes$/', $key, $matches ) ) {
            return (int) $matches[1];
        }

        $map = [
            'one_day'    => 1440,
            'two_days'   => 2880,
            'three_days' => 4320,
            'four_days'  => 5760,
            'five_days'  => 7200,
            'six_days'   => 8640,
            'one_week'   => 10080,
        ];

        return $map[ $key ] ?? 30;
    }

    /**
     * Resolve the timezone used for the current location.
     */
    private function resolve_timezone( ?Location $location ): DateTimeZone {
        if ( $location && $location->get_timezone() ) {
            try {
                return new DateTimeZone( $location->get_timezone() );
            } catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                $this->logger->warning(
                    sprintf(
                        'Invalid timezone "%s" for location #%d, falling back to site timezone.',
                        $location->get_timezone(),
                        $location->get_id()
                    )
                );
            }
        }

        return wp_timezone();
    }

    /**
     * Derive the locale code used by EventCalendar.
     */
    private function get_locale_code(): string {
        $locale = get_user_locale();

        return strtolower( str_replace( '_', '-', $locale ) );
    }

}
