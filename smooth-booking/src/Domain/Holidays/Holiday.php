<?php
/**
 * Value object representing a single holiday/closure day.
 *
 * @package SmoothBooking\Domain\Holidays
 */

namespace SmoothBooking\Domain\Holidays;

/**
 * Immutable location holiday record.
 */
class Holiday {
    /**
     * Identifier.
     */
    private int $id;

    /**
     * Associated location identifier.
     */
    private int $location_id;

    /**
     * Date in Y-m-d format.
     */
    private string $date;

    /**
     * Optional note/label for the holiday.
     */
    private string $note;

    /**
     * Whether the holiday repeats every year.
     */
    private bool $is_recurring;

    /**
     * Constructor.
     */
    public function __construct( int $id, int $location_id, string $date, string $note, bool $is_recurring ) {
        $this->id           = $id;
        $this->location_id  = $location_id;
        $this->date         = $date;
        $this->note         = $note;
        $this->is_recurring = $is_recurring;
    }

    /**
     * Hydrate from database row.
     *
     * @param array<string, mixed> $row Database row.
     */
    public static function from_row( array $row ): self {
        $id          = (int) ( $row['holiday_id'] ?? 0 );
        $location_id = (int) ( $row['location_id'] ?? 0 );
        $date        = (string) ( $row['holiday_date'] ?? '' );
        $note        = (string) ( $row['note'] ?? '' );
        $is_recurring = (int) ( $row['is_recurring'] ?? 0 ) === 1;

        return new self( $id, $location_id, $date, $note, $is_recurring );
    }

    /**
     * Identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Location identifier.
     */
    public function get_location_id(): int {
        return $this->location_id;
    }

    /**
     * Holiday date in Y-m-d format.
     */
    public function get_date(): string {
        return $this->date;
    }

    /**
     * Holiday note/label.
     */
    public function get_note(): string {
        return $this->note;
    }

    /**
     * Whether the entry repeats every year.
     */
    public function is_recurring(): bool {
        return $this->is_recurring;
    }

    /**
     * Retrieve the month/day key (MM-DD) for recurring comparisons.
     */
    public function get_month_day_key(): string {
        return substr( $this->date, 5 );
    }
}
