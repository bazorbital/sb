<?php
/**
 * Contract for persisting business hours.
 *
 * @package SmoothBooking\Domain\BusinessHours
 */

namespace SmoothBooking\Domain\BusinessHours;

use WP_Error;

/**
 * Repository abstraction for business hours.
 */
interface BusinessHoursRepositoryInterface {
    /**
     * Retrieve stored hours for a location.
     *
     * @return BusinessHour[]
     */
    public function get_for_location( int $location_id ): array;

    /**
     * Replace stored hours for a location.
     *
     * @param array<int, array{day:int, open_time:?string, close_time:?string, is_closed:bool}> $hours Normalised day entries.
     *
     * @return true|WP_Error
     */
    public function save_for_location( int $location_id, array $hours );
}
