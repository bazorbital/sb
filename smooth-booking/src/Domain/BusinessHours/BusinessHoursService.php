<?php
/**
 * Domain service for managing business hours templates.
 *
 * @package SmoothBooking\Domain\BusinessHours
 */

namespace SmoothBooking\Domain\BusinessHours;

use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use function __;
use function absint;
use function array_key_exists;
use function explode;
use function is_array;
use function is_wp_error;
use function rest_sanitize_boolean;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Provides validation and orchestration for business hours.
 */
class BusinessHoursService {
    /**
     * @var BusinessHoursRepositoryInterface
     */
    private BusinessHoursRepositoryInterface $repository;

    /**
     * @var LocationRepositoryInterface
     */
    private LocationRepositoryInterface $location_repository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Day map (1 = Monday ... 7 = Sunday).
     *
     * @var array<int, string>
     */
    private array $days = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

    /**
     * Constructor.
     */
    public function __construct( BusinessHoursRepositoryInterface $repository, LocationRepositoryInterface $location_repository, Logger $logger ) {
        $this->repository           = $repository;
        $this->location_repository  = $location_repository;
        $this->logger               = $logger;
    }

    /**
     * Retrieve the days of the week definitions.
     *
     * @return array<int, array{key:string,label:string}>
     */
    public function get_days(): array {
        return [
            1 => [ 'key' => 'monday', 'label' => __( 'Monday', 'smooth-booking' ) ],
            2 => [ 'key' => 'tuesday', 'label' => __( 'Tuesday', 'smooth-booking' ) ],
            3 => [ 'key' => 'wednesday', 'label' => __( 'Wednesday', 'smooth-booking' ) ],
            4 => [ 'key' => 'thursday', 'label' => __( 'Thursday', 'smooth-booking' ) ],
            5 => [ 'key' => 'friday', 'label' => __( 'Friday', 'smooth-booking' ) ],
            6 => [ 'key' => 'saturday', 'label' => __( 'Saturday', 'smooth-booking' ) ],
            7 => [ 'key' => 'sunday', 'label' => __( 'Sunday', 'smooth-booking' ) ],
        ];
    }

    /**
     * Retrieve available time slots in HH:MM format (15 minute resolution).
     *
     * @return array<string, string>
     */
    public function get_time_options(): array {
        $options = [];

        for ( $hour = 0; $hour < 24; $hour++ ) {
            for ( $minute = 0; $minute < 60; $minute += 15 ) {
                $time            = sprintf( '%02d:%02d', $hour, $minute );
                $options[ $time ] = $time;
            }
        }

        return $options;
    }

    /**
     * List active locations.
     *
     * @return Location[]
     */
    public function list_locations(): array {
        return $this->location_repository->list_active();
    }

    /**
     * Retrieve business hours for the provided location.
     *
     * @return array<int, array{open:string, close:string, is_closed:bool}>
     */
    public function get_location_hours( int $location_id ) {
        $location = $this->location_repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        $template = $this->get_empty_template();

        $records = $this->repository->get_for_location( $location_id );

        foreach ( $records as $record ) {
            $day = $record->get_day_of_week();

            if ( ! array_key_exists( $day, $template ) ) {
                continue;
            }

            $template[ $day ] = [
                'open'      => $record->get_open_time() ?? '',
                'close'     => $record->get_close_time() ?? '',
                'is_closed' => $record->is_closed(),
            ];
        }

        return $template;
    }

    /**
     * Persist business hours for a location.
     *
     * @param array<int, mixed> $submitted Submitted form data.
     *
     * @return true|WP_Error
     */
    public function save_location_hours( int $location_id, array $submitted ) {
        $location = $this->location_repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_hours_payload( $submitted );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->save_for_location( $location_id, $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed saving business hours: ' . $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Build a default day template.
     *
     * @return array<int, array{open:string, close:string, is_closed:bool}>
     */
    public function get_empty_template(): array {
        $template = [];

        foreach ( $this->days as $index => $key ) {
            $template[ $index ] = [
                'open'      => '',
                'close'     => '',
                'is_closed' => true,
            ];
        }

        return $template;
    }

    /**
     * Validate submitted hours payload.
     *
     * @param array<int, mixed> $submitted Submitted array.
     *
     * @return array<int, array{day:int, open_time:?string, close_time:?string, is_closed:bool}>|WP_Error
     */
    private function validate_hours_payload( array $submitted ) {
        $allowed_times = $this->get_time_options();
        $payload       = [];

        foreach ( $this->days as $day => $slug ) {
            $raw = [];

            if ( array_key_exists( $day, $submitted ) && is_array( $submitted[ $day ] ) ) {
                $raw = wp_unslash( $submitted[ $day ] );
            }

            $is_closed = rest_sanitize_boolean( $raw['is_closed'] ?? false );
            $open      = isset( $raw['open'] ) ? sanitize_text_field( (string) $raw['open'] ) : '';
            $close     = isset( $raw['close'] ) ? sanitize_text_field( (string) $raw['close'] ) : '';

            if ( $is_closed || ( '' === $open && '' === $close ) ) {
                $payload[] = [
                    'day'        => $day,
                    'open_time'  => null,
                    'close_time' => null,
                    'is_closed'  => true,
                ];

                continue;
            }

            if ( '' === $open || '' === $close ) {
                return new WP_Error(
                    'smooth_booking_business_hours_missing_time',
                    __( 'Please select both opening and closing times for each open day.', 'smooth-booking' )
                );
            }

            if ( ! isset( $allowed_times[ $open ] ) || ! isset( $allowed_times[ $close ] ) ) {
                return new WP_Error(
                    'smooth_booking_business_hours_invalid_time',
                    __( 'Please choose valid times from the dropdown list.', 'smooth-booking' )
                );
            }

            $open_seconds  = $this->convert_to_seconds( $open );
            $close_seconds = $this->convert_to_seconds( $close );

            if ( $close_seconds <= $open_seconds ) {
                return new WP_Error(
                    'smooth_booking_business_hours_order',
                    __( 'Closing time must be later than opening time.', 'smooth-booking' )
                );
            }

            $payload[] = [
                'day'        => $day,
                'open_time'  => $open . ':00',
                'close_time' => $close . ':00',
                'is_closed'  => false,
            ];
        }

        return $payload;
    }

    /**
     * Convert HH:MM to seconds.
     */
    private function convert_to_seconds( string $time ): int {
        $parts = explode( ':', $time );
        $hour  = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
        $min   = isset( $parts[1] ) ? absint( $parts[1] ) : 0;

        return ( $hour * 60 * 60 ) + ( $min * 60 );
    }
}
