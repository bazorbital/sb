<?php
/**
 * Value object representing an employee category.
 *
 * @package SmoothBooking\Domain\Employees
 */

namespace SmoothBooking\Domain\Employees;

/**
 * Describes a reusable category for employees.
 */
class EmployeeCategory {
    /**
     * Category identifier.
     */
    private int $id;

    /**
     * Human readable name.
     */
    private string $name;

    /**
     * URL friendly slug.
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
     * Build a category from raw database row.
     *
     * @param array<string, mixed> $row Database row.
     */
    public static function from_row( array $row ): self {
        return new self(
            isset( $row['category_id'] ) ? (int) $row['category_id'] : 0,
            (string) ( $row['name'] ?? '' ),
            (string) ( $row['slug'] ?? '' )
        );
    }

    /**
     * Get the category identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the category name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the category slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Convert category to array representation.
     *
     * @return array{id:int,name:string,slug:string}
     */
    public function to_array(): array {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
