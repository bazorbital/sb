<?php
/**
 * Service repository implementation using wpdb.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceCategory;
use SmoothBooking\Domain\Services\ServiceRepositoryInterface;
use SmoothBooking\Domain\Services\ServiceTag;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use const ARRAY_A;

use function __;
use function absint;
use function array_fill;
use function array_map;
use function array_unique;
use function array_values;
use function current_time;
use function implode;
use function is_array;
use function number_format;
use function sprintf;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Provides CRUD operations for services.
 */
class ServiceRepository implements ServiceRepositoryInterface {
    /**
     * Cache keys.
     */
    private const CACHE_KEY_ACTIVE  = 'services_active';
    private const CACHE_KEY_ALL     = 'services_all';
    private const CACHE_KEY_DELETED = 'services_deleted';

    /**
     * Cache group.
     */
    private const CACHE_GROUP = 'smooth-booking';

    /**
     * Cache TTL.
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

        $table      = $this->get_services_table();
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

        $services = array_map(
            static function ( array $row ): Service {
                return Service::from_row( $row );
            },
            $results
        );

        wp_cache_set( $cache_key, $services, self::CACHE_GROUP, self::CACHE_TTL );

        return $services;
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $service_id ) {
        if ( $service_id <= 0 ) {
            return null;
        }

        $table = $this->get_services_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE service_id = %d AND is_deleted = %d",
            $service_id,
            0
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return Service::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function find_with_deleted( int $service_id ) {
        if ( $service_id <= 0 ) {
            return null;
        }

        $table = $this->get_services_table();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE service_id = %d",
            $service_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return Service::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $table = $this->get_services_table();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'                         => $data['name'],
                'profile_image_id'             => $data['profile_image_id'],
                'default_color'                => $data['default_color'],
                'visibility'                   => $data['visibility'],
                'price'                        => null === $data['price'] ? null : number_format( (float) $data['price'], 2, '.', '' ),
                'payment_methods_mode'         => $data['payment_methods_mode'],
                'info'                         => $data['info'],
                'providers_preference'         => $data['providers_preference'],
                'providers_random_tie'         => $data['providers_random_tie'],
                'occupancy_period_before'      => $data['occupancy_period_before'],
                'occupancy_period_after'       => $data['occupancy_period_after'],
                'duration_key'                 => $data['duration_key'],
                'slot_length_key'              => $data['slot_length_key'],
                'padding_before_key'           => $data['padding_before_key'],
                'padding_after_key'            => $data['padding_after_key'],
                'online_meeting_provider'      => $data['online_meeting_provider'],
                'limit_per_customer'           => $data['limit_per_customer'],
                'final_step_url_enabled'       => $data['final_step_url_enabled'],
                'final_step_url'               => $data['final_step_url'],
                'min_time_prior_booking_key'   => $data['min_time_prior_booking_key'],
                'min_time_prior_cancel_key'    => $data['min_time_prior_cancel_key'],
                'created_at'                   => current_time( 'mysql' ),
                'updated_at'                   => current_time( 'mysql' ),
            ],
            [
                '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Service insert failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_service_insert_failed',
                __( 'Could not save the service.', 'smooth-booking' )
            );
        }

        $service_id = (int) $this->wpdb->insert_id;
        $this->flush_cache();

        $service = $this->find( $service_id );

        return $service ?: new WP_Error( 'smooth_booking_service_not_found', __( 'Service could not be loaded after creation.', 'smooth-booking' ) );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $service_id, array $data ) {
        $table = $this->get_services_table();

        $updated = $this->wpdb->update(
            $table,
            [
                'name'                         => $data['name'],
                'profile_image_id'             => $data['profile_image_id'],
                'default_color'                => $data['default_color'],
                'visibility'                   => $data['visibility'],
                'price'                        => null === $data['price'] ? null : number_format( (float) $data['price'], 2, '.', '' ),
                'payment_methods_mode'         => $data['payment_methods_mode'],
                'info'                         => $data['info'],
                'providers_preference'         => $data['providers_preference'],
                'providers_random_tie'         => $data['providers_random_tie'],
                'occupancy_period_before'      => $data['occupancy_period_before'],
                'occupancy_period_after'       => $data['occupancy_period_after'],
                'duration_key'                 => $data['duration_key'],
                'slot_length_key'              => $data['slot_length_key'],
                'padding_before_key'           => $data['padding_before_key'],
                'padding_after_key'            => $data['padding_after_key'],
                'online_meeting_provider'      => $data['online_meeting_provider'],
                'limit_per_customer'           => $data['limit_per_customer'],
                'final_step_url_enabled'       => $data['final_step_url_enabled'],
                'final_step_url'               => $data['final_step_url'],
                'min_time_prior_booking_key'   => $data['min_time_prior_booking_key'],
                'min_time_prior_cancel_key'    => $data['min_time_prior_cancel_key'],
                'updated_at'                   => current_time( 'mysql' ),
            ],
            [ 'service_id' => $service_id ],
            [
                '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
            ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Service update failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_service_update_failed',
                __( 'Could not update the service.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        $service = $this->find( $service_id );

        return $service ?: new WP_Error( 'smooth_booking_service_not_found', __( 'Service could not be loaded after update.', 'smooth-booking' ) );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $service_id ) {
        $table = $this->get_services_table();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 1,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'service_id' => $service_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            return new WP_Error(
                'smooth_booking_service_delete_failed',
                __( 'Could not delete the service.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function restore( int $service_id ) {
        $table = $this->get_services_table();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'service_id' => $service_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            return new WP_Error(
                'smooth_booking_service_restore_failed',
                __( 'Could not restore the service.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function sync_service_categories( int $service_id, array $category_ids ): bool {
        $service_id   = (int) $service_id;
        $category_ids = array_values( array_unique( array_map( 'absint', $category_ids ) ) );

        $table = $this->get_service_category_relationship_table();

        $this->wpdb->delete( $table, [ 'service_id' => $service_id ], [ '%d' ] );

        if ( empty( $category_ids ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];

        foreach ( $category_ids as $category_id ) {
            if ( $category_id <= 0 ) {
                continue;
            }

            $values[]       = $service_id;
            $values[]       = $category_id;
            $values[]       = current_time( 'mysql' );
            $placeholders[] = '( %d, %d, %s )';
        }

        if ( empty( $values ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (service_id, category_id, created_at) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );

        $result = $this->wpdb->query( $prepared );

        return false !== $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sync_service_tags( int $service_id, array $tag_ids ): bool {
        $service_id = (int) $service_id;
        $tag_ids    = array_values( array_unique( array_map( 'absint', $tag_ids ) ) );

        $table = $this->get_service_tag_relationship_table();

        $this->wpdb->delete( $table, [ 'service_id' => $service_id ], [ '%d' ] );

        if ( empty( $tag_ids ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];

        foreach ( $tag_ids as $tag_id ) {
            if ( $tag_id <= 0 ) {
                continue;
            }

            $values[]       = $service_id;
            $values[]       = $tag_id;
            $values[]       = current_time( 'mysql' );
            $placeholders[] = '( %d, %d, %s )';
        }

        if ( empty( $values ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (service_id, tag_id, created_at) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );

        $result = $this->wpdb->query( $prepared );

        return false !== $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sync_service_providers( int $service_id, array $providers ): bool {
        $service_id = (int) $service_id;

        $table = $this->get_service_provider_table();

        $this->wpdb->delete( $table, [ 'service_id' => $service_id ], [ '%d' ] );

        if ( empty( $providers ) ) {
            return true;
        }

        $values       = [];
        $placeholders = [];

        foreach ( $providers as $provider ) {
            $employee_id = isset( $provider['employee_id'] ) ? (int) $provider['employee_id'] : 0;
            $order       = isset( $provider['order'] ) ? (int) $provider['order'] : 0;

            if ( $employee_id <= 0 ) {
                continue;
            }

            $values[]       = $service_id;
            $values[]       = $employee_id;
            $values[]       = $order;
            $values[]       = current_time( 'mysql' );
            $values[]       = current_time( 'mysql' );
            $placeholders[] = '( %d, %d, %d, %s, %s )';
        }

        if ( empty( $values ) ) {
            return true;
        }

        $sql = sprintf(
            'INSERT INTO %1$s (service_id, employee_id, provider_order, created_at, updated_at) VALUES %2$s',
            $table,
            implode( ', ', $placeholders )
        );

        $prepared = $this->wpdb->prepare( $sql, $values );

        $result = $this->wpdb->query( $prepared );

        return false !== $result;
    }

    /**
     * {@inheritDoc}
     */
    public function get_categories_for_services( array $service_ids ): array {
        $service_ids = array_values( array_unique( array_map( 'absint', $service_ids ) ) );

        if ( empty( $service_ids ) ) {
            return [];
        }

        $relationships = $this->get_service_category_relationship_table();
        $categories    = $this->get_service_categories_table();

        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

        $sql = sprintf(
            'SELECT sc.service_id, c.* FROM %1$s sc INNER JOIN %2$s c ON sc.category_id = c.category_id WHERE sc.service_id IN (%3$s) ORDER BY c.name ASC',
            $relationships,
            $categories,
            $placeholders
        );

        $prepared = $this->wpdb->prepare( $sql, $service_ids );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $map = [];

        foreach ( $rows as $row ) {
            $service_id = (int) $row['service_id'];
            $category   = ServiceCategory::from_row( $row );

            if ( ! isset( $map[ $service_id ] ) ) {
                $map[ $service_id ] = [];
            }

            $map[ $service_id ][] = $category;
        }

        return $map;
    }

    /**
     * {@inheritDoc}
     */
    public function get_tags_for_services( array $service_ids ): array {
        $service_ids = array_values( array_unique( array_map( 'absint', $service_ids ) ) );

        if ( empty( $service_ids ) ) {
            return [];
        }

        $relationships = $this->get_service_tag_relationship_table();
        $tags_table    = $this->get_service_tags_table();

        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

        $sql = sprintf(
            'SELECT st.service_id, t.* FROM %1$s st INNER JOIN %2$s t ON st.tag_id = t.tag_id WHERE st.service_id IN (%3$s) ORDER BY t.name ASC',
            $relationships,
            $tags_table,
            $placeholders
        );

        $prepared = $this->wpdb->prepare( $sql, $service_ids );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $map = [];

        foreach ( $rows as $row ) {
            $service_id = (int) $row['service_id'];
            $tag        = ServiceTag::from_row( $row );

            if ( ! isset( $map[ $service_id ] ) ) {
                $map[ $service_id ] = [];
            }

            $map[ $service_id ][] = $tag;
        }

        return $map;
    }

    /**
     * {@inheritDoc}
     */
    public function get_providers_for_services( array $service_ids ): array {
        $service_ids = array_values( array_unique( array_map( 'absint', $service_ids ) ) );

        if ( empty( $service_ids ) ) {
            return [];
        }

        $table = $this->get_service_provider_table();
        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

        $sql = sprintf(
            'SELECT service_id, employee_id, provider_order FROM %1$s WHERE service_id IN (%2$s) ORDER BY provider_order ASC, employee_id ASC',
            $table,
            $placeholders
        );

        $prepared = $this->wpdb->prepare( $sql, $service_ids );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $map = [];

        foreach ( $rows as $row ) {
            $service_id = (int) $row['service_id'];

            if ( ! isset( $map[ $service_id ] ) ) {
                $map[ $service_id ] = [];
            }

            $map[ $service_id ][] = [
                'employee_id' => (int) $row['employee_id'],
                'order'       => (int) $row['provider_order'],
            ];
        }

        return $map;
    }

    /**
     * {@inheritDoc}
     */
    public function get_service_categories( int $service_id ): array {
        $map = $this->get_categories_for_services( [ $service_id ] );

        return $map[ $service_id ] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function get_service_tags( int $service_id ): array {
        $map = $this->get_tags_for_services( [ $service_id ] );

        return $map[ $service_id ] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function get_service_providers( int $service_id ): array {
        $map = $this->get_providers_for_services( [ $service_id ] );

        return $map[ $service_id ] ?? [];
    }

    /**
     * Determine cache key.
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

    /**
     * Flush caches.
     */
    private function flush_cache(): void {
        wp_cache_delete( self::CACHE_KEY_ACTIVE, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
        wp_cache_delete( self::CACHE_KEY_DELETED, self::CACHE_GROUP );
    }

    /**
     * Services table name.
     */
    private function get_services_table(): string {
        return $this->wpdb->prefix . 'smooth_services';
    }

    /**
     * Categories table name.
     */
    private function get_service_categories_table(): string {
        return $this->wpdb->prefix . 'smooth_service_categories';
    }

    /**
     * Category relationship table.
     */
    private function get_service_category_relationship_table(): string {
        return $this->wpdb->prefix . 'smooth_service_category_relationships';
    }

    /**
     * Tags table name.
     */
    private function get_service_tags_table(): string {
        return $this->wpdb->prefix . 'smooth_service_tags';
    }

    /**
     * Tag relationships table.
     */
    private function get_service_tag_relationship_table(): string {
        return $this->wpdb->prefix . 'smooth_service_tag_relationships';
    }

    /**
     * Provider relationship table.
     */
    private function get_service_provider_table(): string {
        return $this->wpdb->prefix . 'smooth_service_providers';
    }
}
