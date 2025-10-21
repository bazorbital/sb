<?php
/**
 * Contract for employee category persistence.
 *
 * @package SmoothBooking\Domain\Employees
 */

namespace SmoothBooking\Domain\Employees;

use WP_Error;

/**
 * Defines how employee categories are stored and retrieved.
 */
interface EmployeeCategoryRepositoryInterface {
    /**
     * Retrieve all categories ordered by name.
     *
     * @return EmployeeCategory[]
     */
    public function all(): array;

    /**
     * Find a category by identifier.
     *
     * @return EmployeeCategory|null
     */
    public function find( int $category_id );

    /**
     * Locate a category by its name.
     */
    public function find_by_name( string $name ): ?EmployeeCategory;

    /**
     * Persist a category record.
     *
     * @return EmployeeCategory|WP_Error
     */
    public function create( string $name );

    /**
     * Replace category assignments for an employee.
     *
     * @param int   $employee_id  Employee identifier.
     * @param int[] $category_ids Category identifiers.
     */
    public function sync_employee_categories( int $employee_id, array $category_ids ): bool;

    /**
     * Retrieve categories grouped by employee identifier.
     *
     * @param int[] $employee_ids Employee identifiers.
     *
     * @return array<int, EmployeeCategory[]>
     */
    public function get_categories_for_employees( array $employee_ids ): array;

    /**
     * Retrieve categories assigned to an employee.
     *
     * @return EmployeeCategory[]
     */
    public function get_employee_categories( int $employee_id ): array;
}
