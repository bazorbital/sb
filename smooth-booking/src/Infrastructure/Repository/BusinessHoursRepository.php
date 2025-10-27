<?php
/**
 * wpdb-backed repository for business hours.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\BusinessHours\BusinessHour;
use SmoothBooking\Domain\BusinessHours\BusinessHoursRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;
use const ARRAY_A;
use function __;
use function is_array;
use function sprintf;

/**
 * Persists business hour templates in the smooth_opening_hours table.
 */
class BusinessHoursRepository implements BusinessHoursRepositoryInterface {
    private wpdb $wpdb;

    private Logger $logger;

    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function get_for_location( int $location_id ): array {
        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE location_id = %d AND is_deleted = %d ORDER BY day_of_week ASC",
            $location_id,
            0
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $results ) ) {
            return [];
        }

        return array_map(
            static function ( array $row ): BusinessHour {
                return BusinessHour::from_row( $row );
            },
            $results
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save_for_location( int $location_id, array $hours ) {
        $table = $this->get_table_name();

        $deleted = $this->wpdb->delete( $table, [ 'location_id' => $location_id ], [ '%d' ] );

        if ( false === $deleted ) {
            $this->logger->error( sprintf( 'Failed deleting existing opening hours for location %d: %s', $location_id, $this->wpdb->last_error ) );

            return new WP_Error(
                'smooth_booking_business_hours_delete_failed',
                __( 'Unable to reset existing business hours before saving.', 'smooth-booking' )
            );
        }

        foreach ( $hours as $hour ) {
            $is_closed = ! empty( $hour['is_closed'] );

            $data = [
                'location_id' => $location_id,
                'day_of_week' => $hour['day'],
                'open_time'   => $is_closed ? '00:00:00' : $hour['open_time'],
                'close_time'  => $is_closed ? '00:00:00' : $hour['close_time'],
                'is_closed'   => $is_closed ? 1 : 0,
                'is_deleted'  => 0,
            ];

            $formats = [ '%d', '%d', '%s', '%s', '%d', '%d' ];

            $inserted = $this->wpdb->insert( $table, $data, $formats );

            if ( false === $inserted ) {
                $this->logger->error( sprintf( 'Failed inserting business hour for location %d: %s', $location_id, $this->wpdb->last_error ) );

                return new WP_Error(
                    'smooth_booking_business_hours_insert_failed',
                    __( 'Unable to save the provided business hours.', 'smooth-booking' )
                );
            }
        }

        return true;
    }

    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_opening_hours';
    }
}
