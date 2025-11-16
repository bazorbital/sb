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
