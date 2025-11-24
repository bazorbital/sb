<?php
/**
 * Appointment value object.
 *
 * @package SmoothBooking\Domain\Appointments
 */

namespace SmoothBooking\Domain\Appointments;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

use function abs;

/**
 * Immutable representation of a booking appointment.
 */
class Appointment {
    /**
     * Appointment identifier.
     */
    private int $id;

    /**
     * Related service identifier.
     */
    private ?int $service_id = null;

    /**
     * Service display name.
     */
    private ?string $service_name = null;

    /**
     * Service background color hex code.
     */
    private ?string $service_background_color = null;

    /**
     * Service text color hex code.
     */
    private ?string $service_text_color = null;

    /**
     * Provider (employee) identifier.
     */
    private ?int $employee_id = null;

    /**
     * Provider (employee) name.
     */
    private ?string $employee_name = null;

    /**
     * Customer identifier.
     */
    private ?int $customer_id = null;

    /**
     * Customer account name value.
     */
    private ?string $customer_account_name = null;

    /**
     * Customer first name.
     */
    private ?string $customer_first_name = null;

    /**
     * Customer last name.
     */
    private ?string $customer_last_name = null;

    /**
     * Customer phone number.
     */
    private ?string $customer_phone = null;

    /**
     * Customer email address.
     */
    private ?string $customer_email = null;

    /**
     * Scheduled start datetime.
     */
    private DateTimeImmutable $scheduled_start;

    /**
     * Scheduled end datetime.
     */
    private DateTimeImmutable $scheduled_end;

    /**
     * Appointment status string.
     */
    private string $status;

    /**
     * Payment status string.
     */
    private ?string $payment_status = null;

    /**
     * Total amount value.
     */
    private ?float $total_amount = null;

    /**
     * Currency code.
     */
    private string $currency = 'HUF';

    /**
     * Public customer-visible notes.
     */
    private ?string $notes = null;

    /**
     * Internal note value.
     */
    private ?string $internal_note = null;

    /**
     * Should notifications be dispatched.
     */
    private bool $should_notify = false;

    /**
     * Appointment is marked as recurring.
     */
    private bool $is_recurring = false;

    /**
     * Database creation timestamp.
     */
    private DateTimeImmutable $created_at;

    /**
     * Database update timestamp.
     */
    private DateTimeImmutable $updated_at;

    /**
     * Soft delete flag.
     */
    private bool $is_deleted = false;

    /**
     * Instantiate from database row.
     *
     * @param array<string, mixed> $row Row data.
     */
    public static function from_row( array $row ): self {
        $self = new self();
        $self->id                   = (int) $row['booking_id'];
        $self->service_id                 = isset( $row['service_id'] ) ? (int) $row['service_id'] : null;
        $self->service_name               = isset( $row['service_name'] ) ? (string) $row['service_name'] : null;
        $self->service_background_color   = isset( $row['service_background_color'] ) && '' !== $row['service_background_color'] ? (string) $row['service_background_color'] : null;
        $self->service_text_color         = isset( $row['service_text_color'] ) && '' !== $row['service_text_color'] ? (string) $row['service_text_color'] : null;
        if ( isset( $row['service_color'] ) && '' !== $row['service_color'] && null === $self->service_background_color ) {
            $self->service_background_color = (string) $row['service_color'];
        }
        $self->employee_id          = isset( $row['employee_id'] ) ? (int) $row['employee_id'] : null;
        $self->employee_name        = isset( $row['employee_name'] ) ? (string) $row['employee_name'] : null;
        $self->customer_id          = isset( $row['customer_id'] ) && '' !== $row['customer_id'] ? (int) $row['customer_id'] : null;
        $self->customer_account_name = isset( $row['customer_account_name'] ) ? (string) $row['customer_account_name'] : null;
        $self->customer_first_name  = isset( $row['customer_first_name'] ) ? (string) $row['customer_first_name'] : null;
        $self->customer_last_name   = isset( $row['customer_last_name'] ) ? (string) $row['customer_last_name'] : null;
        $self->customer_phone       = isset( $row['customer_phone'] ) ? (string) $row['customer_phone'] : null;
        $self->customer_email       = isset( $row['customer_email'] ) ? (string) $row['customer_email'] : null;
        $self->scheduled_start      = new DateTimeImmutable( (string) $row['scheduled_start'] );
        $self->scheduled_end        = new DateTimeImmutable( (string) $row['scheduled_end'] );
        $self->status               = (string) $row['status'];
        $self->payment_status       = isset( $row['payment_status'] ) && '' !== $row['payment_status'] ? (string) $row['payment_status'] : null;
        $self->total_amount         = isset( $row['total_amount'] ) && '' !== $row['total_amount'] ? (float) $row['total_amount'] : null;
        $self->currency             = isset( $row['currency'] ) ? (string) $row['currency'] : 'HUF';
        $self->notes                = isset( $row['notes'] ) && '' !== $row['notes'] ? (string) $row['notes'] : null;
        $self->internal_note        = isset( $row['internal_note'] ) && '' !== $row['internal_note'] ? (string) $row['internal_note'] : null;
        $self->should_notify        = ! empty( $row['should_notify'] );
        $self->is_recurring         = ! empty( $row['is_recurring'] );
        $self->created_at           = new DateTimeImmutable( (string) $row['created_at'] );
        $self->updated_at           = new DateTimeImmutable( (string) $row['updated_at'] );
        $self->is_deleted           = ! empty( $row['is_deleted'] );

        return $self;
    }

    /**
     * Export data as array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                     => $this->id,
            'service_id'             => $this->service_id,
            'service_name'           => $this->service_name,
            'service_color'          => $this->service_background_color,
            'service_background_color' => $this->service_background_color,
            'service_text_color'     => $this->service_text_color,
            'employee_id'            => $this->employee_id,
            'employee_name'          => $this->employee_name,
            'customer_id'            => $this->customer_id,
            'customer_account_name'  => $this->customer_account_name,
            'customer_first_name'    => $this->customer_first_name,
            'customer_last_name'     => $this->customer_last_name,
            'customer_phone'         => $this->customer_phone,
            'customer_email'         => $this->customer_email,
            'scheduled_start'        => $this->scheduled_start->format( DateTimeInterface::ATOM ),
            'scheduled_end'          => $this->scheduled_end->format( DateTimeInterface::ATOM ),
            'status'                 => $this->status,
            'payment_status'         => $this->payment_status,
            'total_amount'           => $this->total_amount,
            'currency'               => $this->currency,
            'notes'                  => $this->notes,
            'internal_note'          => $this->internal_note,
            'should_notify'          => $this->should_notify,
            'is_recurring'           => $this->is_recurring,
            'duration_minutes'       => $this->get_duration_minutes(),
            'created_at'             => $this->created_at->format( DateTimeInterface::ATOM ),
            'updated_at'             => $this->updated_at->format( DateTimeInterface::ATOM ),
            'is_deleted'             => $this->is_deleted,
        ];
    }

    /**
     * Get identifier.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Service identifier.
     */
    public function get_service_id(): ?int {
        return $this->service_id;
    }

    /**
     * Service name label.
     */
    public function get_service_name(): ?string {
        return $this->service_name;
    }

    /**
     * Service background color.
     */
    public function get_service_background_color(): ?string {
        return $this->service_background_color;
    }

    /**
     * Service text color.
     */
    public function get_service_text_color(): ?string {
        return $this->service_text_color;
    }

    /**
     * Backward compatible alias for background color.
     */
    public function get_service_color(): ?string {
        return $this->service_background_color;
    }

    /**
     * Employee identifier.
     */
    public function get_employee_id(): ?int {
        return $this->employee_id;
    }

    /**
     * Employee display name.
     */
    public function get_employee_name(): ?string {
        return $this->employee_name;
    }

    /**
     * Customer identifier.
     */
    public function get_customer_id(): ?int {
        return $this->customer_id;
    }

    /**
     * Customer account name.
     */
    public function get_customer_account_name(): ?string {
        return $this->customer_account_name;
    }

    /**
     * Customer first name.
     */
    public function get_customer_first_name(): ?string {
        return $this->customer_first_name;
    }

    /**
     * Customer last name.
     */
    public function get_customer_last_name(): ?string {
        return $this->customer_last_name;
    }

    /**
     * Customer phone value.
     */
    public function get_customer_phone(): ?string {
        return $this->customer_phone;
    }

    /**
     * Customer email value.
     */
    public function get_customer_email(): ?string {
        return $this->customer_email;
    }

    /**
     * Scheduled start object.
     */
    public function get_scheduled_start(): DateTimeImmutable {
        return $this->scheduled_start;
    }

    /**
     * Scheduled end object.
     */
    public function get_scheduled_end(): DateTimeImmutable {
        return $this->scheduled_end;
    }

    /**
     * Appointment status.
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Payment status string.
     */
    public function get_payment_status(): ?string {
        return $this->payment_status;
    }

    /**
     * Total amount.
     */
    public function get_total_amount(): ?float {
        return $this->total_amount;
    }

    /**
     * Currency code.
     */
    public function get_currency(): string {
        return $this->currency;
    }

    /**
     * Public notes value.
     */
    public function get_notes(): ?string {
        return $this->notes;
    }

    /**
     * Internal note value.
     */
    public function get_internal_note(): ?string {
        return $this->internal_note;
    }

    /**
     * Should send notifications.
     */
    public function should_notify(): bool {
        return $this->should_notify;
    }

    /**
     * Recurring flag.
     */
    public function is_recurring(): bool {
        return $this->is_recurring;
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
     * Soft deleted state.
     */
    public function is_deleted(): bool {
        return $this->is_deleted;
    }

    /**
     * Duration in minutes.
     */
    public function get_duration_minutes(): int {
        $interval = $this->scheduled_start->diff( $this->scheduled_end );
        return self::interval_to_minutes( $interval );
    }

    /**
     * Helper to convert interval to minutes.
     */
    private static function interval_to_minutes( DateInterval $interval ): int {
        $minutes = (int) $interval->format( '%r%i' );
        $hours   = (int) $interval->format( '%r%h' );
        $days    = (int) $interval->format( '%r%d' );

        $total = ( $days * 24 * 60 ) + ( $hours * 60 ) + $minutes;

        return (int) abs( $total );
    }
}
