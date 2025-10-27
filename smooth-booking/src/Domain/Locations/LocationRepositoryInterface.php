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
     * Retrieve a location by identifier.
     */
    public function find( int $location_id ): ?Location;
}
