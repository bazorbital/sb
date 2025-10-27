<?php
/**
 * Value object representing a single business hour entry.
 *
 * @package SmoothBooking\Domain\BusinessHours
 */

namespace SmoothBooking\Domain\BusinessHours;

/**
 * Immutable business hour record.
 */
class BusinessHour {
    /**
     * Location identifier.
     */
    private int $location_id;

    /**
     * Day of week (1 = Monday, 7 = Sunday).
     */
    private int $day_of_week;

    /**
     * Opening time (HH:MM:SS) or null when closed.
     */
    private ?string $open_time;

    /**
     * Closing time (HH:MM:SS) or null when closed.
     */
    private ?string $close_time;

    /**
     * Whether the business is closed on this day.
     */
    private bool $is_closed;

    /**
     * Constructor.
     */
    public function __construct( int $location_id, int $day_of_week, ?string $open_time, ?string $close_time, bool $is_closed ) {
        $this->location_id = $location_id;
        $this->day_of_week = $day_of_week;
        $this->open_time   = $open_time;
        $this->close_time  = $close_time;
        $this->is_closed   = $is_closed;
    }

    /**
     * Hydrate from database row.
     *
     * @param array<string, mixed> $row Database row.
     */
    public static function from_row( array $row ): self {
        $location_id = (int) ( $row['location_id'] ?? 0 );
        $day_of_week = (int) ( $row['day_of_week'] ?? 0 );
        $is_closed   = (int) ( $row['is_closed'] ?? 0 ) === 1;

        $open_time  = isset( $row['open_time'] ) ? (string) $row['open_time'] : null;
        $close_time = isset( $row['close_time'] ) ? (string) $row['close_time'] : null;

        if ( $is_closed ) {
            $open_time  = null;
            $close_time = null;
        }

        return new self( $location_id, $day_of_week, $open_time, $close_time, $is_closed );
    }

    /**
     * Location identifier.
     */
    public function get_location_id(): int {
        return $this->location_id;
    }

    /**
     * Day of week (1 = Monday, 7 = Sunday).
     */
    public function get_day_of_week(): int {
        return $this->day_of_week;
    }

    /**
     * Opening time in HH:MM format or null when closed.
     */
    public function get_open_time(): ?string {
        if ( null === $this->open_time ) {
            return null;
        }

        return substr( $this->open_time, 0, 5 );
    }

    /**
     * Closing time in HH:MM format or null when closed.
     */
    public function get_close_time(): ?string {
        if ( null === $this->close_time ) {
            return null;
        }

        return substr( $this->close_time, 0, 5 );
    }

    /**
     * Whether the location is closed on this day.
     */
    public function is_closed(): bool {
        return $this->is_closed;
    }
}
