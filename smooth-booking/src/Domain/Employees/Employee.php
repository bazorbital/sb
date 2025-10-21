<?php
/**
 * Value object representing an employee.
 *
 * @package SmoothBooking\Domain\Employees
 */

namespace SmoothBooking\Domain\Employees;

/**
 * Immutable employee entity used across the plugin.
 */
class Employee {
    /**
     * Employee identifier.
     */
    private int $id;

    /**
     * Display name.
     */
    private string $name;

    /**
     * Email address.
     */
    private ?string $email;

    /**
     * Phone number.
     */
    private ?string $phone;

    /**
     * Specialization label.
     */
    private ?string $specialization;

    /**
     * Whether the employee can be booked online.
     */
    private bool $available_online;

    /**
     * Creation timestamp.
     */
    private ?string $created_at;

    /**
     * Update timestamp.
     */
    private ?string $updated_at;

    /**
     * Constructor.
     *
     * @param int         $id               Employee ID.
     * @param string      $name             Display name.
     * @param string|null $email            Email address.
     * @param string|null $phone            Phone number.
     * @param string|null $specialization   Specialization.
     * @param bool        $available_online Online booking availability.
     * @param string|null $created_at       Creation timestamp.
     * @param string|null $updated_at       Update timestamp.
     */
    public function __construct(
        int $id,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $specialization,
        bool $available_online,
        ?string $created_at,
        ?string $updated_at
    ) {
        $this->id               = $id;
        $this->name             = $name;
        $this->email            = $email ?: null;
        $this->phone            = $phone ?: null;
        $this->specialization   = $specialization ?: null;
        $this->available_online = $available_online;
        $this->created_at       = $created_at;
        $this->updated_at       = $updated_at;
    }

    /**
     * Build an employee from database row data.
     *
     * @param array<string, mixed> $row Database row.
     */
    public static function from_row( array $row ): self {
        return new self(
            isset( $row['employee_id'] ) ? (int) $row['employee_id'] : 0,
            (string) ( $row['name'] ?? '' ),
            isset( $row['email'] ) ? ( $row['email'] !== '' ? (string) $row['email'] : null ) : null,
            isset( $row['phone'] ) ? ( $row['phone'] !== '' ? (string) $row['phone'] : null ) : null,
            isset( $row['specialization'] ) ? ( $row['specialization'] !== '' ? (string) $row['specialization'] : null ) : null,
            ! empty( $row['available_online'] ),
            isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
            isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null
        );
    }

    /**
     * Get employee identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get display name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get email address.
     */
    public function get_email(): ?string {
        return $this->email;
    }

    /**
     * Get phone number.
     */
    public function get_phone(): ?string {
        return $this->phone;
    }

    /**
     * Get specialization.
     */
    public function get_specialization(): ?string {
        return $this->specialization;
    }

    /**
     * Determine if the employee can be booked online.
     */
    public function is_available_online(): bool {
        return $this->available_online;
    }

    /**
     * Get creation timestamp.
     */
    public function get_created_at(): ?string {
        return $this->created_at;
    }

    /**
     * Get update timestamp.
     */
    public function get_updated_at(): ?string {
        return $this->updated_at;
    }

    /**
     * Export to array representation.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'specialization'   => $this->specialization,
            'available_online' => $this->available_online,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
