<?php
/**
 * Immutable email notification representation.
 *
 * @package SmoothBooking\Domain\Notifications
 */

namespace SmoothBooking\Domain\Notifications;

use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function is_array;
use function json_decode;
use function preg_replace;
use function sort;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

/**
 * Represents a rule + template pair for email delivery.
 */
class EmailNotification {
    private const RECIPIENT_SYNONYMS = [
        'client'         => 'customer',
        'customer'       => 'customer',
        'employee'       => 'staff',
        'staff'          => 'staff',
        'administrator'  => 'admin',
        'admin'          => 'admin',
        'custom'         => 'custom',
    ];

    private int $id;

    private string $name;

    private bool $enabled;

    private string $template_code;

    private string $trigger_event;

    private string $appointment_status;

    /**
     * @var array<int>
     */
    private array $service_ids;

    private string $service_scope;

    /**
     * @var array<int, string>
     */
    private array $recipients;

    /**
     * @var array<int, string>
     */
    private array $custom_emails;

    private string $send_format;

    private bool $attach_ics;

    private string $subject;

    private string $body_html;

    private string $body_text;

    private int $schedule_offset;

    private string $channel_order;

    private int $priority;

    private ?int $location_id;

    private string $locale;

    private bool $deleted;

    private ?string $created_at;

    private ?string $updated_at;

    public function __construct(
        int $id,
        string $name,
        bool $enabled,
        string $template_code,
        string $trigger_event,
        string $appointment_status,
        array $service_ids,
        string $service_scope,
        array $recipients,
        array $custom_emails,
        string $send_format,
        bool $attach_ics,
        string $subject,
        string $body_html,
        string $body_text,
        int $schedule_offset,
        string $channel_order,
        int $priority,
        ?int $location_id,
        string $locale,
        bool $deleted,
        ?string $created_at,
        ?string $updated_at
    ) {
        $this->id                 = $id;
        $this->name               = $name;
        $this->enabled            = $enabled;
        $this->template_code      = $template_code;
        $this->trigger_event      = $trigger_event;
        $this->appointment_status = $appointment_status;
        $this->service_ids        = $service_ids;
        $this->service_scope      = $service_scope;
        $this->recipients         = $recipients;
        $this->custom_emails      = $custom_emails;
        $this->send_format        = $send_format;
        $this->attach_ics         = $attach_ics;
        $this->subject            = $subject;
        $this->body_html          = $body_html;
        $this->body_text          = $body_text;
        $this->schedule_offset    = $schedule_offset;
        $this->channel_order      = $channel_order;
        $this->priority           = $priority;
        $this->location_id        = $location_id;
        $this->locale             = $locale;
        $this->deleted            = $deleted;
        $this->created_at         = $created_at;
        $this->updated_at         = $updated_at;
    }

    /**
     * Hydrate an entity from a database join row.
     *
     * @param array<string, mixed> $row Rule/template row data.
     */
    public static function from_row( array $row ): self {
        $conditions = [];
        if ( ! empty( $row['conditions_json'] ) ) {
            $decoded = json_decode( (string) $row['conditions_json'], true );
            if ( is_array( $decoded ) ) {
                $conditions = $decoded;
            }
        }

        $settings = [];
        if ( ! empty( $row['settings_json'] ) ) {
            $decoded = json_decode( (string) $row['settings_json'], true );
            if ( is_array( $decoded ) ) {
                $settings = $decoded;
            }
        }

        $service_scope = isset( $conditions['service_scope'] ) ? (string) $conditions['service_scope'] : 'any';
        $service_ids   = [];

        if ( ! empty( $conditions['service_ids'] ) && is_array( $conditions['service_ids'] ) ) {
            $service_ids = array_map( 'absint', $conditions['service_ids'] );
            $service_ids = array_filter( $service_ids, static fn( int $value ): bool => $value > 0 );
        }

        $recipients = [];
        if ( ! empty( $settings['recipients'] ) && is_array( $settings['recipients'] ) ) {
            $recipients = array_map( [ self::class, 'normalize_recipient_key' ], $settings['recipients'] );
            $recipients = array_filter( $recipients, static fn( string $recipient ): bool => '' !== $recipient );
            $recipients = array_values( array_unique( $recipients ) );
        }

        $custom_emails = [];
        if ( ! empty( $settings['custom_emails'] ) && is_array( $settings['custom_emails'] ) ) {
            $custom_emails = array_map( 'strval', $settings['custom_emails'] );
        }

        $appointment_status = isset( $conditions['appointment_status'] ) ? (string) $conditions['appointment_status'] : 'any';
        $send_format        = isset( $settings['send_format'] ) ? (string) $settings['send_format'] : 'html';
        $attach_ics         = ! empty( $settings['attach_ics'] );

        return new self(
            (int) ( $row['rule_id'] ?? 0 ),
            (string) ( $row['display_name'] ?? '' ),
            (int) ( $row['is_enabled'] ?? 0 ) === 1,
            (string) ( $row['template_code'] ?? '' ),
            (string) ( $row['trigger_event'] ?? '' ),
            $appointment_status,
            $service_ids,
            $service_scope,
            $recipients,
            $custom_emails,
            $send_format,
            $attach_ics,
            isset( $row['subject'] ) ? (string) $row['subject'] : '',
            isset( $row['body_html'] ) ? (string) $row['body_html'] : '',
            isset( $row['body_text'] ) ? (string) $row['body_text'] : '',
            isset( $row['schedule_offset_sec'] ) ? (int) $row['schedule_offset_sec'] : 0,
            isset( $row['channel_order'] ) ? (string) $row['channel_order'] : 'email',
            isset( $row['priority'] ) ? (int) $row['priority'] : 100,
            isset( $row['location_id'] ) ? (int) $row['location_id'] ?: null : null,
            isset( $row['locale'] ) ? (string) $row['locale'] : 'en_US',
            (int) ( $row['is_deleted'] ?? 0 ) === 1,
            isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
            isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null
        );
    }

    /**
     * Normalize the stored recipient key into canonical form.
     */
    public static function normalize_recipient_key( string $recipient ): string {
        $key = strtolower( trim( $recipient ) );

        return self::RECIPIENT_SYNONYMS[ $key ] ?? $key;
    }

    /**
     * Generate the lookup key used for template uniqueness.
     *
     * @param array<int, string> $recipients Recipient identifiers.
     */
    public static function generate_template_lookup( string $trigger_event, array $recipients, string $channel = 'email' ): string {
        $normalised = array_filter(
            array_map( [ self::class, 'normalize_recipient_key' ], $recipients ),
            static fn( string $value ): bool => '' !== $value
        );

        if ( empty( $normalised ) ) {
            $normalised = [ 'customer' ];
        }

        sort( $normalised );

        $recipient_key = implode( '+', $normalised );

        $event_key = preg_replace( '/[^a-z0-9]+/i', ' ', strtolower( $trigger_event ) );
        $event_key = ucwords( (string) $event_key );
        $event_key = str_replace( ' ', '', $event_key );

        $channel_key = strtolower( trim( $channel ) );
        if ( '' === $channel_key ) {
            $channel_key = 'email';
        }

        return sprintf( '%s-%s-%s', $event_key, $recipient_key, $channel_key );
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function is_enabled(): bool {
        return $this->enabled;
    }

    public function get_trigger_event(): string {
        return $this->trigger_event;
    }

    public function get_template_code(): string {
        return $this->template_code;
    }

    public function get_appointment_status(): string {
        return $this->appointment_status;
    }

    /**
     * @return array<int>
     */
    public function get_service_ids(): array {
        return $this->service_ids;
    }

    public function get_service_scope(): string {
        return $this->service_scope;
    }

    /**
     * @return array<int, string>
     */
    public function get_recipients(): array {
        return $this->recipients;
    }

    /**
     * @return array<int, string>
     */
    public function get_custom_emails(): array {
        return $this->custom_emails;
    }

    public function get_send_format(): string {
        return $this->send_format;
    }

    public function should_attach_ics(): bool {
        return $this->attach_ics;
    }

    public function get_subject(): string {
        return $this->subject;
    }

    public function get_body_html(): string {
        return $this->body_html;
    }

    public function get_body_text(): string {
        return $this->body_text;
    }

    public function get_schedule_offset(): int {
        return $this->schedule_offset;
    }

    public function get_channel_order(): string {
        return $this->channel_order;
    }

    public function get_priority(): int {
        return $this->priority;
    }

    public function get_location_id(): ?int {
        return $this->location_id;
    }

    public function get_locale(): string {
        return $this->locale;
    }

    public function is_deleted(): bool {
        return $this->deleted;
    }

    public function get_created_at(): ?string {
        return $this->created_at;
    }

    public function get_updated_at(): ?string {
        return $this->updated_at;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                 => $this->get_id(),
            'name'               => $this->get_name(),
            'enabled'            => $this->is_enabled(),
            'trigger_event'      => $this->get_trigger_event(),
            'template_code'      => $this->get_template_code(),
            'appointment_status' => $this->get_appointment_status(),
            'service_ids'        => $this->get_service_ids(),
            'service_scope'      => $this->get_service_scope(),
            'recipients'         => $this->get_recipients(),
            'custom_emails'      => $this->get_custom_emails(),
            'send_format'        => $this->get_send_format(),
            'attach_ics'         => $this->should_attach_ics(),
            'subject'            => $this->get_subject(),
            'body_html'          => $this->get_body_html(),
            'body_text'          => $this->get_body_text(),
            'schedule_offset'    => $this->get_schedule_offset(),
            'channel_order'      => $this->get_channel_order(),
            'priority'           => $this->get_priority(),
            'location_id'        => $this->get_location_id(),
            'locale'             => $this->get_locale(),
            'deleted'            => $this->is_deleted(),
            'created_at'         => $this->get_created_at(),
            'updated_at'         => $this->get_updated_at(),
        ];
    }
}
