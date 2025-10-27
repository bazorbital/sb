<?php
/**
 * Contract for retrieving locations.
 *
 * @package SmoothBooking\Domain\Locations
 */

namespace SmoothBooking\Domain\Locations;

/**
 * Repository abstraction for locations.
 */
interface LocationRepositoryInterface {
    /**
     * List active (non-deleted) locations ordered by name.
     *
     * @return Location[]
     */
    public function list_active(): array;

    /**
     * List locations with optional deleted filters.
     *
     * @param bool $include_deleted Include soft-deleted records.
     * @param bool $only_deleted    Return only deleted records.
     *
     * @return Location[]
     */
    public function all( bool $include_deleted = false, bool $only_deleted = false ): array;

    /**
     * Retrieve a location by identifier.
     */
    public function find( int $location_id ): ?Location;

    /**
     * Retrieve a location including soft-deleted entries.
     */
    public function find_with_deleted( int $location_id ): ?Location;

    /**
     * Persist a new location.
     *
     * @param array<string, mixed> $data Sanitised payload.
     *
     * @return Location|\WP_Error
     */
    public function create( array $data );

    /**
     * Update an existing location.
     *
     * @param int                   $location_id Location identifier.
     * @param array<string, mixed> $data        Sanitised payload.
     *
     * @return Location|\WP_Error
     */
    public function update( int $location_id, array $data );

    /**
     * Soft delete a location.
     *
     * @return true|\WP_Error
     */
    public function soft_delete( int $location_id );

    /**
     * Restore a soft deleted location.
     *
     * @return true|\WP_Error
     */
    public function restore( int $location_id );
}
