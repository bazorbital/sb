<?php
/**
 * Contract for employee persistence.
 *
 * @package SmoothBooking\Domain\Employees
 */

namespace SmoothBooking\Domain\Employees;

use WP_Error;

/**
 * Defines the repository responsibilities for employees.
 */
interface EmployeeRepositoryInterface {
    /**
     * Retrieve all active employees.
     *
     * @return Employee[]
     */
    public function all(): array;

    /**
     * Locate an employee by identifier.
     *
     * @return Employee|null
     */
    public function find( int $employee_id );

    /**
     * Persist a new employee.
     *
     * @param array<string, mixed> $data Employee data.
     *
     * @return Employee|WP_Error
     */
    public function create( array $data );

    /**
     * Update an existing employee.
     *
     * @param int                   $employee_id Employee identifier.
     * @param array<string, mixed> $data        Data to update.
     *
     * @return Employee|WP_Error
     */
    public function update( int $employee_id, array $data );

    /**
     * Soft delete an employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return true|WP_Error
     */
    public function soft_delete( int $employee_id );
}
