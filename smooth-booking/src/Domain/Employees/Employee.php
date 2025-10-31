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
     * Attachment ID of the profile image.
     */
    private ?int $profile_image_id;

    /**
     * Preferred calendar color.
     */
    private ?string $default_color;

    /**
     * Visibility setting.
     */
    private string $visibility;

    /**
     * Creation timestamp.
     */
    private ?string $created_at;

    /**
     * Update timestamp.
     */
    private ?string $updated_at;

    /**
     * Assigned categories.
     *
     * @var EmployeeCategory[]
     */
    private array $categories;

    /**
     * Assigned location identifiers.
     *
     * @var int[]
     */
    private array $location_ids;

    /**
     * Assigned services with optional price overrides.
     *
     * @var array<int, array{service_id:int, order:int, price:float|null}>
     */
    private array $services;

    /**
     * Weekly working schedule keyed by ISO-8601 day index (1 = Monday).
     *
     * @var array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}>>>
     */
    private array $schedule;

    /**
     * Constructor.
     *
     * @param int         $id               Employee ID.
     * @param string      $name             Display name.
     * @param string|null $email            Email address.
     * @param string|null $phone            Phone number.
     * @param string|null $specialization   Specialization.
     * @param bool                  $available_online Online booking availability.
     * @param int|null              $profile_image_id Profile image attachment ID.
     * @param string|null           $default_color    Default color in HEX format.
     * @param string                $visibility       Visibility status.
     * @param string|null           $created_at       Creation timestamp.
     * @param string|null           $updated_at       Update timestamp.
     * @param EmployeeCategory[]    $categories       Attached categories.
     * @param int[]                 $location_ids     Assigned location identifiers.
     * @param array<int, array{service_id:int, order:int, price:float|null}> $services Assigned services.
     * @param array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}>}> $schedule Working schedule.
     */
    public function __construct(
        int $id,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $specialization,
        bool $available_online,
        ?int $profile_image_id,
        ?string $default_color,
        string $visibility,
        ?string $created_at,
        ?string $updated_at,
        array $categories = [],
        array $location_ids = [],
        array $services = [],
        array $schedule = []
    ) {
        $this->id               = $id;
        $this->name             = $name;
        $this->email            = $email ?: null;
        $this->phone            = $phone ?: null;
        $this->specialization   = $specialization ?: null;
        $this->available_online = $available_online;
        $this->profile_image_id = $profile_image_id ?: null;
        $this->default_color    = $default_color ?: null;
        $this->visibility       = $visibility;
        $this->created_at       = $created_at;
        $this->updated_at       = $updated_at;
        $this->categories       = $categories;
        $this->location_ids     = $location_ids;
        $this->services         = $services;
        $this->schedule         = $schedule;
    }

    /**
     * Build an employee from database row data.
     *
     * @param array<string, mixed> $row        Database row.
     * @param EmployeeCategory[]   $categories Assigned categories.
     */
    public static function from_row( array $row, array $categories = [] ): self {
        return new self(
            isset( $row['employee_id'] ) ? (int) $row['employee_id'] : 0,
            (string) ( $row['name'] ?? '' ),
            isset( $row['email'] ) ? ( $row['email'] !== '' ? (string) $row['email'] : null ) : null,
            isset( $row['phone'] ) ? ( $row['phone'] !== '' ? (string) $row['phone'] : null ) : null,
            isset( $row['specialization'] ) ? ( $row['specialization'] !== '' ? (string) $row['specialization'] : null ) : null,
            ! empty( $row['available_online'] ),
            isset( $row['profile_image_id'] ) ? ( (int) $row['profile_image_id'] ?: null ) : null,
            isset( $row['default_color'] ) ? ( $row['default_color'] !== '' ? (string) $row['default_color'] : null ) : null,
            (string) ( $row['visibility'] ?? 'public' ),
            isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
            isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null,
            $categories,
            [],
            [],
            []
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
     * Get profile image attachment ID.
     */
    public function get_profile_image_id(): ?int {
        return $this->profile_image_id;
    }

    /**
     * Get default color value.
     */
    public function get_default_color(): ?string {
        return $this->default_color;
    }

    /**
     * Get visibility flag.
     */
    public function get_visibility(): string {
        return $this->visibility;
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
     * Retrieve categories.
     *
     * @return EmployeeCategory[]
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * Return a new instance with the provided categories.
     *
     * @param EmployeeCategory[] $categories Categories to assign.
     */
    public function with_categories( array $categories ): self {
        $clone             = clone $this;
        $clone->categories = $categories;

        return $clone;
    }

    /**
     * Retrieve assigned location identifiers.
     *
     * @return int[]
     */
    public function get_location_ids(): array {
        return $this->location_ids;
    }

    /**
     * Return a clone with provided location identifiers.
     *
     * @param int[] $location_ids Assigned locations.
     */
    public function with_locations( array $location_ids ): self {
        $clone               = clone $this;
        $clone->location_ids = array_values( $location_ids );

        return $clone;
    }

    /**
     * Retrieve assigned services.
     *
     * @return array<int, array{service_id:int, order:int, price:float|null}>
     */
    public function get_services(): array {
        return $this->services;
    }

    /**
     * Return a clone with provided service assignments.
     *
     * @param array<int, array{service_id:int, order:int, price:float|null}> $services Services to assign.
     */
    public function with_services( array $services ): self {
        $clone           = clone $this;
        $clone->services = $services;

        return $clone;
    }

    /**
     * Retrieve weekly schedule definition.
     *
     * @return array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>}
     */
    public function get_schedule(): array {
        return $this->schedule;
    }

    /**
     * Return a clone with provided schedule definition.
     *
     * @param array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}> $schedule Schedule to assign.
     */
    public function with_schedule( array $schedule ): self {
        $clone          = clone $this;
        $clone->schedule = $schedule;

        return $clone;
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
            'profile_image_id' => $this->profile_image_id,
            'default_color'    => $this->default_color,
            'visibility'       => $this->visibility,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'categories'       => array_map(
                static function ( EmployeeCategory $category ): array {
                    return $category->to_array();
                },
                $this->categories
            ),
            'locations'        => $this->location_ids,
            'services'         => $this->services,
            'schedule'         => $this->schedule,
        ];
    }
}
