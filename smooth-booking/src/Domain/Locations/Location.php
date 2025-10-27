<?php
/**
 * Value object representing a location.
 *
 * @package SmoothBooking\Domain\Locations
 */

namespace SmoothBooking\Domain\Locations;

/**
 * Immutable location entity.
 */
class Location {
    private int $id;

    private string $name;

    private ?string $address;

    private bool $is_event_location;

    private bool $is_deleted;

    public function __construct( int $id, string $name, ?string $address, bool $is_event_location, bool $is_deleted ) {
        $this->id                = $id;
        $this->name              = $name;
        $this->address           = $address;
        $this->is_event_location = $is_event_location;
        $this->is_deleted        = $is_deleted;
    }

    /**
     * Hydrate from database row.
     *
     * @param array<string, mixed> $row Database row.
     */
    public static function from_row( array $row ): self {
        return new self(
            (int) ( $row['location_id'] ?? 0 ),
            (string) ( $row['name'] ?? '' ),
            isset( $row['address'] ) ? (string) $row['address'] : null,
            (int) ( $row['is_event_location'] ?? 0 ) === 1,
            (int) ( $row['is_deleted'] ?? 0 ) === 1
        );
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_address(): ?string {
        return $this->address;
    }

    public function is_event_location(): bool {
        return $this->is_event_location;
    }

    public function is_deleted(): bool {
        return $this->is_deleted;
    }
}
