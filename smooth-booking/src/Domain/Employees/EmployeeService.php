<?php
/**
 * Business logic for managing employees.
 *
 * @package SmoothBooking\Domain\Employees
 */

namespace SmoothBooking\Domain\Employees;

use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use function __;
use function apply_filters;
use function is_email;
use function is_wp_error;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Provides validation and orchestration for employee CRUD.
 */
class EmployeeService {
    /**
     * Repository instance.
     */
    private EmployeeRepositoryInterface $repository;

    /**
     * Logger instance.
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( EmployeeRepositoryInterface $repository, Logger $logger ) {
        $this->repository = $repository;
        $this->logger     = $logger;
    }

    /**
     * Retrieve employees sorted alphabetically.
     *
     * @return Employee[]
     */
    public function list_employees(): array {
        $employees = $this->repository->all();

        usort(
            $employees,
            static function ( Employee $left, Employee $right ): int {
                return strcasecmp( $left->get_name(), $right->get_name() );
            }
        );

        /**
         * Filter the employees list before displaying.
         *
         * @hook smooth_booking_employees_list
         * @since 0.2.0
         *
         * @param Employee[] $employees List of employees.
         */
        return apply_filters( 'smooth_booking_employees_list', $employees );
    }

    /**
     * Retrieve a single employee.
     *
     * @return Employee|WP_Error
     */
    public function get_employee( int $employee_id ) {
        $employee = $this->repository->find( $employee_id );

        if ( null === $employee ) {
            return new WP_Error(
                'smooth_booking_employee_not_found',
                __( 'The requested employee could not be found.', 'smooth-booking' )
            );
        }

        return $employee;
    }

    /**
     * Create a new employee entry.
     *
     * @param array<string, mixed> $data Submitted form data.
     *
     * @return Employee|WP_Error
     */
    public function create_employee( array $data ) {
        $validated = $this->validate_employee_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->create( $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed creating employee: %s', $result->get_error_message() ) );
        }

        return $result;
    }

    /**
     * Update an existing employee.
     *
     * @param int                   $employee_id Employee identifier.
     * @param array<string, mixed> $data        Submitted data.
     *
     * @return Employee|WP_Error
     */
    public function update_employee( int $employee_id, array $data ) {
        $exists = $this->repository->find( $employee_id );

        if ( null === $exists ) {
            return new WP_Error(
                'smooth_booking_employee_not_found',
                __( 'The requested employee could not be found.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_employee_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->update( $employee_id, $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed updating employee #%d: %s', $employee_id, $result->get_error_message() ) );
        }

        return $result;
    }

    /**
     * Soft delete an employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return true|WP_Error
     */
    public function delete_employee( int $employee_id ) {
        $exists = $this->repository->find( $employee_id );

        if ( null === $exists ) {
            return new WP_Error(
                'smooth_booking_employee_not_found',
                __( 'The requested employee could not be found.', 'smooth-booking' )
            );
        }

        $result = $this->repository->soft_delete( $employee_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed deleting employee #%d: %s', $employee_id, $result->get_error_message() ) );
        }

        return $result;
    }

    /**
     * Validate and sanitize employee data.
     *
     * @param array<string, mixed> $data Submitted data.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validate_employee_data( array $data ) {
        $name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( (string) $data['name'] ) ) : '';

        if ( '' === $name ) {
            return new WP_Error(
                'smooth_booking_employee_missing_name',
                __( 'Employee name is required.', 'smooth-booking' )
            );
        }

        $email = isset( $data['email'] ) ? sanitize_email( wp_unslash( (string) $data['email'] ) ) : '';

        if ( '' !== $email && ! is_email( $email ) ) {
            return new WP_Error(
                'smooth_booking_employee_invalid_email',
                __( 'Please enter a valid email address.', 'smooth-booking' )
            );
        }

        $phone = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( (string) $data['phone'] ) ) : '';

        $specialization = isset( $data['specialization'] )
            ? sanitize_text_field( wp_unslash( (string) $data['specialization'] ) )
            : '';

        $available_online = isset( $data['available_online'] )
            ? (bool) rest_sanitize_boolean( $data['available_online'] )
            : false;

        return [
            'name'             => $name,
            'email'            => $email ?: null,
            'phone'            => $phone ?: null,
            'specialization'   => $specialization ?: null,
            'available_online' => $available_online ? 1 : 0,
        ];
    }
}
