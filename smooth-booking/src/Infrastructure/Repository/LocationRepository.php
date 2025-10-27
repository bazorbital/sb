<?php
/**
 * wpdb-backed repository for locations.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use wpdb;
use const ARRAY_A;
use function is_array;
use function sprintf;

/**
 * Provides read access to the smooth_locations table.
 */
class LocationRepository implements LocationRepositoryInterface {
    private wpdb $wpdb;

    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * {@inheritDoc}
     */
    public function list_active(): array {
        $table = $this->get_table_name();

        $sql = sprintf(
            'SELECT * FROM %1$s WHERE is_deleted = 0 ORDER BY name ASC',
            $table
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $results ) ) {
            return [];
        }

        return array_map(
            static function ( array $row ): Location {
                return Location::from_row( $row );
            },
            $results
        );
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $location_id ): ?Location {
        if ( $location_id <= 0 ) {
            return null;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE location_id = %d LIMIT 1",
            $location_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return Location::from_row( $row );
    }

    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_locations';
    }
}
