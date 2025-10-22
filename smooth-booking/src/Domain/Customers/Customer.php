<?php
/**
 * Aggregate representing a customer.
 *
 * @package SmoothBooking\Domain\Customers
 */

namespace SmoothBooking\Domain\Customers;

use DateTimeImmutable;

/**
 * Customer domain entity.
 */
class Customer {
    /**
     * @var int
     */
    private int $id;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var int|null
     */
    private ?int $user_id;

    /**
     * @var int|null
     */
    private ?int $profile_image_id;

    /**
     * @var string|null
     */
    private ?string $first_name;

    /**
     * @var string|null
     */
    private ?string $last_name;

    /**
     * @var string|null
     */
    private ?string $phone;

    /**
     * @var string|null
     */
    private ?string $email;

    /**
     * @var string|null
     */
    private ?string $date_of_birth;

    /**
     * @var string|null
     */
    private ?string $country;

    /**
     * @var string|null
     */
    private ?string $state;

    /**
     * @var string|null
     */
    private ?string $postal_code;

    /**
     * @var string|null
     */
    private ?string $city;

    /**
     * @var string|null
     */
    private ?string $street_address;

    /**
     * @var string|null
     */
    private ?string $additional_address;

    /**
     * @var string|null
     */
    private ?string $street_number;

    /**
     * @var string|null
     */
    private ?string $notes;

    /**
     * @var DateTimeImmutable|null
     */
    private ?DateTimeImmutable $last_appointment;

    /**
     * @var int
     */
    private int $total_appointments;

    /**
     * @var float
     */
    private float $total_payments;

    /**
     * @var DateTimeImmutable|null
     */
    private ?DateTimeImmutable $created_at;

    /**
     * @var DateTimeImmutable|null
     */
    private ?DateTimeImmutable $updated_at;

    /**
     * @var bool
     */
    private bool $is_deleted;

    /**
     * @var CustomerTag[]
     */
    private array $tags = [];

    /**
     * Constructor.
     */
    public function __construct( int $id, string $name, ?int $user_id, ?int $profile_image_id, ?string $first_name, ?string $last_name, ?string $phone, ?string $email, ?string $date_of_birth, ?string $country, ?string $state, ?string $postal_code, ?string $city, ?string $street_address, ?string $additional_address, ?string $street_number, ?string $notes, ?DateTimeImmutable $last_appointment, int $total_appointments, float $total_payments, ?DateTimeImmutable $created_at, ?DateTimeImmutable $updated_at, bool $is_deleted ) {
        $this->id                 = $id;
        $this->name               = $name;
        $this->user_id            = $user_id;
        $this->profile_image_id   = $profile_image_id;
        $this->first_name         = $first_name;
        $this->last_name          = $last_name;
        $this->phone              = $phone;
        $this->email              = $email;
        $this->date_of_birth      = $date_of_birth;
        $this->country            = $country;
        $this->state              = $state;
        $this->postal_code        = $postal_code;
        $this->city               = $city;
        $this->street_address     = $street_address;
        $this->additional_address = $additional_address;
        $this->street_number      = $street_number;
        $this->notes              = $notes;
        $this->last_appointment   = $last_appointment;
        $this->total_appointments = $total_appointments;
        $this->total_payments     = $total_payments;
        $this->created_at         = $created_at;
        $this->updated_at         = $updated_at;
        $this->is_deleted         = $is_deleted;
    }

    /**
     * Instantiate from database row.
     *
     * @param array<string, mixed> $row Database row data.
     */
    public static function from_row( array $row ): self {
        return new self(
            (int) ( $row['customer_id'] ?? 0 ),
            (string) ( $row['name'] ?? '' ),
            isset( $row['user_id'] ) ? (int) $row['user_id'] : null,
            isset( $row['profile_image_id'] ) ? (int) $row['profile_image_id'] : null,
            self::empty_to_null( $row['first_name'] ?? null ),
            self::empty_to_null( $row['last_name'] ?? null ),
            self::empty_to_null( $row['phone'] ?? null ),
            self::empty_to_null( $row['email'] ?? null ),
            self::empty_to_null( $row['date_of_birth'] ?? null ),
            self::empty_to_null( $row['country'] ?? null ),
            self::empty_to_null( $row['state_region'] ?? null ),
            self::empty_to_null( $row['postal_code'] ?? null ),
            self::empty_to_null( $row['city'] ?? null ),
            self::empty_to_null( $row['street_address'] ?? null ),
            self::empty_to_null( $row['additional_address'] ?? null ),
            self::empty_to_null( $row['street_number'] ?? null ),
            self::empty_to_null( $row['notes'] ?? null ),
            self::parse_datetime( $row['last_appointment_at'] ?? null ),
            (int) ( $row['total_appointments'] ?? 0 ),
            (float) ( $row['total_payments'] ?? 0.0 ),
            self::parse_datetime( $row['created_at'] ?? null ),
            self::parse_datetime( $row['updated_at'] ?? null ),
            (bool) (int) ( $row['is_deleted'] ?? 0 )
        );
    }

    /**
     * Attach tags to the customer.
     *
     * @param CustomerTag[] $tags Customer tags.
     */
    public function with_tags( array $tags ): self {
        $clone       = clone $this;
        $clone->tags = $tags;

        return $clone;
    }

    /**
     * Customer identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Display name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Associated user identifier.
     */
    public function get_user_id(): ?int {
        return $this->user_id;
    }

    /**
     * Profile image attachment identifier.
     */
    public function get_profile_image_id(): ?int {
        return $this->profile_image_id;
    }

    /**
     * First name.
     */
    public function get_first_name(): ?string {
        return $this->first_name;
    }

    /**
     * Last name.
     */
    public function get_last_name(): ?string {
        return $this->last_name;
    }

    /**
     * Phone number.
     */
    public function get_phone(): ?string {
        return $this->phone;
    }

    /**
     * Email address.
     */
    public function get_email(): ?string {
        return $this->email;
    }

    /**
     * Date of birth (Y-m-d).
     */
    public function get_date_of_birth(): ?string {
        return $this->date_of_birth;
    }

    /**
     * Country value.
     */
    public function get_country(): ?string {
        return $this->country;
    }

    /**
     * State or region.
     */
    public function get_state(): ?string {
        return $this->state;
    }

    /**
     * Postal code.
     */
    public function get_postal_code(): ?string {
        return $this->postal_code;
    }

    /**
     * City name.
     */
    public function get_city(): ?string {
        return $this->city;
    }

    /**
     * Street address line.
     */
    public function get_street_address(): ?string {
        return $this->street_address;
    }

    /**
     * Additional address info.
     */
    public function get_additional_address(): ?string {
        return $this->additional_address;
    }

    /**
     * Street number value.
     */
    public function get_street_number(): ?string {
        return $this->street_number;
    }

    /**
     * Notes field.
     */
    public function get_notes(): ?string {
        return $this->notes;
    }

    /**
     * Last appointment date-time.
     */
    public function get_last_appointment(): ?DateTimeImmutable {
        return $this->last_appointment;
    }

    /**
     * Number of appointments.
     */
    public function get_total_appointments(): int {
        return $this->total_appointments;
    }

    /**
     * Payments total.
     */
    public function get_total_payments(): float {
        return $this->total_payments;
    }

    /**
     * Creation timestamp.
     */
    public function get_created_at(): ?DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Updated timestamp.
     */
    public function get_updated_at(): ?DateTimeImmutable {
        return $this->updated_at;
    }

    /**
     * Whether the record is deleted.
     */
    public function is_deleted(): bool {
        return $this->is_deleted;
    }

    /**
     * Associated tags.
     *
     * @return CustomerTag[]
     */
    public function get_tags(): array {
        return $this->tags;
    }

    /**
     * Export to array for APIs.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                  => $this->get_id(),
            'name'                => $this->get_name(),
            'user_id'             => $this->get_user_id(),
            'profile_image_id'    => $this->get_profile_image_id(),
            'first_name'          => $this->get_first_name(),
            'last_name'           => $this->get_last_name(),
            'phone'               => $this->get_phone(),
            'email'               => $this->get_email(),
            'date_of_birth'       => $this->get_date_of_birth(),
            'country'             => $this->get_country(),
            'state'               => $this->get_state(),
            'postal_code'         => $this->get_postal_code(),
            'city'                => $this->get_city(),
            'street_address'      => $this->get_street_address(),
            'additional_address'  => $this->get_additional_address(),
            'street_number'       => $this->get_street_number(),
            'notes'               => $this->get_notes(),
            'last_appointment_at' => $this->get_last_appointment() ? $this->get_last_appointment()->format( 'c' ) : null,
            'total_appointments'  => $this->get_total_appointments(),
            'total_payments'      => $this->get_total_payments(),
            'created_at'          => $this->get_created_at() ? $this->get_created_at()->format( 'c' ) : null,
            'updated_at'          => $this->get_updated_at() ? $this->get_updated_at()->format( 'c' ) : null,
            'is_deleted'          => $this->is_deleted(),
            'tags'                => array_map(
                static function ( CustomerTag $tag ): array {
                    return [
                        'id'   => $tag->get_id(),
                        'name' => $tag->get_name(),
                        'slug' => $tag->get_slug(),
                    ];
                },
                $this->get_tags()
            ),
        ];
    }

    /**
     * Convert empty strings to null.
     *
     * @param string|null $value Input value.
     */
    private static function empty_to_null( ?string $value ): ?string {
        if ( null === $value ) {
            return null;
        }

        $value = (string) $value;

        return '' === trim( $value ) ? null : $value;
    }

    /**
     * Parse a MySQL datetime to immutable object.
     */
    private static function parse_datetime( $value ): ?DateTimeImmutable { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
        if ( empty( $value ) || '0000-00-00 00:00:00' === $value || '0000-00-00' === $value ) {
            return null;
        }

        try {
            return new DateTimeImmutable( (string) $value );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            return null;
        }
    }
}
