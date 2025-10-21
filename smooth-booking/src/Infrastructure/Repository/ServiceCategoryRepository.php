<?php
/**
 * Service category repository.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Services\ServiceCategory;
use SmoothBooking\Domain\Services\ServiceCategoryRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use const ARRAY_A;

use function __;
use function array_map;
use function current_time;
use function sanitize_title;
use function sprintf;
use function wp_json_encode;

/**
 * Handles CRUD for service categories.
 */
class ServiceCategoryRepository implements ServiceCategoryRepositoryInterface {
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
        $table = $this->get_table();

        $sql    = "SELECT * FROM {$table} ORDER BY name ASC";
        $result = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! $result ) {
            return [];
        }

        return array_map(
            static function ( array $row ): ServiceCategory {
                return ServiceCategory::from_row( $row );
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

        $table = $this->get_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE category_id = %d",
            $category_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return ServiceCategory::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function find_by_name( string $name ): ?ServiceCategory {
        $name = trim( $name );

        if ( '' === $name ) {
            return null;
        }

        $table = $this->get_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE slug = %s",
            sanitize_title( $name )
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return ServiceCategory::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( string $name ) {
        $name = trim( $name );

        if ( '' === $name ) {
            return new WP_Error( 'smooth_booking_service_category_invalid', __( 'Category name cannot be empty.', 'smooth-booking' ) );
        }

        $table = $this->get_table();

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
            $this->logger->error( sprintf( 'Failed creating service category: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_service_category_insert_failed',
                __( 'Could not save the service category.', 'smooth-booking' )
            );
        }

        return $this->find( (int) $this->wpdb->insert_id );
    }

    /**
     * Retrieve table name.
     */
    private function get_table(): string {
        return $this->wpdb->prefix . 'smooth_service_categories';
    }
}
