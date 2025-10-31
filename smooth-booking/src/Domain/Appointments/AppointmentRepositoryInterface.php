<?php
/**
 * Appointment repository contract.
 *
 * @package SmoothBooking\Domain\Appointments
 */

namespace SmoothBooking\Domain\Appointments;

use WP_Error;

/**
 * Defines persistence operations for appointments.
 */
interface AppointmentRepositoryInterface {
    /**
     * Paginate appointment results with filters.
     *
     * @param array<string, mixed> $args Query args.
     *
     * @return array{appointments:Appointment[], total:int}
     */
    public function paginate( array $args ): array;

    /**
     * Find appointment including deleted ones.
     */
    public function find_with_deleted( int $appointment_id ): ?Appointment;

    /**
     * Persist a new appointment.
     *
     * @param array<string, mixed> $data Data payload.
     *
     * @return Appointment|WP_Error
     */
    public function create( array $data );

    /**
     * Update an existing appointment.
     *
     * @param array<string, mixed> $data Data payload.
     *
     * @return Appointment|WP_Error
     */
    public function update( int $appointment_id, array $data );

    /**
     * Soft delete an appointment.
     */
    public function soft_delete( int $appointment_id ): bool;

    /**
     * Restore a previously soft deleted appointment.
     */
    public function restore( int $appointment_id ): bool;

    /**
     * Retrieve appointments for specific employees within a datetime range.
     *
     * @param int[]  $employee_ids Employee identifiers.
     * @param string $from         Range start (Y-m-d H:i:s).
     * @param string $to           Range end (Y-m-d H:i:s).
     *
     * @return Appointment[]
     */
    public function get_for_employees_in_range( array $employee_ids, string $from, string $to ): array;
}
