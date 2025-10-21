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
use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function is_email;
use function is_wp_error;
use function preg_split;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_key;
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
     * Category repository.
     */
    private EmployeeCategoryRepositoryInterface $category_repository;

    /**
     * Logger instance.
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( EmployeeRepositoryInterface $repository, EmployeeCategoryRepositoryInterface $category_repository, Logger $logger ) {
        $this->repository          = $repository;
        $this->category_repository = $category_repository;
        $this->logger              = $logger;
    }

    /**
     * Retrieve employees sorted alphabetically.
     *
     * @return Employee[]
     */
    public function list_employees( array $args = [] ): array {
        $include_deleted = ! empty( $args['include_deleted'] );
        $only_deleted    = ! empty( $args['only_deleted'] );

        $employees = $this->repository->all( $include_deleted, $only_deleted );

        $employees = $this->attach_categories( $employees );

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

        return $this->enrich_employee( $employee );
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

        $record = $validated['record'];
        $result = $this->repository->create( $record );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed creating employee: %s', $result->get_error_message() ) );
            return $result;
        }

        $category_ids = $this->resolve_category_ids( $validated['category_ids'], $validated['new_categories'] );
        $this->category_repository->sync_employee_categories( $result->get_id(), $category_ids );

        return $this->enrich_employee( $this->repository->find( $result->get_id() ) ?? $result );
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

        $record = $validated['record'];
        $result = $this->repository->update( $employee_id, $record );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed updating employee #%d: %s', $employee_id, $result->get_error_message() ) );
            return $result;
        }

        $category_ids = $this->resolve_category_ids( $validated['category_ids'], $validated['new_categories'] );
        $this->category_repository->sync_employee_categories( $employee_id, $category_ids );

        return $this->enrich_employee( $this->repository->find( $employee_id ) ?? $result );
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
     * Restore a soft deleted employee.
     *
     * @param int $employee_id Employee identifier.
     *
     * @return Employee|WP_Error
     */
    public function restore_employee( int $employee_id ) {
        $existing = $this->repository->find_with_deleted( $employee_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_employee_not_found',
                __( 'The requested employee could not be found.', 'smooth-booking' )
            );
        }

        $result = $this->repository->restore( $employee_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed restoring employee #%d: %s', $employee_id, $result->get_error_message() ) );

            return $result;
        }

        return $this->enrich_employee( $result );
    }

    /**
     * Retrieve all categories.
     *
     * @return EmployeeCategory[]
     */
    public function list_categories(): array {
        return $this->category_repository->all();
    }

    /**
     * Create a new category.
     */
    public function create_category( string $name ) {
        $existing = $this->category_repository->find_by_name( $name );

        if ( $existing ) {
            return $existing;
        }

        $created = $this->category_repository->create( $name );

        if ( is_wp_error( $created ) ) {
            $this->logger->error( sprintf( 'Failed creating category: %s', $created->get_error_message() ) );
        }

        return $created;
    }

    /**
     * Validate and sanitize employee data.
     *
     * @param array<string, mixed> $data Submitted data.
     *
     * @return array{record:array<string,mixed>,category_ids:int[],new_categories:string[]}|WP_Error
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

        $profile_image_id = isset( $data['profile_image_id'] ) ? absint( $data['profile_image_id'] ) : 0;

        $default_color = isset( $data['default_color'] ) ? sanitize_hex_color( (string) $data['default_color'] ) : '';

        $visibility = isset( $data['visibility'] ) ? sanitize_key( (string) $data['visibility'] ) : 'public';
        $visibility = in_array( $visibility, [ 'public', 'private', 'archived' ], true ) ? $visibility : 'public';

        $category_ids = [];
        if ( isset( $data['category_ids'] ) ) {
            $category_ids = array_filter(
                array_map( 'absint', (array) $data['category_ids'] ),
                static function ( int $value ): bool {
                    return $value > 0;
                }
            );
        }

        $new_categories = [];
        if ( isset( $data['new_categories'] ) ) {
            $new_categories = $this->sanitize_new_categories( (string) $data['new_categories'] );
        }

        return [
            'record'         => [
                'name'             => $name,
                'email'            => $email ?: null,
                'phone'            => $phone ?: null,
                'specialization'   => $specialization ?: null,
                'available_online' => $available_online ? 1 : 0,
                'profile_image_id' => $profile_image_id > 0 ? $profile_image_id : null,
                'default_color'    => $default_color ?: null,
                'visibility'       => $visibility,
            ],
            'category_ids'   => array_values( array_unique( $category_ids ) ),
            'new_categories' => $new_categories,
        ];
    }

    /**
     * Enrich employees with categories.
     *
     * @param Employee[] $employees Employees to enrich.
     *
     * @return Employee[]
     */
    private function attach_categories( array $employees ): array {
        if ( empty( $employees ) ) {
            return $employees;
        }

        $ids = array_map(
            static function ( Employee $employee ): int {
                return $employee->get_id();
            },
            $employees
        );

        $map = $this->category_repository->get_categories_for_employees( $ids );

        foreach ( $employees as $index => $employee ) {
            $categories        = $map[ $employee->get_id() ] ?? [];
            $employees[ $index ] = $employee->with_categories( $categories );
        }

        return $employees;
    }

    /**
     * Attach categories to a single employee.
     */
    private function enrich_employee( Employee $employee ): Employee {
        $categories = $this->category_repository->get_employee_categories( $employee->get_id() );

        return $employee->with_categories( $categories );
    }

    /**
     * Resolve selected and newly created categories into identifiers.
     *
     * @param int[]    $category_ids  Existing category IDs.
     * @param string[] $new_categories New category names.
     *
     * @return int[]
     */
    private function resolve_category_ids( array $category_ids, array $new_categories ): array {
        $ids = [];

        foreach ( $category_ids as $category_id ) {
            $category = $this->category_repository->find( $category_id );

            if ( $category ) {
                $ids[] = $category->get_id();
            }
        }

        foreach ( $new_categories as $name ) {
            $category = $this->category_repository->find_by_name( $name );

            if ( ! $category ) {
                $category = $this->category_repository->create( $name );
            }

            if ( $category instanceof EmployeeCategory ) {
                $ids[] = $category->get_id();
            } elseif ( is_wp_error( $category ) ) {
                $this->logger->error( sprintf( 'Failed creating category "%s": %s', $name, $category->get_error_message() ) );
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Normalize free-form category input.
     *
     * @return string[]
     */
    private function sanitize_new_categories( string $raw ): array {
        $raw        = wp_unslash( $raw );
        $fragments  = preg_split( '/[\r\n,;]+/', $raw ) ?: [];
        $normalized = [];

        foreach ( $fragments as $fragment ) {
            $name = sanitize_text_field( $fragment );

            if ( '' === $name ) {
                continue;
            }

            $normalized[] = $name;
        }

        return array_values( array_unique( $normalized ) );
    }
}
