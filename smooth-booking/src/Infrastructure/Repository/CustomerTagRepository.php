<?php
/**
 * Customer tag repository implementation.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Customers\CustomerTag;
use SmoothBooking\Domain\Customers\CustomerTagRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use const ARRAY_A;
use function __;
use function absint;
use function array_fill;
use function array_filter;
use function array_map;
use function current_time;
use function get_current_blog_id;
use function implode;
use function is_array;
use function sanitize_title;
use function sprintf;
use function trim;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Database repository for customer tags.
 */
class CustomerTagRepository implements CustomerTagRepositoryInterface {
    /**
     * Cache group.
     */
    private const CACHE_GROUP = 'smooth-booking-customer-tags';

    /**
     * Cache ttl.
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
        $cache_key = $this->get_cache_key( 'all' );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $table = $this->get_table_name();

        $rows = $this->wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );

        $tags = array_map(
            static function ( array $row ): CustomerTag {
                return CustomerTag::from_row( $row );
            },
            $rows
        );

        wp_cache_set( $cache_key, $tags, self::CACHE_GROUP, self::CACHE_TTL );

        return $tags;
    }

    /**
     * {@inheritDoc}
     */
    public function create( string $name ) {
        $name = trim( $name );

        if ( '' === $name ) {
            return new WP_Error(
                'smooth_booking_customer_tag_invalid',
                __( 'Tag name cannot be empty.', 'smooth-booking' )
            );
        }

        $slug  = sanitize_title( $name );
        $table = $this->get_table_name();

        $existing = $this->find_by_slug( $slug );

        if ( $existing instanceof CustomerTag ) {
            return $existing;
        }

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Customer tag insert failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_customer_tag_insert_failed',
                __( 'Unable to create tag. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache( $slug );

        return $this->find_by_slug( $slug );
    }

    /**
     * {@inheritDoc}
     */
    public function find_by_ids( array $ids ): array {
        $ids = array_filter( array_map( 'absint', $ids ) );

        if ( empty( $ids ) ) {
            return [];
        }

        $table = $this->get_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE tag_id IN ({$placeholders})",
            ...$ids
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        return array_map(
            static function ( array $row ): CustomerTag {
                return CustomerTag::from_row( $row );
            },
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function find_by_slug( string $slug ): ?CustomerTag {
        $cache_key = $this->get_cache_key( 'slug_' . $slug );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( $cached instanceof CustomerTag ) {
            return $cached;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE slug = %s",
            $slug
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $tag = CustomerTag::from_row( $row );
        wp_cache_set( $cache_key, $tag, self::CACHE_GROUP, self::CACHE_TTL );

        return $tag;
    }

    /**
     * Flush caches.
     */
    private function flush_cache( ?string $slug = null ): void {
        wp_cache_delete( 'all', self::CACHE_GROUP );

        if ( null !== $slug ) {
            wp_cache_delete( $this->get_cache_key( 'slug_' . $slug ), self::CACHE_GROUP );
        }
    }

    /**
     * Table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_customer_tags';
    }

    /**
     * Build cache key.
     */
    private function get_cache_key( string $suffix ): string {
        return sprintf( '%s_%s', get_current_blog_id(), $suffix );
    }
}
