<?php
/**
 * Contract for persisting location holidays.
 *
 * @package SmoothBooking\Domain\Holidays
 */

namespace SmoothBooking\Domain\Holidays;

use WP_Error;

/**
 * Repository abstraction for location holiday records.
 */
interface HolidayRepositoryInterface {
    /**
     * Retrieve holidays for the provided location.
     *
     * @return Holiday[]
     */
    public function list_for_location( int $location_id ): array;

    /**
     * Persist multiple holidays for a location (inserts or updates by unique key).
     *
     * @param array<int, array{date:string,note:string,is_recurring:bool}> $holidays Holiday payloads.
     *
     * @return true|WP_Error
     */
    public function save_range( int $location_id, array $holidays );

    /**
     * Delete a holiday entry.
     */
    public function delete( int $holiday_id, int $location_id );
}
