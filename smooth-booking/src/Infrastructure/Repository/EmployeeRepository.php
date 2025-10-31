<?php
/**
 * Employee repository implementation using wpdb.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;
use const ARRAY_A;
use function __;
use function absint;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function current_time;
use function implode;
use function is_array;
use function round;
use function sprintf;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Provides CRUD operations for employees.
 */
class EmployeeRepository implements EmployeeRepositoryInterface {
    /**
     * Cache key for listing active employees.
     */
    private const CACHE_KEY_ACTIVE = 'employees_active';

    /**
     * Cache key for listing all employees.
     */
    private const CACHE_KEY_ALL = 'employees_all';

    /**
     * Cache key for deleted employees.
     */
    private const CACHE_KEY_DELETED = 'employees_deleted';

    /**
     * Cache group.
     */
    private const CACHE_GROUP = 'smooth-booking';

    /**
     * Cache expiration.
     */
    private const CACHE_TTL = 900;

    /**
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        $cache_key = $this->get_cache_key( $include_deleted, $only_deleted );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $table      = $this->get_table_name();
        $conditions = [];
        $params     = [];

        if ( $only_deleted ) {
            $conditions[] = 'is_deleted = %d';
            $params[]     = 1;
        } elseif ( ! $include_deleted ) {
            $conditions[] = 'is_deleted = %d';
            $params[]     = 0;
        }

        $sql = "SELECT * FROM {$table}";

        if ( ! empty( $conditions ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $conditions );
        }

        $sql .= ' ORDER BY name ASC';

        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, ...$params );
        }

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $results ) ) {
            $results = [];
        }

        $employees = array_map(
            static function ( array $row ): Employee {
                return Employee::from_row( $row );
            },
            $results
        );

        wp_cache_set( $cache_key, $employees, self::CACHE_GROUP, self::CACHE_TTL );

        return $employees;
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $employee_id ) {
        if ( $employee_id <= 0 ) {
            return null;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE employee_id = %d AND is_deleted = %d",
            $employee_id,
            0
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return Employee::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function find_with_deleted( int $employee_id ) {
        if ( $employee_id <= 0 ) {
            return null;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE employee_id = %d",
            $employee_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return Employee::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $table = $this->get_table_name();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'             => $data['name'],
                'email'            => $data['email'],
                'phone'            => $data['phone'],
                'specialization'   => $data['specialization'],
                'available_online' => $data['available_online'],
                'profile_image_id' => $data['profile_image_id'],
                'default_color'    => $data['default_color'],
                'visibility'       => $data['visibility'],
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Employee insert failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_insert_failed',
                __( 'Unable to create employee. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return $this->find( (int) $this->wpdb->insert_id );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $employee_id, array $data ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'name'             => $data['name'],
                'email'            => $data['email'],
                'phone'            => $data['phone'],
                'specialization'   => $data['specialization'],
                'available_online' => $data['available_online'],
                'profile_image_id' => $data['profile_image_id'],
                'default_color'    => $data['default_color'],
                'visibility'       => $data['visibility'],
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'employee_id' => $employee_id ],
            [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Employee update failed for #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_update_failed',
                __( 'Unable to update employee. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return $this->find( $employee_id );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $employee_id ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 1,
                'visibility' => 'archived',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'employee_id' => $employee_id ],
            [ '%d', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Employee delete failed for #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_delete_failed',
                __( 'Unable to delete employee. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function restore( int $employee_id ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'employee_id' => $employee_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Employee restore failed for #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_restore_failed',
                __( 'Unable to restore employee. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        $employee = $this->find_with_deleted( $employee_id );

        if ( null === $employee ) {
            return new WP_Error(
                'smooth_booking_employee_restore_missing',
                __( 'Unable to locate the employee after restore.', 'smooth-booking' )
            );
        }

        return $employee;
    }

    /**
     * {@inheritDoc}
     */
    public function get_employee_locations( int $employee_id ): array {
        $table = $this->get_employee_location_table();

        $sql = $this->wpdb->prepare(
            "SELECT location_id FROM {$table} WHERE employee_id = %d AND is_deleted = %d ORDER BY location_id ASC",
            $employee_id,
            0
        );

        $results = $this->wpdb->get_col( $sql );

        if ( ! is_array( $results ) ) {
            return [];
        }

        return array_values(
            array_map(
                static function ( $value ): int {
                    return (int) $value;
                },
                $results
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sync_employee_locations( int $employee_id, array $location_ids ) {
        $table = $this->get_employee_location_table();

        $deleted = $this->wpdb->delete( $table, [ 'employee_id' => $employee_id ], [ '%d' ] );

        if ( false === $deleted ) {
            $this->logger->error( sprintf( 'Failed clearing employee locations for #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_locations_failed',
                __( 'Unable to update locations for the employee.', 'smooth-booking' )
            );
        }

        $ids = array_values( array_unique( array_map( 'absint', $location_ids ) ) );

        if ( empty( $ids ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];

        foreach ( $ids as $location_id ) {
            if ( $location_id <= 0 ) {
                continue;
            }

            $values[]       = $employee_id;
            $values[]       = $location_id;
            $placeholders[] = '( %d, %d )';
        }

        if ( empty( $placeholders ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (employee_id, location_id) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );
        $result   = $this->wpdb->query( $prepared );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed assigning locations for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_locations_failed',
                __( 'Unable to update locations for the employee.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get_employee_services( int $employee_id ): array {
        $table = $this->get_service_provider_table();

        $sql = $this->wpdb->prepare(
            "SELECT service_id, provider_order, price_override FROM {$table} WHERE employee_id = %d ORDER BY provider_order ASC, service_id ASC",
            $employee_id
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $assignments = [];

        foreach ( $rows as $row ) {
            $price = null;

            if ( isset( $row['price_override'] ) && '' !== $row['price_override'] && null !== $row['price_override'] ) {
                $price = (float) $row['price_override'];
            }

            $assignments[] = [
                'service_id' => (int) $row['service_id'],
                'order'      => (int) $row['provider_order'],
                'price'      => $price,
            ];
        }

        return $assignments;
    }

    /**
     * {@inheritDoc}
     */
    public function sync_employee_services( int $employee_id, array $services ) {
        $table = $this->get_service_provider_table();

        $existing_orders = [];
        foreach ( $this->get_employee_services( $employee_id ) as $assignment ) {
            $existing_orders[ $assignment['service_id'] ] = $assignment['order'];
        }

        $deleted = $this->wpdb->delete( $table, [ 'employee_id' => $employee_id ], [ '%d' ] );

        if ( false === $deleted ) {
            $this->logger->error( sprintf( 'Failed clearing service assignments for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_services_failed',
                __( 'Unable to update employee services.', 'smooth-booking' )
            );
        }

        if ( empty( $services ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];
        $now          = current_time( 'mysql' );

        foreach ( $services as $service ) {
            if ( ! isset( $service['service_id'] ) ) {
                continue;
            }

            $service_id = absint( $service['service_id'] );

            if ( $service_id <= 0 ) {
                continue;
            }

            $order = isset( $service['order'] ) ? (int) $service['order'] : ( $existing_orders[ $service_id ] ?? 0 );

            $price = null;
            if ( isset( $service['price'] ) && '' !== $service['price'] ) {
                $price = (float) $service['price'];
            }

            $values[] = $service_id;
            $values[] = $employee_id;
            $values[] = $order;

            if ( null === $price ) {
                $placeholders[] = '( %d, %d, %d, NULL, %s, %s )';
            } else {
                $placeholders[] = '( %d, %d, %d, %f, %s, %s )';
                $values[]       = round( $price, 2 );
            }

            $values[] = $now;
            $values[] = $now;
        }

        if ( empty( $placeholders ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (service_id, employee_id, provider_order, price_override, created_at, updated_at) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );
        $result   = $this->wpdb->query( $prepared );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed assigning services for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_services_failed',
                __( 'Unable to update employee services.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get_employee_schedule( int $employee_id ): array {
        $hours_table  = $this->get_employee_working_hours_table();
        $breaks_table = $this->get_employee_breaks_table();

        $hours_sql = $this->wpdb->prepare(
            "SELECT day_of_week, start_time, end_time, is_off_day FROM {$hours_table} WHERE employee_id = %d AND is_deleted = %d ORDER BY day_of_week ASC",
            $employee_id,
            0
        );

        $rows = $this->wpdb->get_results( $hours_sql, ARRAY_A );

        $schedule = [];

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $day = (int) $row['day_of_week'];
                $schedule[ $day ] = [
                    'start_time' => isset( $row['start_time'] ) && '' !== $row['start_time'] ? substr( (string) $row['start_time'], 0, 5 ) : null,
                    'end_time'   => isset( $row['end_time'] ) && '' !== $row['end_time'] ? substr( (string) $row['end_time'], 0, 5 ) : null,
                    'is_off_day' => (int) $row['is_off_day'] === 1,
                    'breaks'     => [],
                ];
            }
        }

        $breaks_sql = $this->wpdb->prepare(
            "SELECT day_of_week, start_time, end_time FROM {$breaks_table} WHERE employee_id = %d AND is_deleted = %d ORDER BY day_of_week ASC, start_time ASC",
            $employee_id,
            0
        );

        $break_rows = $this->wpdb->get_results( $breaks_sql, ARRAY_A );

        if ( is_array( $break_rows ) ) {
            foreach ( $break_rows as $break ) {
                $day = (int) $break['day_of_week'];

                if ( ! isset( $schedule[ $day ] ) ) {
                    $schedule[ $day ] = [
                        'start_time' => null,
                        'end_time'   => null,
                        'is_off_day' => false,
                        'breaks'     => [],
                    ];
                }

                $schedule[ $day ]['breaks'][] = [
                    'start_time' => substr( (string) $break['start_time'], 0, 5 ),
                    'end_time'   => substr( (string) $break['end_time'], 0, 5 ),
                ];
            }
        }

        ksort( $schedule );

        return $schedule;
    }

    /**
     * {@inheritDoc}
     */
    public function save_employee_schedule( int $employee_id, array $schedule ) {
        $hours_table  = $this->get_employee_working_hours_table();
        $breaks_table = $this->get_employee_breaks_table();

        $deleted_hours = $this->wpdb->delete( $hours_table, [ 'employee_id' => $employee_id ], [ '%d' ] );

        if ( false === $deleted_hours ) {
            $this->logger->error( sprintf( 'Failed clearing working hours for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_schedule_failed',
                __( 'Unable to update the employee schedule.', 'smooth-booking' )
            );
        }

        $deleted_breaks = $this->wpdb->delete( $breaks_table, [ 'employee_id' => $employee_id ], [ '%d' ] );

        if ( false === $deleted_breaks ) {
            $this->logger->error( sprintf( 'Failed clearing schedule breaks for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_employee_schedule_failed',
                __( 'Unable to update the employee schedule.', 'smooth-booking' )
            );
        }

        if ( empty( $schedule ) ) {
            return true;
        }

        $hour_placeholders  = [];
        $hour_values        = [];
        $break_placeholders = [];
        $break_values       = [];

        foreach ( $schedule as $day => $definition ) {
            $day      = absint( $day );
            $is_off   = ! empty( $definition['is_off_day'] );
            $start    = $definition['start_time'] ?? null;
            $end      = $definition['end_time'] ?? null;

            $row_values      = [ $employee_id, $day ];
            $row_placeholder = '( %d, %d, ';

            if ( null === $start ) {
                $row_placeholder .= 'NULL, ';
            } else {
                $row_placeholder .= '%s, ';
                $row_values[]     = $start;
            }

            if ( null === $end ) {
                $row_placeholder .= 'NULL, ';
            } else {
                $row_placeholder .= '%s, ';
                $row_values[]     = $end;
            }

            $row_placeholder .= '%d )';
            $row_values[]     = $is_off ? 1 : 0;

            $hour_placeholders[] = $row_placeholder;
            $hour_values          = array_merge( $hour_values, $row_values );

            if ( empty( $definition['breaks'] ) || ! is_array( $definition['breaks'] ) ) {
                continue;
            }

            foreach ( $definition['breaks'] as $break ) {
                if ( empty( $break['start_time'] ) || empty( $break['end_time'] ) ) {
                    continue;
                }

                $break_placeholders[] = '( %d, %d, %s, %s )';
                $break_values[]        = $employee_id;
                $break_values[]        = $day;
                $break_values[]        = $break['start_time'];
                $break_values[]        = $break['end_time'];
            }
        }

        if ( ! empty( $hour_placeholders ) ) {
            $sql = sprintf(
                'INSERT INTO %1$s (employee_id, day_of_week, start_time, end_time, is_off_day) VALUES %2$s',
                $hours_table,
                implode( ', ', $hour_placeholders )
            );

            $prepared = $this->wpdb->prepare( $sql, $hour_values );
            $result   = $this->wpdb->query( $prepared );

            if ( false === $result ) {
                $this->logger->error( sprintf( 'Failed saving working hours for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

                return new WP_Error(
                    'smooth_booking_employee_schedule_failed',
                    __( 'Unable to update the employee schedule.', 'smooth-booking' )
                );
            }
        }

        if ( ! empty( $break_placeholders ) ) {
            $sql = sprintf(
                'INSERT INTO %1$s (employee_id, day_of_week, start_time, end_time) VALUES %2$s',
                $breaks_table,
                implode( ', ', $break_placeholders )
            );

            $prepared = $this->wpdb->prepare( $sql, $break_values );
            $result   = $this->wpdb->query( $prepared );

            if ( false === $result ) {
                $this->logger->error( sprintf( 'Failed saving schedule breaks for employee #%d: %s', $employee_id, wp_json_encode( $this->wpdb->last_error ) ) );

                return new WP_Error(
                    'smooth_booking_employee_schedule_failed',
                    __( 'Unable to update the employee schedule.', 'smooth-booking' )
                );
            }
        }

        $this->flush_cache();

        return true;
    }

    /**
     * Resolve the employees table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_employees';
    }

    /**
     * Resolve the employee location relationship table name.
     */
    private function get_employee_location_table(): string {
        return $this->wpdb->prefix . 'smooth_employee_locations';
    }

    /**
     * Resolve the service provider relationship table name.
     */
    private function get_service_provider_table(): string {
        return $this->wpdb->prefix . 'smooth_service_providers';
    }

    /**
     * Resolve the employee working hours table name.
     */
    private function get_employee_working_hours_table(): string {
        return $this->wpdb->prefix . 'smooth_employee_working_hours';
    }

    /**
     * Resolve the employee breaks table name.
     */
    private function get_employee_breaks_table(): string {
        return $this->wpdb->prefix . 'smooth_employee_breaks';
    }

    /**
     * Invalidate cached employee collections.
     */
    private function flush_cache(): void {
        wp_cache_delete( self::CACHE_KEY_ACTIVE, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_DELETED, self::CACHE_GROUP );
    }

    /**
     * Determine cache key based on filters.
     */
    private function get_cache_key( bool $include_deleted, bool $only_deleted ): string {
        if ( $only_deleted ) {
            return self::CACHE_KEY_DELETED;
        }

        if ( $include_deleted ) {
            return self::CACHE_KEY_ALL;
        }

        return self::CACHE_KEY_ACTIVE;
    }
}
