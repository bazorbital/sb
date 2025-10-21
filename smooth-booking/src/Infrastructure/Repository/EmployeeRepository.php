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
use function current_time;
use function is_array;
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
     * Resolve the employees table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_employees';
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
