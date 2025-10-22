<?php
/**
 * Repository contract for customers.
 *
 * @package SmoothBooking\Domain\Customers
 */

namespace SmoothBooking\Domain\Customers;

use WP_Error;

/**
 * Defines persistence methods for customers.
 */
interface CustomerRepositoryInterface {
    /**
     * Paginate customers.
     *
     * @param array<string, mixed> $args Query arguments.
     *
     * @return array{customers: Customer[], total: int}
     */
    public function paginate( array $args = [] ): array;

    /**
     * Retrieve a single customer.
     */
    public function find( int $customer_id );

    /**
     * Retrieve a customer including deleted entries.
     */
    public function find_with_deleted( int $customer_id );

    /**
     * Create a new customer.
     *
     * @param array<string, mixed> $data Customer data.
     *
     * @return Customer|WP_Error
     */
    public function create( array $data );

    /**
     * Update a customer.
     *
     * @param array<string, mixed> $data Customer data.
     *
     * @return Customer|WP_Error
     */
    public function update( int $customer_id, array $data );

    /**
     * Soft delete a customer.
     *
     * @return true|WP_Error
     */
    public function soft_delete( int $customer_id );

    /**
     * Restore a soft deleted customer.
     *
     * @return Customer|WP_Error
     */
    public function restore( int $customer_id );

    /**
     * Sync tags for a customer.
     *
     * @param int[] $tag_ids Tag identifiers.
     */
    public function sync_tags( int $customer_id, array $tag_ids ): void;

    /**
     * Retrieve tags for customers.
     *
     * @param int[] $customer_ids Customer identifiers.
     *
     * @return array<int, CustomerTag[]>
     */
    public function get_tags_for_customers( array $customer_ids ): array;
}
