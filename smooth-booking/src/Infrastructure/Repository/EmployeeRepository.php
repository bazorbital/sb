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

use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Provides CRUD operations for employees.
 */
class EmployeeRepository implements EmployeeRepositoryInterface {
    /**
     * Cache key for listing employees.
     */
    private const CACHE_KEY_ALL = 'employees_all';

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
    public function all(): array {
        $cached = wp_cache_get( self::CACHE_KEY_ALL, self::CACHE_GROUP );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE is_deleted = %d ORDER BY name ASC",
            0
        );

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

        wp_cache_set( self::CACHE_KEY_ALL, $employees, self::CACHE_GROUP, self::CACHE_TTL );

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
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
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
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'employee_id' => $employee_id ],
            [ '%s', '%s', '%s', '%s', '%d', '%s' ],
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
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'employee_id' => $employee_id ],
            [ '%d', '%s' ],
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
     * Resolve the employees table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_employees';
    }

    /**
     * Invalidate cached employee collections.
     */
    private function flush_cache(): void {
        wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
    }
}
