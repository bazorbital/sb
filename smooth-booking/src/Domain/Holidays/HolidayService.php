<?php
/**
 * Domain service handling holiday validation and persistence.
 *
 * @package SmoothBooking\Domain\Holidays
 */

namespace SmoothBooking\Domain\Holidays;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use function __;
use function absint;
use function apply_filters;
use function do_action;
use function function_exists;
use function is_wp_error;
use function mb_strlen;
use function sanitize_textarea_field;
use function sanitize_text_field;
use function strlen;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;

/**
 * Provides orchestration for location holidays.
 */
class HolidayService {
    private const CACHE_GROUP = 'smooth_booking_holidays';

    private const MAX_RANGE_DAYS = 366;

    private HolidayRepositoryInterface $repository;

    private LocationRepositoryInterface $location_repository;

    private Logger $logger;

    public function __construct( HolidayRepositoryInterface $repository, LocationRepositoryInterface $location_repository, Logger $logger ) {
        $this->repository          = $repository;
        $this->location_repository = $location_repository;
        $this->logger              = $logger;
    }

    /**
     * Retrieve holidays for a location.
     *
     * @return Holiday[]|WP_Error
     */
    public function get_location_holidays( int $location_id ) {
        $location = $this->location_repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        $cache_key = $this->get_cache_key( $location_id );
        $cached    = $this->cache_get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $records = $this->repository->list_for_location( $location_id );
        $this->cache_set( $cache_key, $records );

        return $records;
    }

    /**
     * Persist a holiday or holiday range for the provided location.
     *
     * @param array<string, mixed> $submitted Submitted payload.
     */
    public function save_location_holiday( int $location_id, array $submitted ) {
        $location = $this->location_repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        $start_raw = isset( $submitted['start_date'] ) ? sanitize_text_field( (string) $submitted['start_date'] ) : '';
        $end_raw   = isset( $submitted['end_date'] ) ? sanitize_text_field( (string) $submitted['end_date'] ) : '';
        $repeat    = ! empty( $submitted['is_recurring'] );

        if ( '' === $start_raw || '' === $end_raw ) {
            return new WP_Error(
                'smooth_booking_holiday_missing_date',
                __( 'Select a start and end date for the holiday.', 'smooth-booking' )
            );
        }

        $start = DateTimeImmutable::createFromFormat( 'Y-m-d', $start_raw );
        $end   = DateTimeImmutable::createFromFormat( 'Y-m-d', $end_raw );

        if ( ! $start || $start->format( 'Y-m-d' ) !== $start_raw || ! $end || $end->format( 'Y-m-d' ) !== $end_raw ) {
            return new WP_Error(
                'smooth_booking_holiday_invalid_date',
                __( 'Provide valid dates in the YYYY-MM-DD format.', 'smooth-booking' )
            );
        }

        if ( $end < $start ) {
            return new WP_Error(
                'smooth_booking_holiday_range',
                __( 'The end date must be after or equal to the start date.', 'smooth-booking' )
            );
        }

        $note = isset( $submitted['note'] ) ? sanitize_textarea_field( (string) $submitted['note'] ) : '';
        $note = '' === $note ? __( 'We are not working on this day', 'smooth-booking' ) : $note;

        $note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $note ) : strlen( $note );

        if ( $note_length > 255 ) {
            return new WP_Error(
                'smooth_booking_holiday_note_length',
                __( 'Please enter a note shorter than 255 characters.', 'smooth-booking' )
            );
        }

        $days = $this->expand_range( $start, $end );

        if ( is_wp_error( $days ) ) {
            return $days;
        }

        $payload = [];

        foreach ( $days as $date ) {
            $payload[] = [
                'date'         => $date,
                'note'         => $note,
                'is_recurring' => $repeat,
            ];
        }

        $payload = apply_filters( 'smooth_booking_holiday_payload', $payload, $location_id, $submitted );

        $result = $this->repository->save_range( $location_id, $payload );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed saving holidays: ' . $result->get_error_message() );

            return $result;
        }

        $this->cache_delete( $this->get_cache_key( $location_id ) );

        /**
         * Fires after holidays were saved for a location.
         *
         * @since 0.9.0
         *
         * @param int   $location_id Location identifier.
         * @param array $payload     Saved payload array.
         */
        do_action( 'smooth_booking_location_holidays_saved', $location_id, $payload );

        return true;
    }

    /**
     * Delete a holiday entry.
     */
    public function delete_location_holiday( int $location_id, int $holiday_id ) {
        $location = $this->location_repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        if ( $holiday_id <= 0 ) {
            return new WP_Error(
                'smooth_booking_holiday_invalid',
                __( 'Select a valid holiday to delete.', 'smooth-booking' )
            );
        }

        $result = $this->repository->delete( $holiday_id, $location_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed deleting holiday: ' . $result->get_error_message() );

            return $result;
        }

        $this->cache_delete( $this->get_cache_key( $location_id ) );

        /**
         * Fires after a holiday entry has been removed.
         *
         * @since 0.9.0
         *
         * @param int $location_id Location identifier.
         * @param int $holiday_id  Deleted holiday identifier.
         */
        do_action( 'smooth_booking_location_holiday_deleted', $location_id, $holiday_id );

        return true;
    }

    /**
     * Expand a date range to individual days.
     *
     * @return string[]|WP_Error
     */
    private function expand_range( DateTimeImmutable $start, DateTimeImmutable $end ) {
        $interval = new DateInterval( 'P1D' );
        $period   = new DatePeriod( $start, $interval, $end->add( $interval ) );

        $dates = [];
        $count = 0;

        foreach ( $period as $date ) {
            $dates[] = $date->format( 'Y-m-d' );
            $count++;

            if ( $count > self::MAX_RANGE_DAYS ) {
                return new WP_Error(
                    'smooth_booking_holiday_range_limit',
                    __( 'Holiday ranges are limited to one year.', 'smooth-booking' )
                );
            }
        }

        return $dates;
    }

    private function get_cache_key( int $location_id ): string {
        return 'location_' . absint( $location_id );
    }

    /**
     * Retrieve a cached value if available.
     *
     * @return Holiday[]|false
     */
    private function cache_get( string $key ) {
        if ( function_exists( 'wp_cache_get' ) ) {
            $cached = wp_cache_get( $key, self::CACHE_GROUP );

            if ( false !== $cached ) {
                return $cached;
            }
        }

        return false;
    }

    /**
     * Store a value in cache.
     *
     * @param Holiday[] $value Cached holidays.
     */
    private function cache_set( string $key, array $value ): void {
        if ( function_exists( 'wp_cache_set' ) ) {
            $ttl = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
            wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );
        }
    }

    private function cache_delete( string $key ): void {
        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( $key, self::CACHE_GROUP );
        }
    }
}
