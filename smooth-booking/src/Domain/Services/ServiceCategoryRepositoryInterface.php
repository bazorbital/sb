<?php
/**
 * Interface for service category persistence.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use WP_Error;

/**
 * Contract for managing service categories.
 */
interface ServiceCategoryRepositoryInterface {
    /**
     * Retrieve all categories.
     *
     * @return ServiceCategory[]
     */
    public function all(): array;

    /**
     * Find category by id.
     */
    public function find( int $category_id );

    /**
     * Find by name.
     */
    public function find_by_name( string $name ): ?ServiceCategory;

    /**
     * Create category.
     *
     * @return ServiceCategory|WP_Error
     */
    public function create( string $name );
}
