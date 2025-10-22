<?php
/**
 * Repository contract for customer tags.
 *
 * @package SmoothBooking\Domain\Customers
 */

namespace SmoothBooking\Domain\Customers;

use WP_Error;

/**
 * Provides persistence for customer tags.
 */
interface CustomerTagRepositoryInterface {
    /**
     * Retrieve all tags.
     *
     * @return CustomerTag[]
     */
    public function all(): array;

    /**
     * Create a new tag.
     *
     * @return CustomerTag|WP_Error
     */
    public function create( string $name );

    /**
     * Find tags by identifiers.
     *
     * @param int[] $ids Tag identifiers.
     *
     * @return CustomerTag[]
     */
    public function find_by_ids( array $ids ): array;

    /**
     * Locate tag by slug.
     */
    public function find_by_slug( string $slug ): ?CustomerTag;
}
