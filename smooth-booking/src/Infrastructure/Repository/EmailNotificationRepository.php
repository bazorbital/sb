<?php
/**
 * wpdb-backed repository for email notifications.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Notifications\EmailNotification;
use SmoothBooking\Domain\Notifications\EmailNotificationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use function __;
use function current_time;
use function get_locale;
use function is_wp_error;
use function wp_generate_password;
use const ARRAY_A;

/**
 * Provides CRUD access to notification rules/templates.
 */
class EmailNotificationRepository implements EmailNotificationRepositoryInterface {
    private wpdb $wpdb;

    private Logger $logger;

    private ?int $email_channel_id = null;

    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function list( bool $include_deleted = false ): array {
        $channel_id = $this->ensure_email_channel();

        if ( is_wp_error( $channel_id ) ) {
            return [];
        }

        $rules     = $this->get_rules_table();
        $templates = $this->get_templates_table();

        $sql = "SELECT r.*, t.subject, t.body_text, t.body_html, t.locale, t.is_active FROM {$rules} r INNER JOIN {$templates} t ON t.code = r.template_code WHERE t.channel_id = %d";

        $params = [ $channel_id ];

        if ( ! $include_deleted ) {
            $sql     .= ' AND r.is_deleted = %d';
            $params[] = 0;
        }

        $sql .= ' ORDER BY r.display_name ASC';

        $prepared = $this->wpdb->prepare( $sql, $params );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map( [ EmailNotification::class, 'from_row' ], $rows );
    }

    /**
     * {@inheritDoc}
     */
    public function find( int $notification_id, bool $include_deleted = false ): ?EmailNotification {
        if ( $notification_id <= 0 ) {
            return null;
        }

        $channel_id = $this->ensure_email_channel();

        if ( is_wp_error( $channel_id ) ) {
            return null;
        }

        $rules     = $this->get_rules_table();
        $templates = $this->get_templates_table();

        $sql = "SELECT r.*, t.subject, t.body_text, t.body_html, t.locale, t.is_active FROM {$rules} r INNER JOIN {$templates} t ON t.code = r.template_code WHERE t.channel_id = %d AND r.rule_id = %d";
        $params = [ $channel_id, $notification_id ];

        if ( ! $include_deleted ) {
            $sql     .= ' AND r.is_deleted = %d';
            $params[] = 0;
        }

        $prepared = $this->wpdb->prepare( $sql, $params );
        $row      = $this->wpdb->get_row( $prepared, ARRAY_A );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return EmailNotification::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $channel_id = $this->ensure_email_channel();

        if ( is_wp_error( $channel_id ) ) {
            return $channel_id;
        }

        $template = $data['template'] ?? [];
        $code     = $this->generate_template_code();

        $templates_table = $this->get_templates_table();
        $inserted_template = $this->wpdb->insert(
            $templates_table,
            [
                'code'        => $code,
                'channel_id'  => $channel_id,
                'locale'      => $template['locale'] ?? get_locale(),
                'subject'     => $template['subject'] ?? '',
                'body_text'   => $template['body_text'] ?? '',
                'body_html'   => $template['body_html'] ?? '',
                'is_active'   => $data['is_enabled'] ?? 1,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false === $inserted_template ) {
            $this->logger->error( 'Failed inserting notification template: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_template_failed',
                __( 'Unable to create the notification template. Please try again.', 'smooth-booking' )
            );
        }

        $rules_table = $this->get_rules_table();
        $rule_insert = $this->wpdb->insert(
            $rules_table,
            [
                'display_name'        => $data['display_name'],
                'template_code'       => $code,
                'location_id'         => $data['location_id'],
                'trigger_event'       => $data['trigger_event'],
                'schedule_offset_sec' => $data['schedule_offset_sec'],
                'channel_order'       => $data['channel_order'],
                'conditions_json'     => $data['conditions_json'],
                'settings_json'       => $data['settings_json'],
                'is_enabled'          => $data['is_enabled'],
                'priority'            => $data['priority'],
                'is_deleted'          => 0,
                'created_at'          => current_time( 'mysql' ),
                'updated_at'          => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ]
        );

        if ( false === $rule_insert ) {
            $this->logger->error( 'Failed inserting notification rule: ' . $this->wpdb->last_error );
            $this->wpdb->delete( $templates_table, [ 'code' => $code ], [ '%s' ] );

            return new WP_Error(
                'smooth_booking_notification_rule_failed',
                __( 'Unable to create the notification rule. Please try again.', 'smooth-booking' )
            );
        }

        $id = (int) $this->wpdb->insert_id;

        return $this->find( $id, true );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $notification_id, array $data ) {
        $existing = $this->find( $notification_id, true );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_notification_not_found',
                __( 'The requested notification could not be found.', 'smooth-booking' )
            );
        }

        $template_code = $data['template_code'] ?? $existing->get_template_code();
        $channel_id    = $this->ensure_email_channel();

        if ( is_wp_error( $channel_id ) ) {
            return $channel_id;
        }

        $rules_table = $this->get_rules_table();
        $updated     = $this->wpdb->update(
            $rules_table,
            [
                'display_name'        => $data['display_name'],
                'trigger_event'       => $data['trigger_event'],
                'schedule_offset_sec' => $data['schedule_offset_sec'],
                'channel_order'       => $data['channel_order'],
                'conditions_json'     => $data['conditions_json'],
                'settings_json'       => $data['settings_json'],
                'is_enabled'          => $data['is_enabled'],
                'priority'            => $data['priority'],
                'location_id'         => $data['location_id'],
                'updated_at'          => current_time( 'mysql' ),
            ],
            [ 'rule_id' => $notification_id ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $this->logger->error( 'Failed updating notification rule: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_update_failed',
                __( 'Unable to update the notification rule. Please try again.', 'smooth-booking' )
            );
        }

        $template_data = $data['template'] ?? [];

        $templates_table = $this->get_templates_table();
        $template_update = $this->wpdb->update(
            $templates_table,
            [
                'subject'    => $template_data['subject'] ?? '',
                'body_text'  => $template_data['body_text'] ?? '',
                'body_html'  => $template_data['body_html'] ?? '',
                'locale'     => $template_data['locale'] ?? get_locale(),
                'is_active'  => $data['is_enabled'],
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'code'       => $template_code,
                'channel_id' => $channel_id,
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s' ],
            [ '%s', '%d' ]
        );

        if ( false === $template_update ) {
            $this->logger->error( 'Failed updating notification template: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_template_update_failed',
                __( 'Unable to update the notification template. Please try again.', 'smooth-booking' )
            );
        }

        return $this->find( $notification_id, true );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $notification_id ) {
        $rules_table = $this->get_rules_table();

        $deleted = $this->wpdb->update(
            $rules_table,
            [
                'is_deleted' => 1,
                'is_enabled' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'rule_id' => $notification_id ],
            [ '%d', '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $deleted ) {
            $this->logger->error( 'Failed soft deleting notification: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_soft_delete_failed',
                __( 'Unable to delete the notification. Please try again.', 'smooth-booking' )
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function force_delete( int $notification_id ) {
        $existing = $this->find( $notification_id, true );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_notification_not_found',
                __( 'The requested notification could not be found.', 'smooth-booking' )
            );
        }

        $rules_table     = $this->get_rules_table();
        $templates_table = $this->get_templates_table();

        $deleted_rule = $this->wpdb->delete( $rules_table, [ 'rule_id' => $notification_id ], [ '%d' ] );

        if ( false === $deleted_rule ) {
            $this->logger->error( 'Failed deleting notification rule: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_delete_failed',
                __( 'Unable to delete the notification. Please try again.', 'smooth-booking' )
            );
        }

        $this->wpdb->delete(
            $templates_table,
            [ 'code' => $existing->get_template_code() ],
            [ '%s' ]
        );

        return true;
    }

    /**
     * Ensure the email channel exists and return its identifier.
     *
     * @return int|WP_Error
     */
    private function ensure_email_channel() {
        if ( null !== $this->email_channel_id ) {
            return $this->email_channel_id;
        }

        $channels_table = $this->get_channels_table();
        $query          = $this->wpdb->prepare( "SELECT channel_id FROM {$channels_table} WHERE code = %s", 'email' );
        $existing       = $this->wpdb->get_var( $query );

        if ( $existing ) {
            $this->email_channel_id = (int) $existing;

            return $this->email_channel_id;
        }

        $inserted = $this->wpdb->insert(
            $channels_table,
            [
                'code'        => 'email',
                'description' => __( 'Email notifications', 'smooth-booking' ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            $this->logger->error( 'Failed creating email notification channel: ' . $this->wpdb->last_error );

            return new WP_Error(
                'smooth_booking_notification_channel_failed',
                __( 'Unable to prepare the email channel. Please try again.', 'smooth-booking' )
            );
        }

        $this->email_channel_id = (int) $this->wpdb->insert_id;

        return $this->email_channel_id;
    }

    private function generate_template_code(): string {
        return 'EMAIL_' . strtoupper( wp_generate_password( 12, false, false ) );
    }

    private function get_rules_table(): string {
        return $this->wpdb->prefix . 'smooth_notification_rules';
    }

    private function get_templates_table(): string {
        return $this->wpdb->prefix . 'smooth_notification_templates';
    }

    private function get_channels_table(): string {
        return $this->wpdb->prefix . 'smooth_notification_channels';
    }
}
