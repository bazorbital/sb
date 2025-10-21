<?php
/**
 * Interface for service tag persistence.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use WP_Error;

/**
 * Contract for service tags.
 */
interface ServiceTagRepositoryInterface {
    /**
     * Retrieve all tags.
     *
     * @return ServiceTag[]
     */
    public function all(): array;

    /**
     * Find tag.
     */
    public function find( int $tag_id );

    /**
     * Find by name.
     */
    public function find_by_name( string $name ): ?ServiceTag;

    /**
     * Create tag.
     *
     * @return ServiceTag|WP_Error
     */
    public function create( string $name );
}
