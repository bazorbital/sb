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
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function is_email;
use function is_wp_error;
use function is_array;
use function is_numeric;
use function preg_split;
use function preg_match;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function wp_unslash;
use function strtotime;
use function trim;

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

        $employee_id  = $result->get_id();
        $category_ids = $this->resolve_category_ids( $validated['category_ids'], $validated['new_categories'] );
        $this->category_repository->sync_employee_categories( $employee_id, $category_ids );

        $locations_sync = $this->repository->sync_employee_locations( $employee_id, $validated['locations'] );

        if ( is_wp_error( $locations_sync ) ) {
            return $locations_sync;
        }

        $services_sync = $this->repository->sync_employee_services( $employee_id, $validated['services'] );

        if ( is_wp_error( $services_sync ) ) {
            return $services_sync;
        }

        $schedule_sync = $this->repository->save_employee_schedule( $employee_id, $validated['schedule'] );

        if ( is_wp_error( $schedule_sync ) ) {
            return $schedule_sync;
        }

        return $this->enrich_employee( $this->repository->find( $employee_id ) ?? $result );
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

        $locations_sync = $this->repository->sync_employee_locations( $employee_id, $validated['locations'] );

        if ( is_wp_error( $locations_sync ) ) {
            return $locations_sync;
        }

        $services_sync = $this->repository->sync_employee_services( $employee_id, $validated['services'] );

        if ( is_wp_error( $services_sync ) ) {
            return $services_sync;
        }

        $schedule_sync = $this->repository->save_employee_schedule( $employee_id, $validated['schedule'] );

        if ( is_wp_error( $schedule_sync ) ) {
            return $schedule_sync;
        }

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

        $locations = [];
        if ( isset( $data['locations'] ) ) {
            $locations = $this->sanitize_location_ids( (array) $data['locations'] );
        }

        $services = [];
        if ( isset( $data['services'] ) ) {
            $services = $this->sanitize_services_payload( (array) $data['services'] );

            if ( is_wp_error( $services ) ) {
                return $services;
            }
        }

        $schedule = [];
        if ( isset( $data['schedule'] ) ) {
            $schedule = $this->sanitize_schedule_payload( (array) $data['schedule'] );

            if ( is_wp_error( $schedule ) ) {
                return $schedule;
            }
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
            'locations'      => $locations,
            'services'       => $services,
            'schedule'       => $schedule,
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
            $employee_id = $employee->get_id();
            $categories  = $map[ $employee_id ] ?? [];

            $employees[ $index ] = $employee
                ->with_categories( $categories )
                ->with_locations( $this->repository->get_employee_locations( $employee_id ) )
                ->with_services( $this->repository->get_employee_services( $employee_id ) )
                ->with_schedule( $this->repository->get_employee_schedule( $employee_id ) );
        }

        return $employees;
    }

    /**
     * Attach categories to a single employee.
     */
    private function enrich_employee( Employee $employee ): Employee {
        $employee_id = $employee->get_id();
        $categories  = $this->category_repository->get_employee_categories( $employee_id );
        $locations   = $this->repository->get_employee_locations( $employee_id );
        $services    = $this->repository->get_employee_services( $employee_id );
        $schedule    = $this->repository->get_employee_schedule( $employee_id );

        return $employee
            ->with_categories( $categories )
            ->with_locations( $locations )
            ->with_services( $services )
            ->with_schedule( $schedule );
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
     * Normalize submitted location identifiers.
     *
     * @param array<int|string, mixed> $location_ids Raw location identifiers.
     *
     * @return int[]
     */
    private function sanitize_location_ids( array $location_ids ): array {
        $ids = array_filter(
            array_map( 'absint', $location_ids ),
            static function ( int $value ): bool {
                return $value > 0;
            }
        );

        return array_values( array_unique( $ids ) );
    }

    /**
     * Sanitize submitted service assignments.
     *
     * @param array<int|string, mixed> $services Raw service payload.
     *
     * @return array<int, array{service_id:int, order:int, price:float|null}>|WP_Error
     */
    private function sanitize_services_payload( array $services ) {
        $assignments = [];

        foreach ( $services as $key => $payload ) {
            if ( ! is_array( $payload ) ) {
                continue;
            }

            $service_id = isset( $payload['service_id'] ) ? absint( $payload['service_id'] ) : absint( $key );

            if ( $service_id <= 0 ) {
                continue;
            }

            $selected = true;

            if ( array_key_exists( 'selected', $payload ) ) {
                $selected = (bool) rest_sanitize_boolean( $payload['selected'] );
            } elseif ( array_key_exists( 'enabled', $payload ) ) {
                $selected = (bool) rest_sanitize_boolean( $payload['enabled'] );
            }

            if ( ! $selected ) {
                continue;
            }

            $order = isset( $payload['order'] ) ? (int) $payload['order'] : 0;

            $price = null;

            if ( isset( $payload['price'] ) ) {
                $raw_price = trim( wp_unslash( (string) $payload['price'] ) );

                if ( '' !== $raw_price ) {
                    if ( ! is_numeric( $raw_price ) ) {
                        return new WP_Error(
                            'smooth_booking_employee_invalid_service_price',
                            __( 'Service prices must be numeric values.', 'smooth-booking' )
                        );
                    }

                    $price = round( (float) $raw_price, 2 );

                    if ( $price < 0 ) {
                        return new WP_Error(
                            'smooth_booking_employee_invalid_service_price',
                            __( 'Service prices cannot be negative.', 'smooth-booking' )
                        );
                    }
                }
            }

            $assignments[] = [
                'service_id' => $service_id,
                'order'      => $order,
                'price'      => $price,
            ];
        }

        return $assignments;
    }

    /**
     * Sanitize submitted schedule definition.
     *
     * @param array<int|string, mixed> $schedule Raw schedule payload.
     *
     * @return array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}> | WP_Error
     */
    private function sanitize_schedule_payload( array $schedule ) {
        $normalized = $this->get_empty_schedule_template();

        foreach ( $normalized as $day => $definition ) {
            $raw = $schedule[ $day ] ?? [];

            if ( ! is_array( $raw ) ) {
                continue;
            }

            $is_off = false;

            if ( array_key_exists( 'is_off', $raw ) ) {
                $is_off = (bool) rest_sanitize_boolean( $raw['is_off'] );
            } elseif ( array_key_exists( 'is_off_day', $raw ) ) {
                $is_off = (bool) rest_sanitize_boolean( $raw['is_off_day'] );
            }

            $start_raw = '';
            if ( array_key_exists( 'start_time', $raw ) ) {
                $start_raw = sanitize_text_field( wp_unslash( (string) $raw['start_time'] ) );
            } elseif ( array_key_exists( 'start', $raw ) ) {
                $start_raw = sanitize_text_field( wp_unslash( (string) $raw['start'] ) );
            }

            $end_raw = '';
            if ( array_key_exists( 'end_time', $raw ) ) {
                $end_raw = sanitize_text_field( wp_unslash( (string) $raw['end_time'] ) );
            } elseif ( array_key_exists( 'end', $raw ) ) {
                $end_raw = sanitize_text_field( wp_unslash( (string) $raw['end'] ) );
            }

            $start_value = $this->normalize_time_value( $start_raw );
            $end_value   = $this->normalize_time_value( $end_raw );

            if ( false === $start_value || false === $end_value ) {
                return new WP_Error(
                    'smooth_booking_employee_invalid_schedule',
                    __( 'Working hours must use the HH:MM format.', 'smooth-booking' )
                );
            }

            if ( $is_off ) {
                $normalized[ $day ] = [
                    'start_time' => null,
                    'end_time'   => null,
                    'is_off_day' => true,
                    'breaks'     => [],
                ];

                continue;
            }

            if ( null === $start_value || null === $end_value ) {
                return new WP_Error(
                    'smooth_booking_employee_invalid_schedule',
                    __( 'Please provide both start and end time for each working day.', 'smooth-booking' )
                );
            }

            $start_timestamp = strtotime( '1970-01-01 ' . $start_value );
            $end_timestamp   = strtotime( '1970-01-01 ' . $end_value );

            if ( false === $start_timestamp || false === $end_timestamp || $start_timestamp >= $end_timestamp ) {
                return new WP_Error(
                    'smooth_booking_employee_invalid_schedule',
                    __( 'The end time must be later than the start time.', 'smooth-booking' )
                );
            }

            $breaks = [];

            if ( isset( $raw['breaks'] ) && is_array( $raw['breaks'] ) ) {
                foreach ( $raw['breaks'] as $break ) {
                    if ( ! is_array( $break ) ) {
                        continue;
                    }

                    $break_start_raw = '';
                    if ( isset( $break['start_time'] ) ) {
                        $break_start_raw = sanitize_text_field( wp_unslash( (string) $break['start_time'] ) );
                    } elseif ( isset( $break['start'] ) ) {
                        $break_start_raw = sanitize_text_field( wp_unslash( (string) $break['start'] ) );
                    }

                    $break_end_raw = '';
                    if ( isset( $break['end_time'] ) ) {
                        $break_end_raw = sanitize_text_field( wp_unslash( (string) $break['end_time'] ) );
                    } elseif ( isset( $break['end'] ) ) {
                        $break_end_raw = sanitize_text_field( wp_unslash( (string) $break['end'] ) );
                    }

                    $break_start = $this->normalize_time_value( $break_start_raw );
                    $break_end   = $this->normalize_time_value( $break_end_raw );

                    if ( null === $break_start || null === $break_end ) {
                        continue;
                    }

                    if ( false === $break_start || false === $break_end ) {
                        return new WP_Error(
                            'smooth_booking_employee_invalid_schedule_break',
                            __( 'Break times must use the HH:MM format.', 'smooth-booking' )
                        );
                    }

                    $break_start_ts = strtotime( '1970-01-01 ' . $break_start );
                    $break_end_ts   = strtotime( '1970-01-01 ' . $break_end );

                    if ( false === $break_start_ts || false === $break_end_ts || $break_start_ts >= $break_end_ts ) {
                        return new WP_Error(
                            'smooth_booking_employee_invalid_schedule_break',
                            __( 'Break end time must be later than the break start time.', 'smooth-booking' )
                        );
                    }

                    if ( $break_start_ts < $start_timestamp || $break_end_ts > $end_timestamp ) {
                        return new WP_Error(
                            'smooth_booking_employee_invalid_schedule_break',
                            __( 'Breaks must fall within the working hours.', 'smooth-booking' )
                        );
                    }

                    $breaks[] = [
                        'start_time' => $break_start,
                        'end_time'   => $break_end,
                    ];
                }
            }

            $normalized[ $day ] = [
                'start_time' => $start_value,
                'end_time'   => $end_value,
                'is_off_day' => false,
                'breaks'     => $breaks,
            ];
        }

        return $normalized;
    }

    /**
     * Normalize a time value into HH:MM or null.
     *
     * @param string $raw Raw value.
     *
     * @return string|null|false
     */
    private function normalize_time_value( string $raw ) {
        $value = trim( $raw );

        if ( '' === $value ) {
            return null;
        }

        if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
            return false;
        }

        return $value;
    }

    /**
     * Build an empty weekly schedule template.
     *
     * @return array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>}
     */
    private function get_empty_schedule_template(): array {
        return [
            1 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            2 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            3 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            4 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            5 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            6 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
            7 => [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ],
        ];
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
