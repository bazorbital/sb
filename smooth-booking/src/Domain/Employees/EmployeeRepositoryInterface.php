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
     * Retrieve employees.
     *
     * @param bool $include_deleted Include soft deleted employees.
     * @param bool $only_deleted    Fetch only deleted employees.
     *
     * @return Employee[]
     */
    public function all( bool $include_deleted = false, bool $only_deleted = false ): array;

    /**
     * Locate an employee by identifier.
     *
     * @return Employee|null
     */
    public function find( int $employee_id );

    /**
     * Locate an employee regardless of deletion status.
     *
     * @return Employee|null
     */
    public function find_with_deleted( int $employee_id );

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

    /**
     * Restore a previously deleted employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return Employee|WP_Error
     */
    public function restore( int $employee_id );

    /**
     * Retrieve location assignments for an employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return int[]
     */
    public function get_employee_locations( int $employee_id ): array;

    /**
     * Synchronize location assignments for an employee.
     *
     * @param int   $employee_id  Employee identifier.
     * @param int[] $location_ids Location identifiers to assign.
     *
     * @return true|WP_Error
     */
    public function sync_employee_locations( int $employee_id, array $location_ids );

    /**
     * Retrieve service assignments for an employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return array<int, array{service_id:int, order:int, price:float|null}>
     */
    public function get_employee_services( int $employee_id ): array;

    /**
     * Synchronize service assignments for an employee.
     *
     * @param int   $employee_id Employee identifier.
     * @param array<int, array{service_id:int, order:int, price:float|null}> $services Services to assign.
     *
     * @return true|WP_Error
     */
    public function sync_employee_services( int $employee_id, array $services );

    /**
     * Retrieve stored working schedule for an employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>
     */
    public function get_employee_schedule( int $employee_id ): array;

    /**
     * Persist working schedule for an employee.
     *
     * @param int   $employee_id Employee identifier.
     * @param array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}> $schedule Schedule to store.
     *
     * @return true|WP_Error
     */
    public function save_employee_schedule( int $employee_id, array $schedule );
}
