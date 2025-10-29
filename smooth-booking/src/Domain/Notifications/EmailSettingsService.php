<?php
/**
 * Manage email notification settings.
 *
 * @package SmoothBooking\Domain\Notifications
 */

namespace SmoothBooking\Domain\Notifications;

use PHPMailer\PHPMailer\PHPMailer;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

use function __;
use function add_filter;
use function add_action;
use function get_bloginfo;
use function get_option;
use function is_email;
use function sanitize_email;
use function sanitize_text_field;
use function update_option;
use function wp_mail;
use function wp_unslash;
use function rest_sanitize_boolean;
use function absint;
use function in_array;
use function is_array;
use function trim;
use function wpautop;
use function wp_strip_all_tags;
use function _n;

/**
 * Provides accessors for email sending preferences.
 */
class EmailSettingsService {
    private const OPTION_NAME = 'smooth_booking_email_settings';

    private Logger $logger;

    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Retrieve merged settings.
     *
     * @return array<string, mixed>
     */
    public function get_settings(): array {
        $stored = get_option( self::OPTION_NAME, [] );

        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        return array_merge( $this->get_default_settings(), $stored );
    }

    /**
     * Default settings.
     *
     * @return array<string, mixed>
     */
    public function get_default_settings(): array {
        return [
            'sender_name'          => get_bloginfo( 'name' ),
            'sender_email'         => sanitize_email( get_bloginfo( 'admin_email' ) ),
            'send_format'          => 'html',
            'reply_to_customer'    => 1,
            'retry_period_hours'   => 1,
            'mail_gateway'         => 'wordpress',
            'smtp'                 => [
                'host'     => '',
                'port'     => '',
                'username' => '',
                'password' => '',
                'secure'   => 'disabled',
            ],
        ];
    }

    /**
     * Sanitize and persist settings.
     *
     * @param array<string, mixed> $input Raw input.
     *
     * @return array<string, mixed>
     */
    public function save_settings( array $input ): array {
        $sanitized = $this->sanitize_settings( $input );

        update_option( self::OPTION_NAME, $sanitized );

        return $sanitized;
    }

    /**
     * Sanitize input without persisting.
     *
     * @param array<string, mixed> $input Raw values.
     *
     * @return array<string, mixed>
     */
    public function sanitize_settings( array $input ): array {
        $defaults = $this->get_default_settings();
        $current  = get_option( self::OPTION_NAME, [] );

        if ( ! is_array( $current ) ) {
            $current = [];
        }

        $sender_name  = isset( $input['sender_name'] ) ? sanitize_text_field( wp_unslash( (string) $input['sender_name'] ) ) : $defaults['sender_name'];
        $sender_email = isset( $input['sender_email'] ) ? sanitize_email( wp_unslash( (string) $input['sender_email'] ) ) : $defaults['sender_email'];

        if ( ! is_email( $sender_email ) ) {
            $sender_email = $defaults['sender_email'];
        }

        $format = isset( $input['send_format'] ) ? sanitize_text_field( wp_unslash( (string) $input['send_format'] ) ) : 'html';
        if ( ! in_array( $format, [ 'html', 'text' ], true ) ) {
            $format = 'html';
        }

        $retry = isset( $input['retry_period_hours'] ) ? absint( $input['retry_period_hours'] ) : 1;
        if ( $retry < 1 || $retry > 23 ) {
            $retry = 1;
        }

        $gateway = isset( $input['mail_gateway'] ) ? sanitize_text_field( wp_unslash( (string) $input['mail_gateway'] ) ) : 'wordpress';
        if ( ! in_array( $gateway, [ 'wordpress', 'smtp' ], true ) ) {
            $gateway = 'wordpress';
        }

        $smtp_input = isset( $input['smtp'] ) && is_array( $input['smtp'] ) ? $input['smtp'] : [];
        $smtp = [
            'host'     => isset( $smtp_input['host'] ) ? sanitize_text_field( wp_unslash( (string) $smtp_input['host'] ) ) : '',
            'port'     => isset( $smtp_input['port'] ) ? sanitize_text_field( wp_unslash( (string) $smtp_input['port'] ) ) : '',
            'username' => isset( $smtp_input['username'] ) ? sanitize_text_field( wp_unslash( (string) $smtp_input['username'] ) ) : '',
            'password' => isset( $smtp_input['password'] ) ? (string) wp_unslash( (string) $smtp_input['password'] ) : '',
            'secure'   => isset( $smtp_input['secure'] ) ? sanitize_text_field( wp_unslash( (string) $smtp_input['secure'] ) ) : 'disabled',
        ];

        if ( ! in_array( $smtp['secure'], [ 'disabled', 'ssl', 'tls' ], true ) ) {
            $smtp['secure'] = 'disabled';
        }

        if ( '' === trim( $smtp['password'] ) && isset( $current['smtp']['password'] ) ) {
            $smtp['password'] = (string) $current['smtp']['password'];
        }

        return [
            'sender_name'        => $sender_name,
            'sender_email'       => $sender_email,
            'send_format'        => $format,
            'reply_to_customer'  => rest_sanitize_boolean( $input['reply_to_customer'] ?? 0 ) ? 1 : 0,
            'retry_period_hours' => $retry,
            'mail_gateway'       => $gateway,
            'smtp'               => $smtp,
        ];
    }

    /**
     * Register mail filters and hooks.
     */
    public function register_hooks(): void {
        add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );
        add_filter( 'wp_mail_content_type', [ $this, 'filter_mail_content_type' ] );
        add_action( 'phpmailer_init', [ $this, 'configure_phpmailer' ] );
    }

    /**
     * Filter outgoing mail sender address.
     */
    public function filter_mail_from( string $address ): string {
        $settings = $this->get_settings();
        $email    = isset( $settings['sender_email'] ) ? (string) $settings['sender_email'] : '';

        return is_email( $email ) ? $email : $address;
    }

    /**
     * Filter outgoing mail sender name.
     */
    public function filter_mail_from_name( string $name ): string {
        $settings = $this->get_settings();
        $sender   = isset( $settings['sender_name'] ) ? (string) $settings['sender_name'] : '';

        return '' !== $sender ? $sender : $name;
    }

    /**
     * Filter content type.
     */
    public function filter_mail_content_type( string $type ): string {
        $settings = $this->get_settings();

        return 'text' === ( $settings['send_format'] ?? 'html' ) ? 'text/plain' : 'text/html';
    }

    /**
     * Configure PHPMailer for SMTP gateway.
     */
    public function configure_phpmailer( PHPMailer $phpmailer ): void {
        $settings = $this->get_settings();

        if ( ( $settings['mail_gateway'] ?? 'wordpress' ) !== 'smtp' ) {
            return;
        }

        $smtp = $settings['smtp'] ?? [];
        $host = isset( $smtp['host'] ) ? (string) $smtp['host'] : '';
        $port = isset( $smtp['port'] ) ? (int) $smtp['port'] : 0;

        if ( '' === $host ) {
            $this->logger->warning( 'SMTP gateway selected but host is empty.' );
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port > 0 ? $port : 587;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = (string) ( $smtp['username'] ?? '' );
        $phpmailer->Password   = (string) ( $smtp['password'] ?? '' );
        $phpmailer->SMTPSecure = '';

        if ( isset( $smtp['secure'] ) ) {
            if ( 'ssl' === $smtp['secure'] ) {
                $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ( 'tls' === $smtp['secure'] ) {
                $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }
    }

    /**
     * Send a test email to confirm settings.
     *
     * @return true|WP_Error
     */
    public function send_test_email( string $recipient ) {
        $recipient = sanitize_email( $recipient );

        if ( ! is_email( $recipient ) ) {
            return new WP_Error(
                'smooth_booking_invalid_test_email',
                __( 'Please provide a valid email address for the test message.', 'smooth-booking' )
            );
        }

        $settings = $this->get_settings();
        $format   = $settings['send_format'] ?? 'html';
        $subject  = __( 'Smooth Booking test email', 'smooth-booking' );
        $message  = __( 'This is a test email from Smooth Booking. Your notification settings are working correctly.', 'smooth-booking' );

        if ( 'html' === $format ) {
            $message = wpautop( $message );
        } else {
            $message = wp_strip_all_tags( $message );
        }

        $result = wp_mail( $recipient, $subject, $message );

        if ( ! $result ) {
            return new WP_Error(
                'smooth_booking_test_email_failed',
                __( 'The test email could not be sent. Please verify your settings.', 'smooth-booking' )
            );
        }

        return true;
    }

    /**
     * Retrieve retry period options.
     *
     * @return array<int, string>
     */
    public function get_retry_period_options(): array {
        $options = [];

        for ( $hour = 1; $hour <= 23; $hour++ ) {
            /* translators: %d: hours */
            $options[ $hour ] = sprintf( _n( '%d hour', '%d hours', $hour, 'smooth-booking' ), $hour );
        }

        return $options;
    }
}
