<?php
/**
 * Value object representing a customer tag.
 *
 * @package SmoothBooking\Domain\Customers
 */

namespace SmoothBooking\Domain\Customers;

/**
 * Represents a customer tag entity.
 */
class CustomerTag {
    /**
     * @var int
     */
    private int $id;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $slug;

    /**
     * Constructor.
     */
    public function __construct( int $id, string $name, string $slug ) {
        $this->id   = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    /**
     * Create an instance from a database row.
     *
     * @param array<string, mixed> $row Database row data.
     */
    public static function from_row( array $row ): self {
        return new self(
            (int) ( $row['tag_id'] ?? 0 ),
            (string) ( $row['name'] ?? '' ),
            (string) ( $row['slug'] ?? '' )
        );
    }

    /**
     * Tag identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Tag display name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Tag slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }
}
