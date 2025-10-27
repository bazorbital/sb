<?php
/**
 * WP-CLI helpers for managing location holidays.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Holidays\Holiday;
use SmoothBooking\Domain\Holidays\HolidayService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function absint;
use function esc_html__;
use function is_wp_error;
use function sprintf;

/**
 * Provides WP-CLI commands for location holidays.
 */
class HolidaysCommand extends WP_CLI_Command {
    private HolidayService $service;

    public function __construct( HolidayService $service ) {
        $this->service = $service;
    }

    /**
     * List holidays for a location.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Location identifier.
     *
     * [--year=<year>]
     * : Render recurring holidays for a specific year.
     *
     * ## EXAMPLES
     *
     *     wp smooth holidays list 2
     *     wp smooth holidays list 2 --year=2025
     */
    public function list( array $args, array $assoc_args ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyWords
        if ( empty( $args[0] ) ) {
            error( 'Location ID is required.' );
        }

        $location_id = absint( $args[0] );
        $year        = isset( $assoc_args['year'] ) ? absint( $assoc_args['year'] ) : null;

        $result = $this->service->get_location_holidays( $location_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        if ( empty( $result ) ) {
            log( esc_html__( 'No holidays configured for this location.', 'smooth-booking' ) );

            return;
        }

        foreach ( $result as $holiday ) {
            if ( ! $holiday instanceof Holiday ) {
                continue;
            }

            $display_date = $holiday->get_date();

            if ( $holiday->is_recurring() && $year ) {
                $display_date = sprintf( '%04d-%s', $year, $holiday->get_month_day_key() );
            }

            $label = sprintf(
                '#%d %s — %s%s',
                $holiday->get_id(),
                $display_date,
                $holiday->get_note(),
                $holiday->is_recurring() ? ' (recurring)' : ''
            );

            line( $label );
        }
    }

    /**
     * Add a holiday or holiday range for a location.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Location identifier.
     *
     * <start-date>
     * : Start date in YYYY-MM-DD format.
     *
     * [<end-date>]
     * : Optional end date in YYYY-MM-DD format. Defaults to the start date.
     *
     * [--note=<note>]
     * : Optional note to associate with the holiday.
     *
     * [--repeat]
     * : Flag holidays to repeat every year.
     *
     * ## EXAMPLES
     *
     *     wp smooth holidays add 3 2024-12-24 2024-12-26 --note="Christmas" --repeat
     */
    public function add( array $args, array $assoc_args ): void {
        if ( count( $args ) < 2 ) {
            error( 'Location ID and start date are required.' );
        }

        $location_id = absint( $args[0] );
        $start       = $args[1];
        $end         = $args[2] ?? $start;

        $payload = [
            'start_date'   => $start,
            'end_date'     => $end,
            'note'         => $assoc_args['note'] ?? '',
            'is_recurring' => isset( $assoc_args['repeat'] ),
        ];

        $result = $this->service->save_location_holiday( $location_id, $payload );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Holiday saved for location %d.', $location_id ) );
    }

    /**
     * Delete a holiday entry by identifier.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Location identifier.
     *
     * <holiday-id>
     * : Identifier of the holiday to delete.
     *
     * ## EXAMPLES
     *
     *     wp smooth holidays delete 3 42
     */
    public function delete( array $args ): void {
        if ( count( $args ) < 2 ) {
            error( 'Location ID and holiday ID are required.' );
        }

        $location_id = absint( $args[0] );
        $holiday_id  = absint( $args[1] );

        $result = $this->service->delete_location_holiday( $location_id, $holiday_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Holiday #%d deleted for location %d.', $holiday_id, $location_id ) );
    }
}
