<?php
/**
 * Value object representing a location.
 *
 * @package SmoothBooking\Domain\Locations
 */

namespace SmoothBooking\Domain\Locations;

use function absint;

/**
 * Immutable location entity.
 */
class Location {
    private int $id;

    private string $name;

    private ?int $profile_image_id;

    private ?string $address;

    private ?string $phone;

    private ?string $base_email;

    private ?string $website;

    private int $industry_id;

    private bool $is_event_location;

    private bool $is_deleted;

    private ?string $company_name;

    private ?string $company_address;

    private ?string $company_phone;

    private ?string $created_at;

    private ?string $updated_at;

    public function __construct(
        int $id,
        string $name,
        ?int $profile_image_id,
        ?string $address,
        ?string $phone,
        ?string $base_email,
        ?string $website,
        int $industry_id,
        bool $is_event_location,
        bool $is_deleted,
        ?string $company_name,
        ?string $company_address,
        ?string $company_phone,
        ?string $created_at,
        ?string $updated_at
    ) {
        $this->id                = $id;
        $this->name              = $name;
        $this->profile_image_id  = $profile_image_id;
        $this->address           = $address;
        $this->phone             = $phone;
        $this->base_email        = $base_email;
        $this->website           = $website;
        $this->industry_id       = $industry_id;
        $this->is_event_location = $is_event_location;
        $this->is_deleted        = $is_deleted;
        $this->company_name      = $company_name;
        $this->company_address   = $company_address;
        $this->company_phone     = $company_phone;
        $this->created_at        = $created_at;
        $this->updated_at        = $updated_at;
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
            isset( $row['profile_image_id'] ) ? absint( $row['profile_image_id'] ) ?: null : null,
            isset( $row['address'] ) ? (string) $row['address'] : null,
            isset( $row['phone'] ) ? (string) $row['phone'] : null,
            isset( $row['base_email'] ) ? (string) $row['base_email'] : null,
            isset( $row['website'] ) ? (string) $row['website'] : null,
            (int) ( $row['industry_id'] ?? 0 ),
            (int) ( $row['is_event_location'] ?? 0 ) === 1,
            (int) ( $row['is_deleted'] ?? 0 ) === 1,
            isset( $row['company_name'] ) ? (string) $row['company_name'] : null,
            isset( $row['company_address'] ) ? (string) $row['company_address'] : null,
            isset( $row['company_phone'] ) ? (string) $row['company_phone'] : null,
            isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
            isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null
        );
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_profile_image_id(): ?int {
        return $this->profile_image_id;
    }

    public function get_address(): ?string {
        return $this->address;
    }

    public function get_phone(): ?string {
        return $this->phone;
    }

    public function get_base_email(): ?string {
        return $this->base_email;
    }

    public function get_website(): ?string {
        return $this->website;
    }

    public function get_industry_id(): int {
        return $this->industry_id;
    }

    public function is_event_location(): bool {
        return $this->is_event_location;
    }

    public function is_deleted(): bool {
        return $this->is_deleted;
    }

    public function get_company_name(): ?string {
        return $this->company_name;
    }

    public function get_company_address(): ?string {
        return $this->company_address;
    }

    public function get_company_phone(): ?string {
        return $this->company_phone;
    }

    public function get_created_at(): ?string {
        return $this->created_at;
    }

    public function get_updated_at(): ?string {
        return $this->updated_at;
    }

    /**
     * Convert to associative array for serialization.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                => $this->get_id(),
            'name'              => $this->get_name(),
            'profile_image_id'  => $this->get_profile_image_id(),
            'address'           => $this->get_address(),
            'phone'             => $this->get_phone(),
            'base_email'        => $this->get_base_email(),
            'website'           => $this->get_website(),
            'industry_id'       => $this->get_industry_id(),
            'is_event_location' => $this->is_event_location(),
            'is_deleted'        => $this->is_deleted(),
            'company_name'      => $this->get_company_name(),
            'company_address'   => $this->get_company_address(),
            'company_phone'     => $this->get_company_phone(),
            'created_at'        => $this->get_created_at(),
            'updated_at'        => $this->get_updated_at(),
        ];
    }
}
