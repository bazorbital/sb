<?php
/**
 * Provides access to general plugin settings.
 *
 * @package SmoothBooking\Infrastructure\Settings
 */

namespace SmoothBooking\Infrastructure\Settings;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;

use function __;
use function absint;
use function get_option;
use function in_array;
use function max;
use function sanitize_key;
use function sprintf;
use function wp_parse_args;
use function wp_timezone;

/**
 * Encapsulates option access for general plugin settings.
 */
class GeneralSettings {
    /**
     * Option name for persisted settings.
     */
    public const OPTION_NAME = 'smooth_booking_settings';

    /**
     * Default values for general settings.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'auto_repair_schema'   => 1,
        'time_slot_length'     => 30,
        'enable_debug_logging' => 0,
    ];

    /**
     * Allowed slot length values in minutes.
     *
     * @var int[]
     */
    private const ALLOWED_SLOT_LENGTHS = [ 5, 10, 15, 20, 30, 45, 60, 90, 120 ];

    /**
     * Retrieve all stored settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function get_all(): array {
        $option = get_option( self::OPTION_NAME, [] );

        if ( ! is_array( $option ) ) {
            $option = [];
        }

        return wp_parse_args( $option, self::DEFAULTS );
    }

    /**
     * Retrieve the configured time slot length in minutes.
     */
    public function get_time_slot_length(): int {
        $settings = $this->get_all();
        $length   = isset( $settings['time_slot_length'] ) ? absint( $settings['time_slot_length'] ) : self::DEFAULTS['time_slot_length'];

        if ( ! in_array( $length, self::ALLOWED_SLOT_LENGTHS, true ) ) {
            $length = self::DEFAULTS['time_slot_length'];
        }

        return max( 5, $length );
    }

    /**
     * Return available time slot length options for rendering.
     *
     * @return array<int, string>
     */
    public function get_time_slot_length_options(): array {
        $options = [];

        foreach ( self::ALLOWED_SLOT_LENGTHS as $minutes ) {
            $options[ $minutes ] = sprintf( /* translators: %d: number of minutes. */ __( '%d minutes', 'smooth-booking' ), $minutes );
        }

        return $options;
    }

    /**
     * Sanitize an incoming settings payload.
     *
     * @param array<string, mixed> $input Raw option values.
     *
     * @return array<string, mixed>
     */
    public function sanitize( array $input ): array {
        $sanitized = self::DEFAULTS;

        if ( isset( $input['auto_repair_schema'] ) ) {
            $sanitized['auto_repair_schema'] = empty( $input['auto_repair_schema'] ) ? 0 : 1;
        }

        if ( isset( $input['enable_debug_logging'] ) ) {
            $sanitized['enable_debug_logging'] = empty( $input['enable_debug_logging'] ) ? 0 : 1;
        }

        if ( isset( $input['time_slot_length'] ) ) {
            $length = absint( $input['time_slot_length'] );

            if ( in_array( $length, self::ALLOWED_SLOT_LENGTHS, true ) ) {
                $sanitized['time_slot_length'] = $length;
            }
        }

        return $sanitized;
    }

    /**
     * Determine whether debug logging is enabled.
     */
    public function is_debug_logging_enabled(): bool {
        $settings = $this->get_all();

        return ! empty( $settings['enable_debug_logging'] );
    }

    /**
     * Retrieve 24 hour time slots using the configured granularity.
     *
     * @return string[]
     */
    public function get_time_slots(): array {
        $length   = $this->get_time_slot_length();
        $timezone = $this->get_timezone();
        $start    = new DateTimeImmutable( 'today midnight', $timezone );
        $end      = $start->modify( '+1 day' );

        $slots  = [];
        $period = new DatePeriod( $start, new DateInterval( sprintf( 'PT%dM', $length ) ), $end );

        foreach ( $period as $date ) {
            $slots[] = $date->format( 'H:i' );
        }

        return $slots;
    }

    /**
     * Generate time slots between the provided open and close datetimes.
     *
     * @param DateTimeImmutable $open  Opening datetime.
     * @param DateTimeImmutable $close Closing datetime.
     *
     * @return string[]
     */
    public function get_slots_for_range( DateTimeImmutable $open, DateTimeImmutable $close ): array {
        $length = $this->get_time_slot_length();

        if ( $close <= $open ) {
            return [];
        }

        $slots  = [];
        $period = new DatePeriod( $open, new DateInterval( sprintf( 'PT%dM', $length ) ), $close );

        foreach ( $period as $slot ) {
            $slots[] = $slot->format( 'H:i' );
        }

        return $slots;
    }

    /**
     * Normalize a submitted settings key.
     */
    public function normalize_key( string $key ): string {
        return sanitize_key( $key );
    }

    /**
     * Retrieve the timezone configured for the site.
     */
    private function get_timezone(): DateTimeZone {
        $timezone = wp_timezone();

        return $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( 'UTC' );
    }
}
