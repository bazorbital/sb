<?php
/**
 * Interface for service persistence.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use WP_Error;

/**
 * Contract for service repositories.
 */
interface ServiceRepositoryInterface {
    /**
     * Retrieve services.
     *
     * @return Service[]
     */
    public function all( bool $include_deleted = false, bool $only_deleted = false ): array;

    /**
     * Find a service.
     */
    public function find( int $service_id );

    /**
     * Find including deleted entries.
     */
    public function find_with_deleted( int $service_id );

    /**
     * Create a service.
     *
     * @param array<string, mixed> $data Data to insert.
     *
     * @return Service|WP_Error
     */
    public function create( array $data );

    /**
     * Update service data.
     *
     * @param array<string, mixed> $data New values.
     *
     * @return Service|WP_Error
     */
    public function update( int $service_id, array $data );

    /**
     * Soft delete service.
     *
     * @return true|WP_Error
     */
    public function soft_delete( int $service_id );

    /**
     * Restore soft deleted service.
     *
     * @return true|WP_Error
     */
    public function restore( int $service_id );

    /**
     * Sync service categories.
     *
     * @param int[] $category_ids Category identifiers.
     */
    public function sync_service_categories( int $service_id, array $category_ids ): bool;

    /**
     * Sync service tags.
     *
     * @param int[] $tag_ids Tag identifiers.
     */
    public function sync_service_tags( int $service_id, array $tag_ids ): bool;

    /**
     * Sync service providers.
     *
     * @param array<int, array{employee_id:int, order:int}> $providers Providers map.
     */
    public function sync_service_providers( int $service_id, array $providers ): bool;

    /**
     * Fetch categories for services.
     *
     * @param int[] $service_ids
     *
     * @return array<int, ServiceCategory[]>
     */
    public function get_categories_for_services( array $service_ids ): array;

    /**
     * Fetch tags for services.
     *
     * @param int[] $service_ids
     *
     * @return array<int, ServiceTag[]>
     */
    public function get_tags_for_services( array $service_ids ): array;

    /**
     * Fetch providers for services.
     *
     * @param int[] $service_ids
     *
     * @return array<int, array<int, array{employee_id:int, order:int}>>
     */
    public function get_providers_for_services( array $service_ids ): array;

    /**
     * Fetch categories for a service.
     *
     * @return ServiceCategory[]
     */
    public function get_service_categories( int $service_id ): array;

    /**
     * Fetch tags for a service.
     *
     * @return ServiceTag[]
     */
    public function get_service_tags( int $service_id ): array;

    /**
     * Fetch providers for a service.
     *
     * @return array<int, array{employee_id:int, order:int}>
     */
    public function get_service_providers( int $service_id ): array;
}
