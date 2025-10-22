<?php
/**
 * Customer repository implementation.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerRepositoryInterface;
use SmoothBooking\Domain\Customers\CustomerTag;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use const ARRAY_A;
use function __;
use function absint;
use function array_fill;
use function array_filter;
use function array_map;
use function array_merge;
use function current_time;
use function get_current_blog_id;
use function implode;
use function is_array;
use function max;
use function sprintf;
use function strtoupper;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;

/**
 * Handles database operations for customers.
 */
class CustomerRepository implements CustomerRepositoryInterface {
    /**
     * Cache group name.
     */
    private const CACHE_GROUP = 'smooth-booking-customers';

    /**
     * Cache lifetime in seconds.
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
    public function paginate( array $args = [] ): array {
        $table = $this->get_table_name();

        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $orderby = $this->sanitize_orderby( (string) ( $args['orderby'] ?? 'name' ) );
        $order   = strtoupper( (string) ( $args['order'] ?? 'ASC' ) );
        $order   = 'DESC' === $order ? 'DESC' : 'ASC';

        $conditions = [];
        $params     = [];

        if ( ! empty( $args['only_deleted'] ) ) {
            $conditions[] = 'is_deleted = %d';
            $params[]     = 1;
        } elseif ( empty( $args['include_deleted'] ) ) {
            $conditions[] = 'is_deleted = %d';
            $params[]     = 0;
        }

        if ( ! empty( $args['search'] ) ) {
            $search      = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
            $conditions[] = '(name LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params       = array_merge( $params, [ $search, $search, $search, $search, $search ] );
        }

        $where = '';

        if ( ! empty( $conditions ) ) {
            $where = 'WHERE ' . implode( ' AND ', $conditions );
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";

        if ( ! empty( $params ) ) {
            $count_sql = $this->wpdb->prepare( $count_sql, ...$params );
        }

        $total = (int) $this->wpdb->get_var( $count_sql );

        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_params = $params;
        $query_params[] = $per_page;
        $query_params[] = $offset;

        $sql = $this->wpdb->prepare( $sql, ...$query_params );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $results ) ) {
            $results = [];
        }

        $customers = array_map(
            static function ( array $row ): Customer {
                return Customer::from_row( $row );
            },
            $results
        );

        return [
            'customers' => $customers,
            'total'     => $total,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $customer_id ) {
        $cache_key = $this->get_cache_key( 'customer_' . $customer_id );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( $cached instanceof Customer ) {
            return $cached;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_id = %d AND is_deleted = %d",
            $customer_id,
            0
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $customer = Customer::from_row( $row );
        wp_cache_set( $cache_key, $customer, self::CACHE_GROUP, self::CACHE_TTL );

        return $customer;
    }

    /**
     * {@inheritDoc}
     */
    public function find_with_deleted( int $customer_id ) {
        $cache_key = $this->get_cache_key( 'customer_with_deleted_' . $customer_id );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( $cached instanceof Customer ) {
            return $cached;
        }

        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_id = %d",
            $customer_id
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $customer = Customer::from_row( $row );
        wp_cache_set( $cache_key, $customer, self::CACHE_GROUP, self::CACHE_TTL );

        return $customer;
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $table = $this->get_table_name();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'name'               => $data['name'],
                'user_id'            => $data['user_id'] ?: null,
                'profile_image_id'   => $data['profile_image_id'] ?: null,
                'first_name'         => $data['first_name'],
                'last_name'          => $data['last_name'],
                'phone'              => $data['phone'],
                'email'              => $data['email'],
                'date_of_birth'      => $data['date_of_birth'],
                'country'            => $data['country'],
                'state_region'       => $data['state_region'],
                'postal_code'        => $data['postal_code'],
                'city'               => $data['city'],
                'street_address'     => $data['street_address'],
                'additional_address' => $data['additional_address'],
                'street_number'      => $data['street_number'],
                'notes'              => $data['notes'],
                'created_at'         => current_time( 'mysql' ),
                'updated_at'         => current_time( 'mysql' ),
            ],
            [
                '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Customer insert failed: %s', wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_customer_insert_failed',
                __( 'Unable to create customer. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache( (int) $this->wpdb->insert_id );

        return $this->find_with_deleted( (int) $this->wpdb->insert_id );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $customer_id, array $data ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'name'               => $data['name'],
                'user_id'            => $data['user_id'] ?: null,
                'profile_image_id'   => $data['profile_image_id'] ?: null,
                'first_name'         => $data['first_name'],
                'last_name'          => $data['last_name'],
                'phone'              => $data['phone'],
                'email'              => $data['email'],
                'date_of_birth'      => $data['date_of_birth'],
                'country'            => $data['country'],
                'state_region'       => $data['state_region'],
                'postal_code'        => $data['postal_code'],
                'city'               => $data['city'],
                'street_address'     => $data['street_address'],
                'additional_address' => $data['additional_address'],
                'street_number'      => $data['street_number'],
                'notes'              => $data['notes'],
                'updated_at'         => current_time( 'mysql' ),
            ],
            [ 'customer_id' => $customer_id ],
            [
                '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Customer update failed for #%d: %s', $customer_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_customer_update_failed',
                __( 'Unable to update customer. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache( $customer_id );

        return $this->find_with_deleted( $customer_id );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $customer_id ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 1,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'customer_id' => $customer_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Customer delete failed for #%d: %s', $customer_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_customer_delete_failed',
                __( 'Unable to delete customer. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache( $customer_id );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function restore( int $customer_id ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'customer_id' => $customer_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Customer restore failed for #%d: %s', $customer_id, wp_json_encode( $this->wpdb->last_error ) ) );

            return new WP_Error(
                'smooth_booking_customer_restore_failed',
                __( 'Unable to restore customer. Please try again.', 'smooth-booking' )
            );
        }

        $this->flush_cache( $customer_id );

        $customer = $this->find_with_deleted( $customer_id );

        if ( null === $customer ) {
            return new WP_Error(
                'smooth_booking_customer_restore_missing',
                __( 'Unable to locate the customer after restore.', 'smooth-booking' )
            );
        }

        return $customer;
    }

    /**
     * {@inheritDoc}
     */
    public function sync_tags( int $customer_id, array $tag_ids ): void {
        $table = $this->get_tags_relationship_table();

        $this->wpdb->delete( $table, [ 'customer_id' => $customer_id ], [ '%d' ] );

        foreach ( $tag_ids as $tag_id ) {
            $this->wpdb->insert(
                $table,
                [
                    'customer_id' => $customer_id,
                    'tag_id'      => absint( $tag_id ),
                ],
                [ '%d', '%d' ]
            );
        }

        $this->flush_cache( $customer_id );
    }

    /**
     * {@inheritDoc}
     */
    public function get_tags_for_customers( array $customer_ids ): array {
        $customer_ids = array_filter( array_map( 'absint', $customer_ids ) );

        if ( empty( $customer_ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );
        $table_rel    = $this->get_tags_relationship_table();
        $table_tags   = $this->get_tags_table();

        $sql = $this->wpdb->prepare(
            "SELECT rel.customer_id, tags.* FROM {$table_rel} rel INNER JOIN {$table_tags} tags ON rel.tag_id = tags.tag_id WHERE rel.customer_id IN ({$placeholders})",
            ...$customer_ids
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        $grouped = [];

        foreach ( $rows as $row ) {
            $customer_id = (int) $row['customer_id'];
            $grouped[ $customer_id ][] = CustomerTag::from_row( $row );
        }

        return $grouped;
    }

    /**
     * Get the customers table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_customers';
    }

    /**
     * Get the tags relationship table name.
     */
    private function get_tags_relationship_table(): string {
        return $this->wpdb->prefix . 'smooth_customer_tag_relationships';
    }

    /**
     * Get the tags table name.
     */
    private function get_tags_table(): string {
        return $this->wpdb->prefix . 'smooth_customer_tags';
    }

    /**
     * Flush cache entries.
     */
    private function flush_cache( ?int $customer_id = null ): void {
        if ( null !== $customer_id ) {
            wp_cache_delete( $this->get_cache_key( 'customer_' . $customer_id ), self::CACHE_GROUP );
            wp_cache_delete( $this->get_cache_key( 'customer_with_deleted_' . $customer_id ), self::CACHE_GROUP );
        }

        wp_cache_delete( 'all', self::CACHE_GROUP );
    }

    /**
     * Generate cache key.
     */
    private function get_cache_key( string $suffix ): string {
        return sprintf( '%s_%s', get_current_blog_id(), $suffix );
    }

    /**
     * Sanitize orderby parameter.
     */
    private function sanitize_orderby( string $orderby ): string {
        $allowed = [
            'id'                 => 'customer_id',
            'name'               => 'name',
            'email'              => 'email',
            'phone'              => 'phone',
            'last_appointment'   => 'last_appointment_at',
            'total_appointments' => 'total_appointments',
            'total_payments'     => 'total_payments',
            'created_at'         => 'created_at',
        ];

        return $allowed[ $orderby ] ?? 'name';
    }
}
