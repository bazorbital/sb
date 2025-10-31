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
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function is_array;
use function is_ssl;
use function sanitize_text_field;
use function sprintf;
use function plugins_url;
use function wp_nonce_field;
use function wp_unslash;
use function wp_timezone;
use function selected;

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
        $schedule     = $this->calendar->get_daily_schedule( $location_id, $selected_date );
        $error_message = '';
        $employees    = [];
        $slots        = [];
        $appointments_by_employee = [];
        $is_closed    = false;
        $open_time    = $selected_date->setTime( 8, 0 );
        $slot_length  = $this->general_settings->get_time_slot_length();

        if ( is_wp_error( $schedule ) ) {
            $error_message = $schedule->get_error_message();
        } else {
            $employees   = $schedule['employees'] ?? [];
            $slots       = $schedule['slots'] ?? [];
            $appointments = $schedule['appointments'] ?? [];
            $appointments_by_employee = $this->group_appointments_by_employee( $appointments );
            $is_closed = ! empty( $schedule['is_closed'] );
            if ( isset( $schedule['open'] ) && $schedule['open'] instanceof DateTimeImmutable ) {
                $open_time = $schedule['open'];
            }
            if ( isset( $schedule['slot_length'] ) ) {
                $slot_length = (int) $schedule['slot_length'];
            }
        }

        $services  = $this->services->list_services();
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
                        <p class="description"><?php esc_html_e( 'Review today's schedule per employee and add new bookings directly from the grid.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <?php $this->render_filters( $locations, $location_id, $selected_date ); ?>

                <?php if ( $error_message ) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?></p></div>
                <?php endif; ?>

                <div class="smooth-booking-calendar-board" data-slot-length="<?php echo esc_attr( (string) $slot_length ); ?>">
                    <?php if ( $is_closed ) : ?>
                        <div class="notice notice-info"><p><?php esc_html_e( 'This location is closed on the selected day.', 'smooth-booking' ); ?></p></div>
                    <?php elseif ( empty( $slots ) || empty( $employees ) ) : ?>
                        <div class="notice notice-warning"><p><?php esc_html_e( 'No working hours or employees available for the selected location.', 'smooth-booking' ); ?></p></div>
                    <?php else : ?>
                        <?php $this->render_calendar_grid( $slots, $employees, $appointments_by_employee, $open_time, $slot_length, $selected_date, $timezone_string ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $this->render_modal( $employees, $services, $customer_items );
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
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/js/admin-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'smooth-booking-vanilla-calendar', 'select2' ],
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
     * Render filters for location and date.
     *
     * @param Location[]         $locations    Available locations.
     * @param int                $location_id  Current location id.
     * @param DateTimeImmutable  $selected_date Selected date.
     */
    private function render_filters( array $locations, int $location_id, DateTimeImmutable $selected_date ): void {
        $timezone = wp_timezone();
        $date_value = $selected_date->setTimezone( $timezone )->format( 'Y-m-d' );
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
            <div id="smooth-booking-calendar-picker" class="smooth-booking-calendar-picker" data-initial-date="<?php echo esc_attr( $date_value ); ?>"></div>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'smooth-booking' ); ?></button>
        </form>
        <?php
    }

    /**
     * Render main calendar grid.
     *
     * @param string[]                $slots        Time slots.
     * @param Employee[]              $employees    Employees list.
     * @param array<int,Appointment[]> $appointments_by_employee Grouped appointments.
     * @param DateTimeImmutable       $open_time    Opening datetime.
     * @param int                     $slot_length  Slot length in minutes.
     * @param DateTimeImmutable       $selected_date Current date.
     * @param string                  $timezone     Timezone identifier.
     */
    private function render_calendar_grid( array $slots, array $employees, array $appointments_by_employee, DateTimeImmutable $open_time, int $slot_length, DateTimeImmutable $selected_date, string $timezone ): void {
        $slot_count = max( 1, count( $slots ) );
        $timezone_label = $timezone ? $timezone : 'UTC';
        ?>
        <div class="smooth-booking-calendar-meta">
            <span class="smooth-booking-calendar-meta__item"><?php echo esc_html( sprintf( __( 'Date: %s', 'smooth-booking' ), $selected_date->format( 'Y-m-d' ) ) ); ?></span>
            <span class="smooth-booking-calendar-meta__item"><?php echo esc_html( sprintf( __( 'Timezone: %s', 'smooth-booking' ), $timezone_label ) ); ?></span>
        </div>
        <div class="smooth-booking-calendar-grid" style="grid-template-columns: <?php echo esc_attr( 'minmax(120px, 160px) repeat(' . count( $employees ) . ', minmax(220px, 1fr))' ); ?>">
            <div class="smooth-booking-calendar-column smooth-booking-calendar-column--times" style="grid-template-rows: <?php echo esc_attr( 'repeat(' . $slot_count . ', var(--sbc-slot-height))' ); ?>">
                <?php foreach ( $slots as $slot ) : ?>
                    <div class="smooth-booking-calendar-time" aria-hidden="true"><?php echo esc_html( $slot ); ?></div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $employees as $employee ) : ?>
                <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                <?php $employee_id = $employee->get_id(); ?>
                <div class="smooth-booking-calendar-column" data-employee-id="<?php echo esc_attr( (string) $employee_id ); ?>">
                    <div class="smooth-booking-calendar-column__header">
                        <span class="smooth-booking-calendar-column__title"><?php echo esc_html( $employee->get_name() ); ?></span>
                    </div>
                    <div class="smooth-booking-calendar-column__body" style="grid-template-rows: <?php echo esc_attr( 'repeat(' . $slot_count . ', var(--sbc-slot-height))' ); ?>">
                        <?php foreach ( $slots as $slot ) : ?>
                            <button type="button" class="smooth-booking-calendar-slot" data-slot="<?php echo esc_attr( $slot ); ?>" data-employee="<?php echo esc_attr( (string) $employee_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Create appointment at %1$s for %2$s', 'smooth-booking' ), $slot, $employee->get_name() ) ); ?>"></button>
                        <?php endforeach; ?>
                        <?php foreach ( $appointments_by_employee[ $employee_id ] ?? [] as $appointment ) : ?>
                            <?php if ( ! $appointment instanceof Appointment ) { continue; } ?>
                            <?php
                            $start_index = $this->calculate_slot_index( $open_time, $appointment->get_scheduled_start(), $slot_length, $slot_count );
                            $span        = $this->calculate_slot_span( $appointment, $slot_length, $slot_count, $start_index );
                            $service_color = $appointment->get_service_color() ?: '#2271b1';
                            ?>
                            <div
                                class="smooth-booking-calendar-appointment"
                                style="grid-row: <?php echo esc_attr( (string) ( $start_index + 1 ) ); ?> / span <?php echo esc_attr( (string) $span ); ?>; border-color: <?php echo esc_attr( $service_color ); ?>;"
                                data-employee="<?php echo esc_attr( (string) $employee_id ); ?>"
                                data-appointment="<?php echo esc_attr( (string) $appointment->get_id() ); ?>"
                            >
                                <span class="smooth-booking-calendar-appointment__service" style="background-color: <?php echo esc_attr( $service_color ); ?>;">
                                    <?php echo esc_html( $appointment->get_service_name() ?? __( 'Service', 'smooth-booking' ) ); ?>
                                </span>
                                <span class="smooth-booking-calendar-appointment__time"><?php echo esc_html( sprintf( '%s - %s', $appointment->get_scheduled_start()->format( 'H:i' ), $appointment->get_scheduled_end()->format( 'H:i' ) ) ); ?></span>
                                <span class="smooth-booking-calendar-appointment__customer"><?php echo esc_html( $this->format_customer_name( $appointment ) ); ?></span>
                                <span class="smooth-booking-calendar-appointment__contact">
                                    <?php if ( $appointment->get_customer_phone() ) : ?>
                                        <span><?php echo esc_html( $appointment->get_customer_phone() ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $appointment->get_customer_email() ) : ?>
                                        <span><?php echo esc_html( $appointment->get_customer_email() ); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="smooth-booking-calendar-appointment__status"><?php echo esc_html( ucfirst( $appointment->get_status() ) ); ?></span>
                                <div class="smooth-booking-calendar-appointment__actions">
                                    <a class="button button-small" href="<?php echo esc_url( $this->get_edit_link( $appointment->get_id() ) ); ?>"><?php esc_html_e( 'Edit', 'smooth-booking' ); ?></a>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-calendar-appointment__delete">
                                        <?php wp_nonce_field( 'smooth_booking_delete_appointment' ); ?>
                                        <input type="hidden" name="action" value="smooth_booking_delete_appointment" />
                                        <input type="hidden" name="appointment_id" value="<?php echo esc_attr( (string) $appointment->get_id() ); ?>" />
                                        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $this->get_current_url() ); ?>" />
                                        <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'smooth-booking' ); ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
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
