<?php
/**
 * wpdb-backed repository for locations.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;
use const ARRAY_A;
use function __;
use function array_map;
use function current_time;
use function implode;
use function is_array;
use function sprintf;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Provides CRUD access to the smooth_locations table.
 */
class LocationRepository implements LocationRepositoryInterface {
    private const CACHE_GROUP      = 'smooth-booking';
    private const CACHE_KEY_ACTIVE = 'locations_active';
    private const CACHE_KEY_ALL    = 'locations_all';
    private const CACHE_KEY_DELETED = 'locations_deleted';
    private const CACHE_TTL        = 900;

    private wpdb $wpdb;

    private Logger $logger;

    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function list_active(): array {
        return $this->all();
    }

    /**
     * {@inheritDoc}
     */
    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        $cache_key = $this->get_cache_key( $include_deleted, $only_deleted );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( is_array( $cached ) ) {
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

        $locations = array_map(
            static function ( array $row ): Location {
                return Location::from_row( $row );
            },
            $results
        );

        wp_cache_set( $cache_key, $locations, self::CACHE_GROUP, self::CACHE_TTL );

        return $locations;
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
            "SELECT * FROM {$table} WHERE location_id = %d AND is_deleted = %d",
            $location_id,
            0
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return Location::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function find_with_deleted( int $location_id ): ?Location {
        if ( $location_id <= 0 ) {
            return null;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE location_id = %d",
            $location_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return Location::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $table = $this->get_table_name();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'              => $data['name'],
                'profile_image_id'  => $data['profile_image_id'] ?: null,
                'address'           => $data['address'],
                'phone'             => $data['phone'],
                'base_email'        => $data['base_email'],
                'website'           => $data['website'],
                'industry_id'       => $data['industry_id'],
                'is_event_location' => $data['is_event_location'],
                'company_name'      => $data['company_name'],
                'company_address'   => $data['company_address'],
                'company_phone'     => $data['company_phone'],
                'is_deleted'        => 0,
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' ),
            ],
            [
                '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s',
            ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Location insert failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_location_insert_failed',
                __( 'Unable to create location. Please try again.', 'smooth-booking' )
            );
        }

        $location_id = (int) $this->wpdb->insert_id;

        $this->flush_cache();

        return $this->find_with_deleted( $location_id );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $location_id, array $data ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'name'              => $data['name'],
                'profile_image_id'  => $data['profile_image_id'] ?: null,
                'address'           => $data['address'],
                'phone'             => $data['phone'],
                'base_email'        => $data['base_email'],
                'website'           => $data['website'],
                'industry_id'       => $data['industry_id'],
                'is_event_location' => $data['is_event_location'],
                'company_name'      => $data['company_name'],
                'company_address'   => $data['company_address'],
                'company_phone'     => $data['company_phone'],
                'updated_at'        => current_time( 'mysql' ),
            ],
            [ 'location_id' => $location_id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Location update failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_location_update_failed',
                __( 'Unable to update location. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return $this->find_with_deleted( $location_id );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $location_id ) {
        $table = $this->get_table_name();

        $deleted = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 1,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'location_id' => $location_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $deleted ) {
            $this->logger->error( sprintf( 'Location delete failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_location_delete_failed',
                __( 'Unable to delete location. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function restore( int $location_id ) {
        $table = $this->get_table_name();

        $restored = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'location_id' => $location_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $restored ) {
            $this->logger->error( sprintf( 'Location restore failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_location_restore_failed',
                __( 'Unable to restore location. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_locations';
    }

    private function get_cache_key( bool $include_deleted, bool $only_deleted ): string {
        if ( $only_deleted ) {
            return self::CACHE_KEY_DELETED;
        }

        if ( $include_deleted ) {
            return self::CACHE_KEY_ALL;
        }

        return self::CACHE_KEY_ACTIVE;
    }

    private function flush_cache(): void {
        wp_cache_delete( self::CACHE_KEY_ACTIVE, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_DELETED, self::CACHE_GROUP );
    }
}
