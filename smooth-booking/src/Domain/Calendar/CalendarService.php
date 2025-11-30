<?php
/**
 * Calendar aggregation service for admin schedule view.
 *
 * @package SmoothBooking\Domain\Calendar
 */

namespace SmoothBooking\Domain\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use SmoothBooking\Support\CalendarEventFormatterTrait;
use WP_Error;

use function absint;
use function array_filter;
use function array_map;
use function in_array;
use function count;
use function is_wp_error;
use function sprintf;
use function wp_timezone;

/**
 * Provides structured calendar data for the admin interface.
 */
class CalendarService {
    use CalendarEventFormatterTrait;

    private AppointmentService $appointments;

    private EmployeeService $employees;

    private BusinessHoursService $business_hours;

    private LocationService $locations;

    private GeneralSettings $settings;

    private Logger $logger;

    public function __construct(
        AppointmentService $appointments,
        EmployeeService $employees,
        BusinessHoursService $business_hours,
        LocationService $locations,
        GeneralSettings $settings,
        Logger $logger
    ) {
        $this->appointments   = $appointments;
        $this->employees      = $employees;
        $this->business_hours = $business_hours;
        $this->locations      = $locations;
        $this->settings       = $settings;
        $this->logger         = $logger;
    }

    /**
     * Build EventCalendar resources payload for provided employees.
     *
     * @param Employee[] $employees Employees assigned to the day.
     *
     * @return array<int,array<string,mixed>>
     */
    public function build_resources_payload( array $employees ): array {
        $resources = [];

        foreach ( $employees as $employee ) {
            if ( ! $employee instanceof Employee ) {
                continue;
            }

            $services = array_map(
                static function ( array $service ): int {
                    return isset( $service['service_id'] ) ? absint( $service['service_id'] ) : 0;
                },
                $employee->get_services()
            );

            $resources[] = [
                'id'         => $employee->get_id(),
                'title'      => $employee->get_name(),
                'serviceIds' => array_values( array_filter( $services ) ),
            ];
        }

        return $resources;
    }

    /**
     * Build a daily schedule for the provided location and date.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_daily_schedule( int $location_id, DateTimeImmutable $date ) {
        $this->logger->info(
            sprintf(
                'Requesting schedule for location #%d on %s',
                $location_id,
                $date->format( 'Y-m-d' )
            )
        );

        $location_result = $this->locations->get_location( $location_id );

        if ( is_wp_error( $location_result ) ) {
            $this->logger->error(
                sprintf(
                    'Location lookup failed for #%d: %s',
                    $location_id,
                    $location_result->get_error_message()
                )
            );
            return $location_result;
        }

        $location = $location_result;
        $timezone = $this->resolve_timezone( $location );
        $date     = $date->setTimezone( $timezone );
        $employees = $this->filter_employees_for_location( $location_id );

        $this->logger->info(
            sprintf(
                'Employees available for location #%d: %d',
                $location_id,
                count( $employees )
            )
        );

        $hours_result = $this->business_hours->get_location_hours( $location_id );

        if ( is_wp_error( $hours_result ) ) {
            $this->logger->error(
                sprintf(
                    'Business hours lookup failed for location #%d: %s',
                    $location_id,
                    $hours_result->get_error_message()
                )
            );
            return $hours_result;
        }

        $day_index   = (int) $date->format( 'N' );
        $day_hours   = $hours_result[ $day_index ] ?? [ 'open' => '', 'close' => '', 'is_closed' => true ];
        $slot_length = $this->settings->get_time_slot_length();

        $open_string = $day_hours['open'] ?: '08:00';
        $close_string = $day_hours['close'] ?: '18:00';

        $open_datetime  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date->format( 'Y-m-d' ) . ' ' . $open_string, $timezone ) ?: $date->setTime( 8, 0 );
        $close_datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date->format( 'Y-m-d' ) . ' ' . $close_string, $timezone ) ?: $date->setTime( 18, 0 );

        if ( $close_datetime <= $open_datetime ) {
            $close_datetime = $open_datetime->add( new DateInterval( 'PT8H' ) );
        }

        $slots = $this->settings->get_slots_for_range( $open_datetime, $close_datetime );

        $day_start = $date->setTime( 0, 0 );
        $day_end   = $date->setTime( 23, 59, 59 );
        $window_start = $day_start->sub( new DateInterval( 'P7D' ) );
        $window_end   = $day_end->add( new DateInterval( 'P7D' ) );

        $appointments        = [];
        $window_appointments = [];

        $site_timezone = wp_timezone();
        if ( ! $site_timezone instanceof DateTimeZone ) {
            $site_timezone = new DateTimeZone( 'UTC' );
        }
        if ( ! empty( $employees ) ) {
            $employee_ids = array_map(
                static function ( Employee $employee ): int {
                    return $employee->get_id();
                },
                $employees
            );

            $storage_window_start = $window_start->setTimezone( $site_timezone );
            $storage_window_end   = $window_end->setTimezone( $site_timezone );

            $window_appointments = $this->appointments->get_appointments_for_employees( $employee_ids, $storage_window_start, $storage_window_end );
            $appointments        = $this->filter_daily_appointments( $window_appointments, $day_start, $day_end );
        }

        $this->logger->info(
            sprintf(
                'Resolved schedule window for location #%d on %s (open: %s, close: %s, slots: %d, appointments: %d, closed flag: %s)',
                $location_id,
                $date->format( 'Y-m-d' ),
                $open_datetime->format( 'H:i' ),
                $close_datetime->format( 'H:i' ),
                count( $slots ),
                count( $appointments ),
                ! empty( $day_hours['is_closed'] ) ? 'yes' : 'no'
            )
        );

        return [
            'location'     => $location,
            'employees'    => $employees,
            'slots'        => $slots,
            'slot_length'  => $slot_length,
            'appointments' => $appointments,
            'window_appointments' => $window_appointments,
            'window_start' => $window_start,
            'window_end'   => $window_end,
            'is_closed'    => ! empty( $day_hours['is_closed'] ),
            'open'         => $open_datetime,
            'close'        => $close_datetime,
            'date'         => $date,
        ];
    }

    /**
     * Determine the view window with padding before open and after close.
     *
     * @param DateTimeImmutable $open_time Opening time for the location.
     * @param DateTimeImmutable $close_time Closing time for the location.
     *
     * @return array<string,string>
     */
    public function build_view_window( DateTimeImmutable $open_time, DateTimeImmutable $close_time ): array {
        $day_start = $open_time->setTime( 0, 0 );
        $day_end   = $open_time->setTime( 23, 59, 59 );

        $slot_min = $open_time->sub( new DateInterval( 'PT2H' ) );
        if ( $slot_min < $day_start ) {
            $slot_min = $day_start;
        }

        $slot_max = $close_time->add( new DateInterval( 'PT2H' ) );
        if ( $slot_max > $day_end ) {
            $slot_max = $day_end;
        }

        if ( $slot_max < $slot_min ) {
            $slot_max = $slot_min->add( new DateInterval( 'PT16H' ) );
            if ( $slot_max > $day_end ) {
                $slot_max = $day_end;
            }
        }

        return [
            'slotMinTime' => $slot_min->format( 'H:i:s' ),
            'slotMaxTime' => $slot_max->format( 'H:i:s' ),
            'scrollTime'  => $open_time->format( 'H:i:s' ),
        ];
    }

    /**
     * Convert the slot length to a HH:MM:SS format string.
     */
    public function format_slot_duration( int $slot_length ): string {
        $minutes = max( 1, $slot_length );
        $hours   = intdiv( $minutes, 60 );
        $mins    = $minutes % 60;

        return str_pad( (string) $hours, 2, '0', STR_PAD_LEFT ) . ':' . str_pad( (string) $mins, 2, '0', STR_PAD_LEFT ) . ':00';
    }

    /**
     * Restrict appointments to the selected day.
     *
     * @param Appointment[]     $appointments Full window appointments.
     * @param DateTimeImmutable $day_start    Day start boundary.
     * @param DateTimeImmutable $day_end      Day end boundary.
     *
     * @return Appointment[]
     */
    private function filter_daily_appointments( array $appointments, DateTimeImmutable $day_start, DateTimeImmutable $day_end ): array {
        return array_values(
            array_filter(
                $appointments,
                static function ( $appointment ) use ( $day_start, $day_end ): bool {
                    if ( ! $appointment instanceof Appointment ) {
                        return false;
                    }

                    $start = $appointment->get_scheduled_start()->setTimezone( $day_start->getTimezone() );

                    return $start->getTimestamp() >= $day_start->getTimestamp() && $start->getTimestamp() <= $day_end->getTimestamp();
                }
            )
        );
    }

    /**
     * Determine the timezone for a location, falling back to the site default when invalid.
     */
    private function resolve_timezone( Location $location ): DateTimeZone {
        $location_timezone = $location->get_timezone();

        if ( $location_timezone ) {
            try {
                return new DateTimeZone( $location_timezone );
            } catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                $this->logger->warning(
                    sprintf(
                        'Invalid timezone "%s" for location #%d, falling back to site timezone.',
                        $location_timezone,
                        $location->get_id()
                    )
                );
            }
        }

        return wp_timezone();
    }

    /**
     * Filter employees assigned to the provided location.
     *
     * @return Employee[]
     */
    private function filter_employees_for_location( int $location_id ): array {
        $location_id = absint( $location_id );

        if ( $location_id <= 0 ) {
            return [];
        }

        $employees = $this->employees->list_employees();

        return array_values(
            array_filter(
                $employees,
                static function ( Employee $employee ) use ( $location_id ): bool {
                    return in_array( $location_id, $employee->get_location_ids(), true );
                }
            )
        );
    }
}
