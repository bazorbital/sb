<?php
/**
 * Appointment business logic layer.
 *
 * @package SmoothBooking\Domain\Appointments
 */

namespace SmoothBooking\Domain\Appointments;

use DateTimeImmutable;
use DateTimeZone;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

use function __;
use function absint;
use function apply_filters;
use function in_array;
use function get_option;
use function is_email;
use function is_wp_error;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sprintf;
use function wp_parse_args;
use function wp_timezone;

/**
 * Provides validation and orchestration for appointment workflows.
 */
class AppointmentService {
    /**
     * Repository implementation.
     */
    private AppointmentRepositoryInterface $repository;

    /**
     * Employee service dependency.
     */
    private EmployeeService $employee_service;

    /**
     * Service service dependency.
     */
    private ServiceService $service_service;

    /**
     * Customer service dependency.
     */
    private CustomerService $customer_service;

    /**
     * Logger instance.
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( AppointmentRepositoryInterface $repository, EmployeeService $employee_service, ServiceService $service_service, CustomerService $customer_service, Logger $logger ) {
        $this->repository        = $repository;
        $this->employee_service  = $employee_service;
        $this->service_service   = $service_service;
        $this->customer_service  = $customer_service;
        $this->logger            = $logger;
    }

    /**
     * Paginate appointments with filters.
     *
     * @param array<string, mixed> $args Arguments.
     *
     * @return array<string, mixed>
     */
    public function paginate_appointments( array $args = [] ): array {
        $defaults = [
            'paged'            => 1,
            'per_page'         => 20,
            'orderby'          => 'scheduled_start',
            'order'            => 'DESC',
            'include_deleted'  => false,
            'only_deleted'     => false,
            'appointment_id'   => null,
            'appointment_from' => null,
            'appointment_to'   => null,
            'created_from'     => null,
            'created_to'       => null,
            'customer_search'  => null,
            'employee_id'      => null,
            'service_id'       => null,
            'status'           => null,
        ];

        $args = wp_parse_args( $args, $defaults );

        $result = $this->repository->paginate( $args );

        /**
         * Filter the appointment pagination result.
         *
         * @hook smooth_booking_appointments_paginated
         * @since 0.7.0
         *
         * @param array<string, mixed> $result Result payload.
         * @param array<string, mixed> $args   Query args.
         */
        return apply_filters(
            'smooth_booking_appointments_paginated',
            [
                'appointments' => $result['appointments'],
                'total'        => (int) $result['total'],
                'per_page'     => (int) $args['per_page'],
                'paged'        => (int) $args['paged'],
            ],
            $args
        );
    }

    /**
     * Retrieve an appointment by id.
     *
     * @return Appointment|WP_Error
     */
    public function get_appointment( int $appointment_id ) {
        $appointment = $this->repository->find_with_deleted( $appointment_id );

        if ( null === $appointment ) {
            return new WP_Error(
                'smooth_booking_appointment_not_found',
                __( 'The requested appointment could not be found.', 'smooth-booking' )
            );
        }

        return $appointment;
    }

    /**
     * Create a new appointment.
     *
     * @param array<string, mixed> $data Submitted payload.
     *
     * @return Appointment|WP_Error
     */
    public function create_appointment( array $data ) {
        $validated = $this->validate_payload( $data );

        if ( is_wp_error( $validated ) ) {
            $this->logger->error( sprintf( 'Appointment validation failed: %s', $validated->get_error_message() ) );
            return $validated;
        }

        $result = $this->repository->create( $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed creating appointment: %s', $result->get_error_message() ) );
            return $result;
        }

        if ( $result instanceof Appointment ) {
            $this->logger->info(
                sprintf(
                    'Appointment #%d created for provider #%d, service #%d on %s to %s.',
                    $result->get_id(),
                    $validated['provider_id'],
                    $validated['service_id'],
                    $validated['scheduled_start']->format( 'Y-m-d H:i' ),
                    $validated['scheduled_end']->format( 'Y-m-d H:i' )
                )
            );
        }

        return $result;
    }

    /**
     * Update an existing appointment.
     *
     * @param int                   $appointment_id Appointment identifier.
     * @param array<string, mixed> $data            Payload.
     *
     * @return Appointment|WP_Error
     */
    public function update_appointment( int $appointment_id, array $data ) {
        $existing = $this->repository->find_with_deleted( $appointment_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_appointment_not_found',
                __( 'The requested appointment could not be found.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_payload( $data, $existing );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $this->logger->info(
            sprintf(
                'Rescheduling appointment #%1$d from %2$s–%3$s (provider #%4$s) to %5$s–%6$s for provider #%7$d, service #%8$d.',
                $appointment_id,
                $existing->get_scheduled_start()->format( 'Y-m-d H:i' ),
                $existing->get_scheduled_end()->format( 'Y-m-d H:i' ),
                null === $existing->get_employee_id() ? 'n/a' : (string) $existing->get_employee_id(),
                $validated['scheduled_start']->format( 'Y-m-d H:i' ),
                $validated['scheduled_end']->format( 'Y-m-d H:i' ),
                $validated['provider_id'],
                $validated['service_id']
            )
        );

        $result = $this->repository->update( $appointment_id, $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed updating appointment #%d: %s', $appointment_id, $result->get_error_message() ) );
            return $result;
        }

        return $result;
    }

    /**
     * Soft delete appointment.
     */
    public function delete_appointment( int $appointment_id ) {
        $existing = $this->repository->find_with_deleted( $appointment_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_appointment_not_found',
                __( 'The requested appointment could not be found.', 'smooth-booking' )
            );
        }

        $deleted = $this->repository->soft_delete( $appointment_id );

        if ( ! $deleted ) {
            return new WP_Error(
                'smooth_booking_appointment_delete_failed',
                __( 'Unable to delete appointment. Please try again.', 'smooth-booking' )
            );
        }

        return true;
    }

    /**
     * Restore soft deleted appointment.
     */
    public function restore_appointment( int $appointment_id ) {
        $restored = $this->repository->restore( $appointment_id );

        if ( ! $restored ) {
            return new WP_Error(
                'smooth_booking_appointment_restore_failed',
                __( 'Unable to restore appointment.', 'smooth-booking' )
            );
        }

        return true;
    }

    /**
     * Retrieve appointments for a set of employees within a range.
     *
     * @param int[]             $employee_ids Employee identifiers.
     * @param DateTimeImmutable $from         Start datetime.
     * @param DateTimeImmutable $to           End datetime.
     *
     * @return Appointment[]
     */
    public function get_appointments_for_employees( array $employee_ids, DateTimeImmutable $from, DateTimeImmutable $to ): array {
        if ( empty( $employee_ids ) ) {
            return [];
        }

        return $this->repository->get_for_employees_in_range(
            $employee_ids,
            $from->format( 'Y-m-d H:i:s' ),
            $to->format( 'Y-m-d H:i:s' )
        );
    }

    /**
     * Validate incoming payload and normalize data for persistence.
     *
     * @param array<string, mixed> $data     Submitted data.
     * @param Appointment|null     $existing Existing appointment when updating.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validate_payload( array $data, ?Appointment $existing = null ) {
        $provider_id  = isset( $data['provider_id'] ) ? absint( $data['provider_id'] ) : ( $existing ? ( $existing->get_employee_id() ?? 0 ) : 0 );
        $service_id   = isset( $data['service_id'] ) ? absint( $data['service_id'] ) : ( $existing ? ( $existing->get_service_id() ?? 0 ) : 0 );
        $customer_id  = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : ( $existing ? ( $existing->get_customer_id() ?? 0 ) : 0 );
        $status       = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : ( $existing ? $existing->get_status() : 'pending' );
        $payment      = isset( $data['payment_status'] ) ? sanitize_key( $data['payment_status'] ) : ( $existing ? ( $existing->get_payment_status() ?? '' ) : '' );
        $notes        = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : ( $existing ? ( $existing->get_notes() ?? '' ) : '' );
        $internal     = isset( $data['internal_note'] ) ? sanitize_textarea_field( $data['internal_note'] ) : ( $existing ? ( $existing->get_internal_note() ?? '' ) : '' );
        $notify       = isset( $data['send_notifications'] ) ? (bool) $data['send_notifications'] : ( $existing ? $existing->should_notify() : false );
        $repeat       = isset( $data['is_recurring'] ) ? (bool) $data['is_recurring'] : ( $existing ? $existing->is_recurring() : false );
        $total_amount = isset( $data['total_amount'] ) ? (float) $data['total_amount'] : ( $existing && null !== $existing->get_total_amount() ? (float) $existing->get_total_amount() : null );
        $currency     = isset( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : ( $existing ? $existing->get_currency() : ( get_option( 'woocommerce_currency' ) ?: 'HUF' ) );

        if ( $provider_id <= 0 ) {
            return new WP_Error( 'smooth_booking_invalid_provider', __( 'A provider must be selected.', 'smooth-booking' ) );
        }

        if ( $service_id <= 0 ) {
            return new WP_Error( 'smooth_booking_invalid_service', __( 'A service must be selected.', 'smooth-booking' ) );
        }

        $provider = $this->employee_service->get_employee( $provider_id );

        if ( is_wp_error( $provider ) ) {
            return new WP_Error( 'smooth_booking_invalid_provider', $provider->get_error_message() );
        }

        $service = $this->service_service->get_service( $service_id );

        if ( is_wp_error( $service ) ) {
            return new WP_Error( 'smooth_booking_invalid_service', $service->get_error_message() );
        }

        $appointment_date  = isset( $data['appointment_date'] ) ? sanitize_text_field( $data['appointment_date'] ) : '';
        $appointment_start = isset( $data['appointment_start'] ) ? sanitize_text_field( $data['appointment_start'] ) : '';
        $appointment_end   = isset( $data['appointment_end'] ) ? sanitize_text_field( $data['appointment_end'] ) : '';

        $times = $this->combine_datetimes( $appointment_date, $appointment_start, $appointment_end );

        if ( is_wp_error( $times ) ) {
            return $times;
        }

        [ $start, $end ] = $times;

        if ( $start->getTimestamp() >= $end->getTimestamp() ) {
            return new WP_Error( 'smooth_booking_invalid_period', __( 'The end time must be after the start time.', 'smooth-booking' ) );
        }

        $customer_email = isset( $data['customer_email'] )
            ? sanitize_email( $data['customer_email'] )
            : ( $existing ? ( $existing->get_customer_email() ?? '' ) : '' );

        if ( $customer_email && ! is_email( $customer_email ) ) {
            return new WP_Error( 'smooth_booking_invalid_email', __( 'The provided customer email address is invalid.', 'smooth-booking' ) );
        }

        if ( $customer_id > 0 ) {
            $customer = $this->customer_service->get_customer( $customer_id );

            if ( is_wp_error( $customer ) ) {
                return new WP_Error( 'smooth_booking_invalid_customer', $customer->get_error_message() );
            }
        }

        $allowed_statuses   = [ 'pending', 'confirmed', 'completed', 'canceled' ];
        $allowed_payments   = [ 'pending', 'authorized', 'paid', 'refunded', 'failed', 'canceled' ];
        $normalized_status  = in_array( $status, $allowed_statuses, true ) ? $status : 'pending';
        $normalized_payment = in_array( $payment, $allowed_payments, true ) ? $payment : null;

        $payload = [
            'provider_id'     => $provider_id,
            'service_id'      => $service_id,
            'customer_id'     => $customer_id ?: null,
            'customer_email'  => $customer_email ?: null,
            'customer_phone'  => isset( $data['customer_phone'] )
                ? sanitize_text_field( $data['customer_phone'] )
                : ( $existing ? ( $existing->get_customer_phone() ?? null ) : null ),
            'status'          => $normalized_status,
            'payment_status'  => $normalized_payment,
            'notes'           => $notes ?: null,
            'internal_note'   => $internal ?: null,
            'should_notify'   => $notify,
            'is_recurring'    => $repeat,
            'scheduled_start' => $start,
            'scheduled_end'   => $end,
            'total_amount'    => $total_amount,
            'currency'        => $currency ?: 'HUF',
        ];

        /**
         * Filter validated appointment payload.
         *
         * @hook smooth_booking_appointment_payload
         * @since 0.7.0
         *
         * @param array<string, mixed> $payload Payload.
         * @param array<string, mixed> $data    Original data.
         * @param Appointment|null     $existing Existing appointment.
         */
        return apply_filters( 'smooth_booking_appointment_payload', $payload, $data, $existing );
    }

    /**
     * Combine date and times into timezone aware datetimes.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}|WP_Error
     */
    private function combine_datetimes( string $date, string $start_time, string $end_time ) {
        if ( ! $date || ! $start_time || ! $end_time ) {
            return new WP_Error( 'smooth_booking_invalid_schedule', __( 'Appointment date and times are required.', 'smooth-booking' ) );
        }

        $timezone = wp_timezone();
        if ( ! $timezone instanceof DateTimeZone ) {
            $timezone = new DateTimeZone( 'UTC' );
        }

        $start_string = sprintf( '%s %s', $date, $start_time );
        $end_string   = sprintf( '%s %s', $date, $end_time );

        $start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $start_string, $timezone ) ?: false;
        $end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $end_string, $timezone ) ?: false;

        if ( false === $start || false === $end ) {
            return new WP_Error( 'smooth_booking_invalid_schedule', __( 'The provided appointment schedule is invalid.', 'smooth-booking' ) );
        }

        return [ $start, $end ];
    }
}
