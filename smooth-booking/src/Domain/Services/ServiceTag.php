<?php
/**
 * Value object representing a service tag.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use DateTimeImmutable;

/**
 * Service tag data holder.
 */
class ServiceTag {
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
        $tag            = new self();
        $tag->id        = (int) $row['tag_id'];
        $tag->name      = (string) $row['name'];
        $tag->slug      = (string) $row['slug'];
        $tag->created_at = new DateTimeImmutable( (string) $row['created_at'] );
        $tag->updated_at = new DateTimeImmutable( (string) $row['updated_at'] );

        return $tag;
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
