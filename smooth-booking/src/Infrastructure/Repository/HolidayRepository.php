<?php
/**
 * wpdb-backed repository for location holidays.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Holidays\Holiday;
use SmoothBooking\Domain\Holidays\HolidayRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use wpdb;
use const ARRAY_A;
use function __;
use function is_array;
use function sprintf;

/**
 * Persist and retrieve location holiday entries.
 */
class HolidayRepository implements HolidayRepositoryInterface {
    private wpdb $wpdb;

    private Logger $logger;

    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function list_for_location( int $location_id ): array {
        $table = $this->get_table_name();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE location_id = %d ORDER BY holiday_date ASC",
            $location_id
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map(
            static function ( array $row ): Holiday {
                return Holiday::from_row( $row );
            },
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save_range( int $location_id, array $holidays ) {
        $table = $this->get_table_name();

        foreach ( $holidays as $holiday ) {
            $data = [
                'location_id'   => $location_id,
                'holiday_date'  => $holiday['date'],
                'note'          => $holiday['note'],
                'is_recurring'  => $holiday['is_recurring'] ? 1 : 0,
                'is_deleted'    => 0,
            ];

            $formats = [ '%d', '%s', '%s', '%d', '%d' ];

            $result = $this->wpdb->replace( $table, $data, $formats );

            if ( false === $result ) {
                $this->logger->error( sprintf( 'Failed inserting holiday for location %d: %s', $location_id, $this->wpdb->last_error ) );

                return new WP_Error(
                    'smooth_booking_holiday_insert_failed',
                    __( 'Unable to save the provided holiday.', 'smooth-booking' )
                );
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete( int $holiday_id, int $location_id ) {
        $table = $this->get_table_name();

        $result = $this->wpdb->delete(
            $table,
            [
                'holiday_id'  => $holiday_id,
                'location_id' => $location_id,
            ],
            [ '%d', '%d' ]
        );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed deleting holiday %d for location %d: %s', $holiday_id, $location_id, $this->wpdb->last_error ) );

            return new WP_Error(
                'smooth_booking_holiday_delete_failed',
                __( 'Unable to delete the selected holiday.', 'smooth-booking' )
            );
        }

        return true;
    }

    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_location_holidays';
    }
}
