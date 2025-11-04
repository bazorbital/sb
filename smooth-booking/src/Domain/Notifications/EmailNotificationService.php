<?php
/**
 * Business logic for managing email notifications.
 *
 * @package SmoothBooking\Domain\Notifications
 */

namespace SmoothBooking\Domain\Notifications;

use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

use function __;
use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function esc_html__;
use function explode;
use function get_locale;
use function in_array;
use function is_array;
use function is_email;
use function is_wp_error;
use function sanitize_email;
use function sanitize_text_field;
use function trim;
use function wp_kses_post;
use function wp_strip_all_tags;
use function wp_unslash;
use function wp_json_encode;
use function rest_sanitize_boolean;

/**
 * Provides validation and orchestration for email notifications.
 */
class EmailNotificationService {
    private EmailNotificationRepositoryInterface $repository;

    private ServiceService $service_service;

    private Logger $logger;

    public function __construct( EmailNotificationRepositoryInterface $repository, ServiceService $service_service, Logger $logger ) {
        $this->repository      = $repository;
        $this->service_service = $service_service;
        $this->logger          = $logger;
    }

    /**
     * List notifications.
     *
     * @return EmailNotification[]
     */
    public function list_notifications( bool $include_deleted = false ): array {
        return $this->repository->list( $include_deleted );
    }

    /**
     * Retrieve a notification.
     *
     * @return EmailNotification|WP_Error
     */
    public function get_notification( int $notification_id, bool $include_deleted = false ) {
        $notification = $this->repository->find( $notification_id, $include_deleted );

        if ( null === $notification ) {
            return new WP_Error(
                'smooth_booking_notification_not_found',
                __( 'The requested notification could not be found.', 'smooth-booking' )
            );
        }

        return $notification;
    }

    /**
     * Create a new notification.
     *
     * @param array<string, mixed> $data Raw payload.
     *
     * @return EmailNotification|WP_Error
     */
    public function create_notification( array $data ) {
        $validated = $this->validate_notification_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $template_lookup = $validated['template_lookup'] ?? '';
        if ( $template_lookup && $this->repository->template_lookup_exists( $template_lookup ) ) {
            return new WP_Error(
                'smooth_booking_notification_duplicate',
                __( 'A notification with this event and recipient combination already exists.', 'smooth-booking' )
            );
        }

        $result = $this->repository->create( $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed creating notification: ' . $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Update an existing notification.
     *
     * @param array<string, mixed> $data Raw payload.
     *
     * @return EmailNotification|WP_Error
     */
    public function update_notification( int $notification_id, array $data ) {
        $existing = $this->repository->find( $notification_id, true );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_notification_not_found',
                __( 'The requested notification could not be found.', 'smooth-booking' )
            );
        }

        if ( $existing->is_deleted() ) {
            return new WP_Error(
                'smooth_booking_notification_deleted',
                __( 'The notification has been deleted and must be restored before editing.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_notification_data( $data, $existing );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $template_lookup = $validated['template_lookup'] ?? '';
        if ( $template_lookup && $this->repository->template_lookup_exists( $template_lookup, $notification_id ) ) {
            return new WP_Error(
                'smooth_booking_notification_duplicate',
                __( 'A notification with this event and recipient combination already exists.', 'smooth-booking' )
            );
        }

        $result = $this->repository->update( $notification_id, $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed updating notification: ' . $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Disable a notification without deleting it.
     */
    public function disable_notification( int $notification_id ) {
        $notification = $this->repository->find( $notification_id, true );

        if ( null === $notification ) {
            return new WP_Error(
                'smooth_booking_notification_not_found',
                __( 'The requested notification could not be found.', 'smooth-booking' )
            );
        }

        $custom_emails = implode( "\n", $notification->get_custom_emails() );

        return $this->update_notification(
            $notification_id,
            [
                'name'               => $notification->get_name(),
                'status'             => 'disabled',
                'type'               => $notification->get_trigger_event(),
                'appointment_status' => $notification->get_appointment_status(),
                'service_scope'      => $notification->get_service_scope(),
                'service_ids'        => $notification->get_service_ids(),
                'recipients'         => $notification->get_recipients(),
                'custom_emails'      => $custom_emails,
                'send_format'        => $notification->get_send_format(),
                'subject'            => $notification->get_subject(),
                'body_html'          => $notification->get_body_html(),
                'body_text'          => $notification->get_body_text(),
                'attach_ics'         => $notification->should_attach_ics(),
                'locale'             => $notification->get_locale(),
                'location_id'        => $notification->get_location_id(),
            ]
        );
    }

    /**
     * Soft delete a notification.
     */
    public function soft_delete_notification( int $notification_id ) {
        return $this->repository->soft_delete( $notification_id );
    }

    /**
     * Permanently delete a notification.
     */
    public function force_delete_notification( int $notification_id ) {
        return $this->repository->force_delete( $notification_id );
    }

    /**
     * Retrieve selectable notification types.
     *
     * @return array<string, array{label:string,description:string}>
     */
    public function get_notification_types(): array {
        return [
            'booking.created'  => [
                'label'       => esc_html__( 'New booking notification', 'smooth-booking' ),
                'description' => esc_html__( 'Send immediately when a new booking is created.', 'smooth-booking' ),
            ],
            'booking.reminder' => [
                'label'       => esc_html__( 'Upcoming booking reminder', 'smooth-booking' ),
                'description' => esc_html__( 'Send reminders ahead of scheduled bookings.', 'smooth-booking' ),
            ],
            'booking.updated'  => [
                'label'       => esc_html__( 'Booking updated notification', 'smooth-booking' ),
                'description' => esc_html__( 'Send when a booking changes.', 'smooth-booking' ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_appointment_status_options(): array {
        return [
            'any'       => esc_html__( 'Any', 'smooth-booking' ),
            'pending'   => esc_html__( 'Pending', 'smooth-booking' ),
            'approved'  => esc_html__( 'Approved', 'smooth-booking' ),
            'cancelled' => esc_html__( 'Cancelled', 'smooth-booking' ),
            'rejected'  => esc_html__( 'Rejected', 'smooth-booking' ),
            'done'      => esc_html__( 'Done', 'smooth-booking' ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_recipient_options(): array {
        return [
            'customer' => esc_html__( 'Client', 'smooth-booking' ),
            'staff'    => esc_html__( 'Employee', 'smooth-booking' ),
            'admin'    => esc_html__( 'Administrators', 'smooth-booking' ),
            'custom'   => esc_html__( 'Custom', 'smooth-booking' ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_send_format_options(): array {
        return [
            'html' => esc_html__( 'HTML', 'smooth-booking' ),
            'text' => esc_html__( 'Text', 'smooth-booking' ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_service_scope_options(): array {
        return [
            'any'      => esc_html__( 'Any service', 'smooth-booking' ),
            'selected' => esc_html__( 'Specific services', 'smooth-booking' ),
        ];
    }

    /**
     * Retrieve available services for selection.
     *
     * @return array<int, array{value:int,label:string}>
     */
    public function get_service_choices(): array {
        $services = $this->service_service->list_services();

        return array_map(
            static function ( Service $service ): array {
                return [
                    'value' => $service->get_id(),
                    'label' => $service->get_name(),
                ];
            },
            $services
        );
    }

    /**
     * Validate and normalize notification data.
     *
     * @param array<string, mixed>     $data     Submitted data.
     * @param EmailNotification|null $existing Existing notification (for update).
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validate_notification_data( array $data, ?EmailNotification $existing = null ) {
        $name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( (string) $data['name'] ) ) : '';

        if ( '' === $name ) {
            return new WP_Error(
                'smooth_booking_notification_invalid_name',
                __( 'Notification name is required.', 'smooth-booking' )
            );
        }

        $status  = isset( $data['status'] ) ? sanitize_text_field( wp_unslash( (string) $data['status'] ) ) : 'enabled';
        $enabled = 'enabled' === $status;

        $type = isset( $data['type'] ) ? sanitize_text_field( wp_unslash( (string) $data['type'] ) ) : 'booking.created';
        $types = array_keys( $this->get_notification_types() );

        if ( ! in_array( $type, $types, true ) ) {
            return new WP_Error(
                'smooth_booking_notification_invalid_type',
                __( 'Please choose a valid notification type.', 'smooth-booking' )
            );
        }

        $appointment_status = isset( $data['appointment_status'] ) ? sanitize_text_field( wp_unslash( (string) $data['appointment_status'] ) ) : 'any';
        $status_options     = array_keys( $this->get_appointment_status_options() );

        if ( ! in_array( $appointment_status, $status_options, true ) ) {
            return new WP_Error(
                'smooth_booking_notification_invalid_status',
                __( 'Please choose a valid appointment status.', 'smooth-booking' )
            );
        }

        $service_scope = isset( $data['service_scope'] ) ? sanitize_text_field( wp_unslash( (string) $data['service_scope'] ) ) : 'any';
        $scope_options = array_keys( $this->get_service_scope_options() );

        if ( ! in_array( $service_scope, $scope_options, true ) ) {
            return new WP_Error(
                'smooth_booking_notification_invalid_scope',
                __( 'Please choose whether the notification targets any service or specific ones.', 'smooth-booking' )
            );
        }

        $service_ids = [];
        if ( ! empty( $data['service_ids'] ) && is_array( $data['service_ids'] ) ) {
            $service_ids = array_map( static fn( $value ): int => absint( $value ), $data['service_ids'] );
            $service_ids = array_filter( $service_ids, static fn( int $value ): bool => $value > 0 );
        }

        if ( 'selected' === $service_scope && empty( $service_ids ) ) {
            return new WP_Error(
                'smooth_booking_notification_missing_services',
                __( 'Select at least one service for this notification.', 'smooth-booking' )
            );
        }

        $recipient_input   = isset( $data['recipients'] ) && is_array( $data['recipients'] ) ? $data['recipients'] : [];
        $recipient_options = array_keys( $this->get_recipient_options() );

        $recipients = [];
        foreach ( $recipient_input as $value ) {
            $raw        = sanitize_text_field( wp_unslash( (string) $value ) );
            $normalised = EmailNotification::normalize_recipient_key( $raw );

            if ( in_array( $normalised, $recipient_options, true ) ) {
                $recipients[] = $normalised;
            }
        }

        $recipients = array_values( array_unique( $recipients ) );

        if ( empty( $recipients ) ) {
            return new WP_Error(
                'smooth_booking_notification_missing_recipient',
                __( 'Choose at least one recipient.', 'smooth-booking' )
            );
        }

        if ( in_array( 'custom', $recipients, true ) && count( $recipients ) > 1 ) {
            return new WP_Error(
                'smooth_booking_notification_invalid_recipient_mix',
                __( 'Custom recipients cannot be combined with other recipient types.', 'smooth-booking' )
            );
        }

        $custom_emails = [];
        if ( in_array( 'custom', $recipients, true ) ) {
            $raw_custom = isset( $data['custom_emails'] ) ? (string) wp_unslash( $data['custom_emails'] ) : '';
            $lines      = array_filter( array_map( 'trim', explode( "\n", $raw_custom ) ) );

            foreach ( $lines as $line ) {
                $email = sanitize_email( $line );

                if ( '' === $email || ! is_email( $email ) ) {
                    return new WP_Error(
                        'smooth_booking_notification_invalid_custom_email',
                        __( 'Please provide valid custom email addresses (one per line).', 'smooth-booking' )
                    );
                }

                $custom_emails[] = $email;
            }
        }

        $send_format = isset( $data['send_format'] ) ? sanitize_text_field( wp_unslash( (string) $data['send_format'] ) ) : 'html';
        $format_options = array_keys( $this->get_send_format_options() );

        if ( ! in_array( $send_format, $format_options, true ) ) {
            $send_format = 'html';
        }

        $subject = isset( $data['subject'] ) ? sanitize_text_field( wp_unslash( (string) $data['subject'] ) ) : '';
        $body_html = isset( $data['body_html'] ) ? wp_kses_post( wp_unslash( (string) $data['body_html'] ) ) : '';
        $body_text = isset( $data['body_text'] ) ? wp_strip_all_tags( wp_unslash( (string) $data['body_text'] ), true ) : '';

        if ( 'text' === $send_format && '' === trim( $body_text ) ) {
            return new WP_Error(
                'smooth_booking_notification_missing_body_text',
                __( 'Provide the email body for text format notifications.', 'smooth-booking' )
            );
        }

        if ( '' === $body_text ) {
            $body_text = wp_strip_all_tags( $body_html, true );
        }

        $attach_ics = rest_sanitize_boolean( $data['attach_ics'] ?? false );

        $locale = isset( $data['locale'] ) ? sanitize_text_field( wp_unslash( (string) $data['locale'] ) ) : get_locale();
        if ( '' === $locale ) {
            $locale = get_locale();
        }

        $location_id = isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0;
        if ( $location_id <= 0 ) {
            $location_id = null;
        }

        $conditions = [
            'appointment_status' => $appointment_status,
            'service_scope'      => $service_scope,
            'service_ids'        => $service_scope === 'selected' ? $service_ids : [],
        ];

        $settings = [
            'recipients'    => $recipients,
            'custom_emails' => $custom_emails,
            'send_format'   => $send_format,
            'attach_ics'    => (bool) $attach_ics,
        ];

        $template_lookup = EmailNotification::generate_template_lookup( $type, $recipients );

        $payload = [
            'display_name'        => $name,
            'is_enabled'          => $enabled ? 1 : 0,
            'trigger_event'       => $type,
            'schedule_offset_sec' => 0,
            'channel_order'       => 'email',
            'priority'            => $existing ? $existing->get_priority() : 100,
            'location_id'         => $location_id,
            'conditions_json'     => wp_json_encode( $conditions ),
            'settings_json'       => wp_json_encode( $settings ),
            'template_lookup'     => $template_lookup,
            'template'            => [
                'subject'   => $subject,
                'body_html' => $body_html,
                'body_text' => $body_text,
                'locale'    => $locale,
            ],
        ];

        if ( $existing ) {
            $payload['template_code'] = $existing->get_template_code();
        }

        return $payload;
    }
}
