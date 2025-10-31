<?php
/**
 * Value object representing a service.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use DateTimeImmutable;

/**
 * Immutable representation of a booking service.
 */
class Service {
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
    private ?int $image_id;

    /**
     * @var string|null
     */
    private ?string $color;

    /**
     * @var string
     */
    private string $visibility;

    /**
     * @var float|null
     */
    private ?float $price;

    /**
     * @var string
     */
    private string $payment_methods_mode;

    /**
     * @var string|null
     */
    private ?string $info;

    /**
     * @var string
     */
    private string $providers_preference;

    /**
     * @var bool
     */
    private bool $providers_random_tie;

    /**
     * @var int
     */
    private int $occupancy_period_before;

    /**
     * @var int
     */
    private int $occupancy_period_after;

    /**
     * @var string
     */
    private string $duration_key;

    /**
     * @var string
     */
    private string $slot_length_key;

    /**
     * @var string
     */
    private string $padding_before_key;

    /**
     * @var string
     */
    private string $padding_after_key;

    /**
     * @var string
     */
    private string $online_meeting_provider;

    /**
     * @var string
     */
    private string $limit_per_customer;

    /**
     * @var bool
     */
    private bool $final_step_url_enabled;

    /**
     * @var string|null
     */
    private ?string $final_step_url;

    /**
     * @var string
     */
    private string $min_time_prior_booking_key;

    /**
     * @var string
     */
    private string $min_time_prior_cancel_key;

    /**
     * @var bool
     */
    private bool $is_deleted;

    /**
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $created_at;

    /**
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $updated_at;

    /**
     * @var ServiceCategory[]
     */
    private array $categories = [];

    /**
     * @var ServiceTag[]
     */
    private array $tags = [];

    /**
     * @var array<int, array{employee_id:int, order:int}>
     */
    private array $providers = [];

    /**
     * Factory from database row.
     *
     * @param array<string, mixed> $row Row data.
     */
    public static function from_row( array $row ): self {
        $service = new self();
        $service->id                         = (int) $row['service_id'];
        $service->name                       = (string) $row['name'];
        $service->image_id                   = isset( $row['profile_image_id'] ) && '' !== $row['profile_image_id'] ? (int) $row['profile_image_id'] : null;
        $service->color                      = isset( $row['default_color'] ) && '' !== $row['default_color'] ? (string) $row['default_color'] : null;
        $service->visibility                 = (string) $row['visibility'];
        $service->price                      = isset( $row['price'] ) ? (float) $row['price'] : null;
        $service->payment_methods_mode       = (string) $row['payment_methods_mode'];
        $service->info                       = isset( $row['info'] ) && '' !== $row['info'] ? (string) $row['info'] : null;
        $service->providers_preference       = (string) $row['providers_preference'];
        $service->providers_random_tie       = (bool) $row['providers_random_tie'];
        $service->occupancy_period_before    = isset( $row['occupancy_period_before'] ) ? (int) $row['occupancy_period_before'] : 0;
        $service->occupancy_period_after     = isset( $row['occupancy_period_after'] ) ? (int) $row['occupancy_period_after'] : 0;
        $service->duration_key               = (string) $row['duration_key'];
        $service->slot_length_key            = (string) $row['slot_length_key'];
        $service->padding_before_key         = (string) $row['padding_before_key'];
        $service->padding_after_key          = (string) $row['padding_after_key'];
        $service->online_meeting_provider    = (string) $row['online_meeting_provider'];
        $service->limit_per_customer         = (string) $row['limit_per_customer'];
        $service->final_step_url_enabled     = (bool) $row['final_step_url_enabled'];
        $service->final_step_url             = isset( $row['final_step_url'] ) && '' !== $row['final_step_url'] ? (string) $row['final_step_url'] : null;
        $service->min_time_prior_booking_key = (string) $row['min_time_prior_booking_key'];
        $service->min_time_prior_cancel_key  = (string) $row['min_time_prior_cancel_key'];
        $service->is_deleted                 = (bool) $row['is_deleted'];
        $service->created_at                 = new DateTimeImmutable( (string) $row['created_at'] );
        $service->updated_at                 = new DateTimeImmutable( (string) $row['updated_at'] );

        return $service;
    }

    /**
     * Get identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get service name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get image ID.
     */
    public function get_image_id(): ?int {
        return $this->image_id;
    }

    /**
     * Get color hex.
     */
    public function get_color(): ?string {
        return $this->color;
    }

    /**
     * Get visibility.
     */
    public function get_visibility(): string {
        return $this->visibility;
    }

    /**
     * Get price.
     */
    public function get_price(): ?float {
        return $this->price;
    }

    /**
     * Payment methods mode.
     */
    public function get_payment_methods_mode(): string {
        return $this->payment_methods_mode;
    }

    /**
     * Additional info text.
     */
    public function get_info(): ?string {
        return $this->info;
    }

    /**
     * Provider preference.
     */
    public function get_providers_preference(): string {
        return $this->providers_preference;
    }

    /**
     * Whether to randomize on ties.
     */
    public function is_providers_random_tie(): bool {
        return $this->providers_random_tie;
    }

    /**
     * Occupancy lookback.
     */
    public function get_occupancy_period_before(): int {
        return $this->occupancy_period_before;
    }

    /**
     * Occupancy lookahead.
     */
    public function get_occupancy_period_after(): int {
        return $this->occupancy_period_after;
    }

    /**
     * Duration key.
     */
    public function get_duration_key(): string {
        return $this->duration_key;
    }

    /**
     * Slot length key.
     */
    public function get_slot_length_key(): string {
        return $this->slot_length_key;
    }

    /**
     * Padding before key.
     */
    public function get_padding_before_key(): string {
        return $this->padding_before_key;
    }

    /**
     * Padding after key.
     */
    public function get_padding_after_key(): string {
        return $this->padding_after_key;
    }

    /**
     * Online meeting provider.
     */
    public function get_online_meeting_provider(): string {
        return $this->online_meeting_provider;
    }

    /**
     * Customer limit key.
     */
    public function get_limit_per_customer(): string {
        return $this->limit_per_customer;
    }

    /**
     * Whether final step URL is enabled.
     */
    public function is_final_step_url_enabled(): bool {
        return $this->final_step_url_enabled;
    }

    /**
     * Final step URL.
     */
    public function get_final_step_url(): ?string {
        return $this->final_step_url;
    }

    /**
     * Minimum time before booking.
     */
    public function get_min_time_prior_booking_key(): string {
        return $this->min_time_prior_booking_key;
    }

    /**
     * Minimum time before cancelling.
     */
    public function get_min_time_prior_cancel_key(): string {
        return $this->min_time_prior_cancel_key;
    }

    /**
     * Whether service is deleted.
     */
    public function is_deleted(): bool {
        return $this->is_deleted;
    }

    /**
     * Creation timestamp.
     */
    public function get_created_at(): DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * Update timestamp.
     */
    public function get_updated_at(): DateTimeImmutable {
        return $this->updated_at;
    }

    /**
     * Get categories.
     *
     * @return ServiceCategory[]
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * Get tags.
     *
     * @return ServiceTag[]
     */
    public function get_tags(): array {
        return $this->tags;
    }

    /**
     * Get providers.
     *
     * @return array<int, array{employee_id:int, order:int, price:float|null}>
     */
    public function get_providers(): array {
        return $this->providers;
    }

    /**
     * Attach categories to service.
     */
    public function with_categories( array $categories ): self {
        $clone             = clone $this;
        $clone->categories = $categories;

        return $clone;
    }

    /**
     * Attach tags.
     */
    public function with_tags( array $tags ): self {
        $clone        = clone $this;
        $clone->tags = $tags;

        return $clone;
    }

    /**
     * Attach providers.
     *
     * @param array<int, array{employee_id:int, order:int, price:float|null}> $providers Providers to attach.
     */
    public function with_providers( array $providers ): self {
        $clone            = clone $this;
        $clone->providers = $providers;

        return $clone;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                           => $this->get_id(),
            'name'                         => $this->get_name(),
            'profile_image_id'             => $this->get_image_id(),
            'default_color'                => $this->get_color(),
            'visibility'                   => $this->get_visibility(),
            'price'                        => $this->get_price(),
            'payment_methods_mode'         => $this->get_payment_methods_mode(),
            'info'                         => $this->get_info(),
            'providers_preference'         => $this->get_providers_preference(),
            'providers_random_tie'         => $this->is_providers_random_tie(),
            'occupancy_period_before'      => $this->get_occupancy_period_before(),
            'occupancy_period_after'       => $this->get_occupancy_period_after(),
            'duration_key'                 => $this->get_duration_key(),
            'slot_length_key'              => $this->get_slot_length_key(),
            'padding_before_key'           => $this->get_padding_before_key(),
            'padding_after_key'            => $this->get_padding_after_key(),
            'online_meeting_provider'      => $this->get_online_meeting_provider(),
            'limit_per_customer'           => $this->get_limit_per_customer(),
            'final_step_url_enabled'       => $this->is_final_step_url_enabled(),
            'final_step_url'               => $this->get_final_step_url(),
            'min_time_prior_booking_key'   => $this->get_min_time_prior_booking_key(),
            'min_time_prior_cancel_key'    => $this->get_min_time_prior_cancel_key(),
            'is_deleted'                   => $this->is_deleted(),
            'created_at'                   => $this->get_created_at()->format( 'c' ),
            'updated_at'                   => $this->get_updated_at()->format( 'c' ),
            'categories'                   => array_map(
                static function ( ServiceCategory $category ): array {
                    return $category->to_array();
                },
                $this->get_categories()
            ),
            'tags'                         => array_map(
                static function ( ServiceTag $tag ): array {
                    return $tag->to_array();
                },
                $this->get_tags()
            ),
            'providers'                    => $this->get_providers(),
        ];
    }
}
