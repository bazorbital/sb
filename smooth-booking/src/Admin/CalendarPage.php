<?php
/**
 * Calendar overview screen for appointments.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use DateTimeImmutable;
use DateTimeZone;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use WP_Error;

use function __;
use function absint;
use function admin_url;
use function add_query_arg;
use function array_filter;
use function array_map;
use function array_values;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function in_array;
use function is_array;
use function is_ssl;
use function plugins_url;
use function sanitize_text_field;
use function selected;
use function sprintf;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_json_encode;
use function wp_nonce_field;
use function wp_timezone;
use function wp_unslash;

/**
 * Displays a per-location calendar with employee columns.
 */
class CalendarPage {
    use AdminStylesTrait;
    use AppointmentFormRendererTrait;

    public const CAPABILITY = 'manage_options';

    public const MENU_SLUG = 'smooth-booking-calendar';

    private CalendarService $calendar;

    private LocationService $locations;

    private EmployeeService $employees;

    private ServiceService $services;

    private CustomerService $customers;

    protected GeneralSettings $general_settings;

    public function __construct(
        CalendarService $calendar,
        LocationService $locations,
        EmployeeService $employees,
        ServiceService $services,
        CustomerService $customers,
        GeneralSettings $general_settings
    ) {
        $this->calendar          = $calendar;
        $this->locations         = $locations;
        $this->employees         = $employees;
        $this->services          = $services;
        $this->customers         = $customers;
        $this->general_settings  = $general_settings;
    }

    /**
     * Render the calendar admin screen.
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

        $location_id  = $this->determine_location_id( $locations );
        $selected_date = $this->determine_date();
        $services     = $this->services->list_services();
        $selected_service_ids = $this->determine_selected_service_ids( $services );
        $requested_employee_ids = $this->get_requested_employee_ids();
        $schedule     = $this->calendar->get_daily_schedule( $location_id, $selected_date );
        $error_message = '';
        $employees    = [];
        $slots        = [];
        $appointments_by_employee = [];
        $is_closed    = false;
        $open_time    = $selected_date->setTime( 8, 0 );
        $close_time   = null;
        $slot_length  = $this->general_settings->get_time_slot_length();
        $selected_employee_ids = [];
        $all_employees = [];
        $no_employee_selected = false;

        if ( is_wp_error( $schedule ) ) {
            $error_message = $schedule->get_error_message();
        } else {
            $employees   = $schedule['employees'] ?? [];
            $all_employees = $employees;
            $selected_employee_ids = $this->determine_selected_employee_ids( $employees, $requested_employee_ids );
            $employees   = $this->filter_employees_for_display( $employees, $selected_employee_ids );
            if ( empty( $employees ) && ! empty( $all_employees ) ) {
                $no_employee_selected = true;
            }
            $slots       = $schedule['slots'] ?? [];
            $appointments = $schedule['appointments'] ?? [];
            $appointments = $this->filter_appointments( $appointments, $selected_service_ids, $selected_employee_ids );
            $appointments_by_employee = $this->group_appointments_by_employee( $appointments );
            $is_closed = ! empty( $schedule['is_closed'] );
            if ( isset( $schedule['open'] ) && $schedule['open'] instanceof DateTimeImmutable ) {
                $open_time = $schedule['open'];
            }
            if ( isset( $schedule['close'] ) && $schedule['close'] instanceof DateTimeImmutable ) {
                $close_time = $schedule['close'];
            }
            if ( isset( $schedule['slot_length'] ) ) {
                $slot_length = (int) $schedule['slot_length'];
            }
        }

        $customers = $this->customers->paginate_customers( [ 'per_page' => 200 ] );
        $customer_items = [];
        if ( is_array( $customers ) && isset( $customers['customers'] ) && is_array( $customers['customers'] ) ) {
            $customer_items = $customers['customers'];
        }

        $current_location = $this->find_location( $locations, $location_id );
        $timezone_string  = $current_location ? $current_location->get_timezone() : get_option( 'timezone_string', 'UTC' );
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Calendar', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Review today\'s schedule per employee and add new bookings directly from the grid.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <?php
                $this->render_filters(
                    $locations,
                    $location_id,
                    $selected_date,
                    $services,
                    $selected_service_ids,
                    $all_employees,
                    $selected_employee_ids
                );
                ?>

                <?php if ( $error_message ) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?></p></div>
                <?php endif; ?>

                <div class="smooth-booking-calendar-board" data-slot-length="<?php echo esc_attr( (string) $slot_length ); ?>">
                    <?php if ( $is_closed ) : ?>
                        <div class="notice notice-info"><p><?php esc_html_e( 'This location is closed on the selected day.', 'smooth-booking' ); ?></p></div>
                    <?php elseif ( empty( $slots ) || empty( $all_employees ) ) : ?>
                        <div class="notice notice-warning"><p><?php esc_html_e( 'No working hours or employees available for the selected location.', 'smooth-booking' ); ?></p></div>
                    <?php elseif ( $no_employee_selected ) : ?>
                        <div class="notice notice-warning"><p><?php esc_html_e( 'No staff members are selected. Use the quick filters above to display at least one employee.', 'smooth-booking' ); ?></p></div>
                    <?php else : ?>
                        <?php $this->render_calendar_grid( $slots, $employees, $appointments_by_employee, $open_time, $close_time, $slot_length, $selected_date, $timezone_string ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $this->render_modal( $all_employees, $services, $customer_items );
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
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/css/admin-calendar.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'select2' );

        wp_enqueue_script(
            'smooth-booking-vanilla-calendar',
            plugins_url( 'assets/js/vendor/vanilla-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_enqueue_script(
            'smooth-booking-event-calendar',
            plugins_url( 'assets/js/vendor/event-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_enqueue_script(
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/js/admin-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'smooth-booking-vanilla-calendar', 'smooth-booking-event-calendar', 'select2' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $localization = [
            'strings' => [
                'newAppointment' => __( 'New appointment', 'smooth-booking' ),
                'modalTitle'     => __( 'Add appointment', 'smooth-booking' ),
                'close'          => __( 'Close', 'smooth-booking' ),
            ],
            'modalSelector' => '#smooth-booking-calendar-modal',
            'slotLength'    => $this->general_settings->get_time_slot_length(),
        ];

        wp_localize_script( 'smooth-booking-admin-calendar', 'SmoothBookingCalendar', $localization );
    }

    /**
     * Render filters for location, services, and staff members.
     *
     * @param Location[]        $locations              Available locations.
     * @param int               $location_id            Current location id.
     * @param DateTimeImmutable $selected_date          Selected date.
     * @param Service[]         $services               Services collection for the filter.
     * @param int[]             $selected_service_ids   Active service identifiers.
     * @param Employee[]        $employees              Employees assigned to the location.
     * @param int[]             $selected_employee_ids  Active employee identifiers.
     */
    private function render_filters( array $locations, int $location_id, DateTimeImmutable $selected_date, array $services, array $selected_service_ids, array $employees, array $selected_employee_ids ): void {
        $timezone = wp_timezone();
        $date_value = $selected_date->setTimezone( $timezone )->format( 'Y-m-d' );
        $all_services_selected = empty( $services ) || count( $selected_service_ids ) === count( $this->extract_ids_from_services( $services ) );
        ?>
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="smooth-booking-calendar-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
            <label>
                <span><?php esc_html_e( 'Location', 'smooth-booking' ); ?></span>
                <select name="location_id">
                    <?php foreach ( $locations as $location ) : ?>
                        <?php if ( ! $location instanceof Location ) { continue; } ?>
                        <option value="<?php echo esc_attr( (string) $location->get_id() ); ?>" <?php selected( $location_id, $location->get_id() ); ?>><?php echo esc_html( $location->get_name() ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Date', 'smooth-booking' ); ?></span>
                <input type="date" name="calendar_date" value="<?php echo esc_attr( $date_value ); ?>" data-calendar-input />
            </label>
            <label>
                <span><?php esc_html_e( 'Services', 'smooth-booking' ); ?></span>
                <select id="smooth-booking-calendar-services" class="smooth-booking-select2" name="service_ids[]" multiple data-placeholder="<?php echo esc_attr__( 'Filter services…', 'smooth-booking' ); ?>">
                    <?php foreach ( $services as $service ) : ?>
                        <?php if ( ! $service instanceof Service ) { continue; } ?>
                        <?php $is_selected = in_array( $service->get_id(), $selected_service_ids, true ); ?>
                        <option value="<?php echo esc_attr( (string) $service->get_id() ); ?>" <?php selected( $is_selected ); ?>><?php echo esc_html( $service->get_name() ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ( $all_services_selected && ! empty( $services ) ) : ?>
                    <p class="description"><?php esc_html_e( 'All services are currently visible.', 'smooth-booking' ); ?></p>
                <?php endif; ?>
            </label>
            <label>
                <span><?php esc_html_e( 'Staff members', 'smooth-booking' ); ?></span>
                <select id="smooth-booking-calendar-employees" class="smooth-booking-select2" name="employee_ids[]" multiple data-placeholder="<?php echo esc_attr__( 'Filter staff…', 'smooth-booking' ); ?>">
                    <?php foreach ( $employees as $employee ) : ?>
                        <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                        <?php $is_selected = in_array( $employee->get_id(), $selected_employee_ids, true ); ?>
                        <option value="<?php echo esc_attr( (string) $employee->get_id() ); ?>" <?php selected( $is_selected ); ?>><?php echo esc_html( $employee->get_name() ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Use the quick buttons below to toggle staff columns.', 'smooth-booking' ); ?></p>
            </label>
            <div id="smooth-booking-calendar-picker" class="smooth-booking-calendar-picker" data-initial-date="<?php echo esc_attr( $date_value ); ?>"></div>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'smooth-booking' ); ?></button>
        </form>
        <?php if ( ! empty( $employees ) ) : ?>
            <div class="smooth-booking-calendar-quickfilters" role="group" aria-label="<?php echo esc_attr__( 'Quick staff filters', 'smooth-booking' ); ?>">
                <span class="smooth-booking-calendar-quickfilters__label"><?php esc_html_e( 'Quick staff filters', 'smooth-booking' ); ?></span>
                <div class="smooth-booking-calendar-quickfilters__buttons">
                    <?php
                    $all_selected = ! empty( $employees ) && count( $selected_employee_ids ) === count( $employees );
                    ?>
                    <button type="button" class="smooth-booking-calendar-quickfilters__button<?php echo $all_selected ? ' is-active' : ''; ?>" data-employee-toggle="all">
                        <?php esc_html_e( 'All staff', 'smooth-booking' ); ?>
                    </button>
                    <?php foreach ( $employees as $employee ) : ?>
                        <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                        <?php $is_active = in_array( $employee->get_id(), $selected_employee_ids, true ); ?>
                        <button type="button" class="smooth-booking-calendar-quickfilters__button<?php echo $is_active ? ' is-active' : ''; ?>" data-employee-toggle="<?php echo esc_attr( (string) $employee->get_id() ); ?>">
                            <?php echo esc_html( $employee->get_name() ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render main calendar grid.
     *
     * @param string[]                $slots        Time slots.
     * @param Employee[]              $employees    Employees list.
     * @param array<int,Appointment[]> $appointments_by_employee Grouped appointments.
     * @param DateTimeImmutable       $open_time    Opening datetime.
     * @param DateTimeImmutable|null  $close_time   Closing datetime.
     * @param int                     $slot_length  Slot length in minutes.
     * @param DateTimeImmutable       $selected_date Current date.
     * @param string                  $timezone     Timezone identifier.
     */
    private function render_calendar_grid( array $slots, array $employees, array $appointments_by_employee, DateTimeImmutable $open_time, ?DateTimeImmutable $close_time, int $slot_length, DateTimeImmutable $selected_date, string $timezone ): void {
        $payload = $this->build_calendar_payload( $slots, $employees, $appointments_by_employee, $open_time, $close_time, $slot_length, $selected_date, $timezone );

        if ( ! empty( $payload ) ) {
            $encoded_payload = wp_json_encode( $payload );

            wp_add_inline_script(
                'smooth-booking-admin-calendar',
                'window.SmoothBookingCalendarData = ' . $encoded_payload . ';',
                'before'
            );

            wp_add_inline_script(
                'smooth-booking-admin-calendar',
                'window.SmoothBookingCalendar = window.SmoothBookingCalendar || {}; window.SmoothBookingCalendar.data = window.SmoothBookingCalendarData;',
                'after'
            );
        }

        $timezone_label = $timezone ? $timezone : 'UTC';
        ?>
        <div class="smooth-booking-calendar-meta">
            <span class="smooth-booking-calendar-meta__item"><?php echo esc_html( sprintf( __( 'Date: %s', 'smooth-booking' ), $selected_date->format( 'Y-m-d' ) ) ); ?></span>
            <span class="smooth-booking-calendar-meta__item"><?php echo esc_html( sprintf( __( 'Timezone: %s', 'smooth-booking' ), $timezone_label ) ); ?></span>
        </div>
        <div id="smooth-booking-calendar-view" class="smooth-booking-calendar-grid"></div>
        <?php
    }

    /**
     * Build structured data for the EventCalendar integration.
     *
     * @param string[]                 $slots                    Time slots labels.
     * @param Employee[]               $employees                Visible employees.
     * @param array<int,Appointment[]> $appointments_by_employee Appointments grouped by employee.
     */
    private function build_calendar_payload( array $slots, array $employees, array $appointments_by_employee, DateTimeImmutable $open_time, ?DateTimeImmutable $close_time, int $slot_length, DateTimeImmutable $selected_date, string $timezone ): array {
        $slot_count = max( 1, count( $slots ) );
        $resources  = [];

        foreach ( $employees as $employee ) {
            if ( ! $employee instanceof Employee ) {
                continue;
            }

            $resources[] = [
                'id'    => $employee->get_id(),
                'title' => $employee->get_name(),
            ];
        }

        $events          = [];
        $delete_endpoint = admin_url( 'admin-post.php' );
        $current_url     = $this->get_current_url();

        foreach ( $appointments_by_employee as $employee_id => $appointments ) {
            foreach ( $appointments as $appointment ) {
                if ( ! $appointment instanceof Appointment ) {
                    continue;
                }

                $start        = $appointment->get_scheduled_start();
                $end          = $appointment->get_scheduled_end();
                $start_index  = $this->calculate_slot_index( $open_time, $start, $slot_length, $slot_count );
                $span         = $this->calculate_slot_span( $appointment, $slot_length, $slot_count, $start_index );
                $service_name = $appointment->get_service_name() ?? __( 'Service', 'smooth-booking' );
                $service_color = $appointment->get_service_color() ?: '#2271b1';
                $customer_name = $this->format_customer_name( $appointment );

                $events[] = [
                    'id'         => $appointment->get_id(),
                    'resourceId' => $employee_id,
                    'start'      => $start->format( DATE_ATOM ),
                    'end'        => $end->format( DATE_ATOM ),
                    'startIndex' => $start_index,
                    'span'       => $span,
                    'service'    => $service_name,
                    'color'      => $service_color,
                    'timeLabel'  => sprintf( '%s - %s', $start->format( 'H:i' ), $end->format( 'H:i' ) ),
                    'customer'   => [
                        'name'  => $customer_name,
                        'phone' => $appointment->get_customer_phone(),
                        'email' => $appointment->get_customer_email(),
                    ],
                    'status'     => ucfirst( $appointment->get_status() ),
                    'statusSlug' => $appointment->get_status(),
                    'editUrl'    => $this->get_edit_link( $appointment->get_id() ),
                    'editLabel'  => __( 'Edit', 'smooth-booking' ),
                    'delete'     => [
                        'endpoint'      => $delete_endpoint,
                        'action'        => 'smooth_booking_delete_appointment',
                        'nonce'         => wp_create_nonce( 'smooth_booking_delete_appointment' ),
                        'appointmentId' => $appointment->get_id(),
                        'referer'       => $current_url,
                        'label'         => __( 'Delete', 'smooth-booking' ),
                    ],
                ];
            }
        }

        return [
            'date'       => $selected_date->format( 'Y-m-d' ),
            'timezone'   => $timezone ?: 'UTC',
            'slotLength' => $slot_length,
            'openTime'   => $open_time->format( 'H:i' ),
            'closeTime'  => $close_time instanceof DateTimeImmutable ? $close_time->format( 'H:i' ) : '',
            'slots'      => array_values( array_map( 'strval', $slots ) ),
            'resources'  => $resources,
            'events'     => $events,
            'labels'     => [
                'slotAria' => __( 'Create appointment at %1$s for %2$s', 'smooth-booking' ),
            ],
        ];
    }

    /**
     * Render modal markup for creating appointments.
     *
     * @param Employee[] $employees Employees list.
     * @param Service[]  $services  Services list.
     * @param Customer[] $customers Customers list.
     */
    private function render_modal( array $employees, array $services, array $customers ): void {
        ?>
        <div class="smooth-booking-modal" id="smooth-booking-calendar-modal" hidden>
            <div class="smooth-booking-modal__overlay" data-calendar-close></div>
            <div class="smooth-booking-modal__dialog">
                <button type="button" class="smooth-booking-modal__close" data-calendar-close aria-label="<?php esc_attr_e( 'Close', 'smooth-booking' ); ?>">&times;</button>
                <?php $this->render_appointment_form( null, $employees, $services, $customers ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * When no locations exist display placeholder.
     */
    private function render_empty_state(): void {
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <div class="notice notice-warning"><p><?php esc_html_e( 'Add a location before using the calendar view.', 'smooth-booking' ); ?></p></div>
            </div>
        </div>
        <?php
    }

    /**
     * Determine current location id.
     *
     * @param Location[] $locations Locations array.
     */
    private function determine_location_id( array $locations ): int {
        $requested = isset( $_GET['location_id'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_GET['location_id'] ) ) ) : 0;

        if ( $requested > 0 ) {
            return $requested;
        }

        $first = reset( $locations );
        return $first instanceof Location ? $first->get_id() : 0;
    }

    /**
     * Determine selected date from query.
     */
    private function determine_date(): DateTimeImmutable {
        $timezone = wp_timezone();
        $requested = isset( $_GET['calendar_date'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['calendar_date'] ) ) : '';

        if ( $requested ) {
            $parsed = DateTimeImmutable::createFromFormat( 'Y-m-d', $requested, $timezone );
            if ( $parsed instanceof DateTimeImmutable ) {
                return $parsed;
            }
        }

        return new DateTimeImmutable( 'today', $timezone instanceof DateTimeZone ? $timezone : null );
    }

    /**
     * Locate a specific location object.
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
     * Group appointments by employee id.
     *
     * @param Appointment[] $appointments Appointments list.
     *
     * @return array<int, Appointment[]>
     */
    private function group_appointments_by_employee( array $appointments ): array {
        $grouped = [];

        foreach ( $appointments as $appointment ) {
            if ( ! $appointment instanceof Appointment ) {
                continue;
            }

            $employee_id = $appointment->get_employee_id();
            if ( null === $employee_id ) {
                continue;
            }

            if ( ! isset( $grouped[ $employee_id ] ) ) {
                $grouped[ $employee_id ] = [];
            }

            $grouped[ $employee_id ][] = $appointment;
        }

        return $grouped;
    }

    /**
     * Calculate slot index for positioning.
     */
    private function calculate_slot_index( DateTimeImmutable $open, DateTimeImmutable $start, int $slot_length, int $slot_count ): int {
        $diff_minutes = (int) floor( max( 0, $start->getTimestamp() - $open->getTimestamp() ) / 60 );
        $index = (int) floor( $diff_minutes / max( 1, $slot_length ) );

        return min( max( 0, $index ), $slot_count - 1 );
    }

    /**
     * Calculate how many slots an appointment should span.
     */
    private function calculate_slot_span( Appointment $appointment, int $slot_length, int $slot_count, int $start_index ): int {
        $duration = max( $slot_length, $appointment->get_duration_minutes() );
        $span = (int) ceil( $duration / max( 1, $slot_length ) );
        $max_span = $slot_count - $start_index;

        return max( 1, min( $span, $max_span ) );
    }

    /**
     * Format display name for customers.
     */
    private function format_customer_name( Appointment $appointment ): string {
        $first = $appointment->get_customer_first_name();
        $last  = $appointment->get_customer_last_name();

        if ( $first || $last ) {
            return trim( $first . ' ' . $last );
        }

        return $appointment->get_customer_account_name() ?: __( 'Guest', 'smooth-booking' );
    }

    /**
     * Retrieve requested service identifiers from the query string.
     *
     * @return int[]
     */
    private function get_requested_service_ids(): array {
        if ( empty( $_GET['service_ids'] ) || ! is_array( $_GET['service_ids'] ) ) {
            return [];
        }

        $raw = wp_unslash( $_GET['service_ids'] );

        return $this->sanitize_id_list( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Retrieve requested employee identifiers from the query string.
     *
     * @return int[]
     */
    private function get_requested_employee_ids(): array {
        if ( empty( $_GET['employee_ids'] ) || ! is_array( $_GET['employee_ids'] ) ) {
            return [];
        }

        $raw = wp_unslash( $_GET['employee_ids'] );

        return $this->sanitize_id_list( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Sanitize an array of identifiers.
     *
     * @param array<int|string, mixed> $values Raw values.
     *
     * @return int[]
     */
    private function sanitize_id_list( array $values ): array {
        $sanitized = array_map(
            static function ( $value ): int {
                return absint( sanitize_text_field( (string) $value ) );
            },
            $values
        );

        return array_values(
            array_filter(
                $sanitized,
                static function ( int $id ): bool {
                    return $id > 0;
                }
            )
        );
    }

    /**
     * Determine which services should be visible.
     *
     * @param Service[] $services Service collection.
     *
     * @return int[]
     */
    private function determine_selected_service_ids( array $services ): array {
        $requested = $this->get_requested_service_ids();
        $available = $this->extract_ids_from_services( $services );

        if ( empty( $available ) ) {
            return [];
        }

        $selected = array_values(
            array_filter(
                $requested,
                static function ( int $id ) use ( $available ): bool {
                    return in_array( $id, $available, true );
                }
            )
        );

        if ( empty( $selected ) ) {
            return $available;
        }

        return $selected;
    }

    /**
     * Extract identifiers from service collection.
     *
     * @param Service[] $services Services list.
     *
     * @return int[]
     */
    private function extract_ids_from_services( array $services ): array {
        return array_values(
            array_map(
                static function ( Service $service ): int {
                    return $service->get_id();
                },
                array_filter(
                    $services,
                    static function ( $service ): bool {
                        return $service instanceof Service;
                    }
                )
            )
        );
    }

    /**
     * Determine which employees should be visible.
     *
     * @param Employee[] $employees            Available employees.
     * @param int[]      $requested_employee_ids Requested identifiers.
     *
     * @return int[]
     */
    private function determine_selected_employee_ids( array $employees, array $requested_employee_ids ): array {
        $available = $this->extract_ids_from_employees( $employees );

        if ( empty( $available ) ) {
            return [];
        }

        $selected = array_values(
            array_filter(
                $requested_employee_ids,
                static function ( int $id ) use ( $available ): bool {
                    return in_array( $id, $available, true );
                }
            )
        );

        if ( empty( $selected ) ) {
            return $available;
        }

        return $selected;
    }

    /**
     * Extract identifiers from employee collection.
     *
     * @param Employee[] $employees Employees list.
     *
     * @return int[]
     */
    private function extract_ids_from_employees( array $employees ): array {
        return array_values(
            array_map(
                static function ( Employee $employee ): int {
                    return $employee->get_id();
                },
                array_filter(
                    $employees,
                    static function ( $employee ): bool {
                        return $employee instanceof Employee;
                    }
                )
            )
        );
    }

    /**
     * Filter employees for the grid display.
     *
     * @param Employee[] $employees         All employees.
     * @param int[]      $selected_employee_ids Selected identifiers.
     *
     * @return Employee[]
     */
    private function filter_employees_for_display( array $employees, array $selected_employee_ids ): array {
        if ( empty( $selected_employee_ids ) ) {
            return $employees;
        }

        return array_values(
            array_filter(
                $employees,
                static function ( $employee ) use ( $selected_employee_ids ): bool {
                    return $employee instanceof Employee && in_array( $employee->get_id(), $selected_employee_ids, true );
                }
            )
        );
    }

    /**
     * Filter appointments based on current selections.
     *
     * @param Appointment[] $appointments          Appointment list.
     * @param int[]         $selected_service_ids  Selected service identifiers.
     * @param int[]         $selected_employee_ids Selected employee identifiers.
     *
     * @return Appointment[]
     */
    private function filter_appointments( array $appointments, array $selected_service_ids, array $selected_employee_ids ): array {
        return array_values(
            array_filter(
                $appointments,
                static function ( $appointment ) use ( $selected_service_ids, $selected_employee_ids ): bool {
                    if ( ! $appointment instanceof Appointment ) {
                        return false;
                    }

                    if ( ! empty( $selected_service_ids ) ) {
                        $service_id = $appointment->get_service_id();
                        if ( null === $service_id || ! in_array( $service_id, $selected_service_ids, true ) ) {
                            return false;
                        }
                    }

                    if ( ! empty( $selected_employee_ids ) ) {
                        $employee_id = $appointment->get_employee_id();
                        if ( null === $employee_id || ! in_array( $employee_id, $selected_employee_ids, true ) ) {
                            return false;
                        }
                    }

                    return true;
                }
            )
        );
    }

    /**
     * Generate edit link for appointments.
     */
    private function get_edit_link( int $appointment_id ): string {
        return add_query_arg(
            [
                'page'           => AppointmentsPage::MENU_SLUG,
                'action'         => 'edit',
                'appointment_id' => $appointment_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    /**
     * Current URL helper for referer fields.
     */
    protected function get_current_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';

        return esc_url_raw( $scheme . $host . $uri );
    }
}
