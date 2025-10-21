<?php
/**
 * Value object representing a service category.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use DateTimeImmutable;

/**
 * Service category data holder.
 */
class ServiceCategory {
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
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $created_at;

    /**
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $updated_at;

    /**
     * Factory.
     *
     * @param array<string, mixed> $row Row data.
     */
    public static function from_row( array $row ): self {
        $category            = new self();
        $category->id        = (int) $row['category_id'];
        $category->name      = (string) $row['name'];
        $category->slug      = (string) $row['slug'];
        $category->created_at = new DateTimeImmutable( (string) $row['created_at'] );
        $category->updated_at = new DateTimeImmutable( (string) $row['updated_at'] );

        return $category;
    }

    /**
     * Identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Array representation.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'   => $this->get_id(),
            'name' => $this->get_name(),
            'slug' => $this->get_slug(),
        ];
    }
}
