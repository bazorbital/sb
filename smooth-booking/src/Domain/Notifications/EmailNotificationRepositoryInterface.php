<?php
/**
 * Contract for email notification persistence.
 *
 * @package SmoothBooking\Domain\Notifications
 */

namespace SmoothBooking\Domain\Notifications;

/**
 * Repository abstraction for email notifications.
 */
interface EmailNotificationRepositoryInterface {
    /**
     * List notifications.
     *
     * @param bool $include_deleted Include soft-deleted notifications.
     *
     * @return EmailNotification[]
     */
    public function list( bool $include_deleted = false ): array;

    /**
     * Retrieve a notification by identifier.
     *
     * @param bool $include_deleted Include soft-deleted notifications.
     */
    public function find( int $notification_id, bool $include_deleted = false ): ?EmailNotification;

    /**
     * Create a notification.
     *
     * @param array<string, mixed> $data Sanitised payload.
     *
     * @return EmailNotification|\WP_Error
     */
    public function create( array $data );

    /**
     * Update an existing notification.
     *
     * @param int                   $notification_id Notification identifier.
     * @param array<string, mixed> $data             Sanitised payload.
     *
     * @return EmailNotification|\WP_Error
     */
    public function update( int $notification_id, array $data );

    /**
     * Soft delete a notification.
     *
     * @return true|\WP_Error
     */
    public function soft_delete( int $notification_id );

    /**
     * Permanently delete a notification.
     *
     * @return true|\WP_Error
     */
    public function force_delete( int $notification_id );

    /**
     * Determine whether a template lookup already exists.
     *
     * @param string   $template_lookup Canonical lookup identifier.
     * @param int|null $exclude_rule_id Optional rule identifier to exclude from the check.
     */
    public function template_lookup_exists( string $template_lookup, ?int $exclude_rule_id = null ): bool;
}
