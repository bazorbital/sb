<?php
/**
 * Employee category repository implementation.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Employees\EmployeeCategory;
use SmoothBooking\Domain\Employees\EmployeeCategoryRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use const ARRAY_A;

use function __;
use function array_fill;
use function array_map;
use function array_unique;
use function current_time;
use function implode;
use function sanitize_title;
use function sprintf;
use function wp_json_encode;

/**
 * Provides CRUD helpers for employee categories.
 */
class EmployeeCategoryRepository implements EmployeeCategoryRepositoryInterface {
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
        $table = $this->get_categories_table();

        $sql    = "SELECT * FROM {$table} ORDER BY name ASC";
        $result = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! $result ) {
            return [];
        }

        return array_map(
            static function ( array $row ): EmployeeCategory {
                return EmployeeCategory::from_row( $row );
            },
            $result
        );
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $category_id ) {
        if ( $category_id <= 0 ) {
            return null;
        }

        $table = $this->get_categories_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE category_id = %d",
            $category_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return EmployeeCategory::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function find_by_name( string $name ): ?EmployeeCategory {
        $name = trim( $name );

        if ( '' === $name ) {
            return null;
        }

        $table = $this->get_categories_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE slug = %s",
            sanitize_title( $name )
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return EmployeeCategory::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( string $name ) {
        $name = trim( $name );

        if ( '' === $name ) {
            return new WP_Error( 'smooth_booking_category_invalid', __( 'Category name cannot be empty.', 'smooth-booking' ) );
        }

        $table = $this->get_categories_table();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'       => $name,
                'slug'       => sanitize_title( $name ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Failed creating employee category: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_category_insert_failed',
                __( 'Could not save the employee category.', 'smooth-booking' )
            );
        }

        return $this->find( (int) $this->wpdb->insert_id );
    }

    /**
     * {@inheritDoc}
     */
    public function sync_employee_categories( int $employee_id, array $category_ids ): bool {
        $employee_id = (int) $employee_id;

        if ( $employee_id <= 0 ) {
            return false;
        }

        $category_ids = array_values( array_unique( array_map( 'absint', $category_ids ) ) );

        $table = $this->get_relationship_table();

        $this->wpdb->delete( $table, [ 'employee_id' => $employee_id ], [ '%d' ] );

        if ( empty( $category_ids ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];

        foreach ( $category_ids as $category_id ) {
            if ( $category_id <= 0 ) {
                continue;
            }

            $values[]       = $employee_id;
            $values[]       = $category_id;
            $values[]       = current_time( 'mysql' );
            $placeholders[] = '( %d, %d, %s )';
        }

        if ( empty( $values ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (employee_id, category_id, created_at) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );

        $result = $this->wpdb->query( $prepared );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed syncing employee categories: %s', wp_json_encode( $this->wpdb->last_error ) ) );
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get_categories_for_employees( array $employee_ids ): array {
        $employee_ids = array_values( array_unique( array_map( 'absint', $employee_ids ) ) );

        if ( empty( $employee_ids ) ) {
            return [];
        }

        $table_rel = $this->get_relationship_table();
        $table_cat = $this->get_categories_table();

        $placeholders = implode( ', ', array_fill( 0, count( $employee_ids ), '%d' ) );

        $sql = $this->wpdb->prepare(
            "SELECT rel.employee_id, cat.* FROM {$table_rel} rel INNER JOIN {$table_cat} cat ON rel.category_id = cat.category_id WHERE rel.employee_id IN ( {$placeholders} ) ORDER BY cat.name ASC",
            $employee_ids
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return [];
        }

        $grouped = [];

        foreach ( $rows as $row ) {
            $employee_id = isset( $row['employee_id'] ) ? (int) $row['employee_id'] : 0;

            if ( $employee_id <= 0 ) {
                continue;
            }

            if ( ! isset( $grouped[ $employee_id ] ) ) {
                $grouped[ $employee_id ] = [];
            }

            $grouped[ $employee_id ][] = EmployeeCategory::from_row( $row );
        }

        return $grouped;
    }

    /**
     * {@inheritDoc}
     */
    public function get_employee_categories( int $employee_id ): array {
        $map = $this->get_categories_for_employees( [ $employee_id ] );

        return $map[ $employee_id ] ?? [];
    }

    /**
     * Retrieve categories table name.
     */
    private function get_categories_table(): string {
        return $this->wpdb->prefix . 'smooth_employee_categories';
    }

    /**
     * Retrieve relationship table name.
     */
    private function get_relationship_table(): string {
        return $this->wpdb->prefix . 'smooth_employee_category_relationships';
    }
}
