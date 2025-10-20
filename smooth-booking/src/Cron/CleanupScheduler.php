<?php
/**
 * Handles scheduled maintenance tasks.
 *
 * @package SmoothBooking\Cron
 */

namespace SmoothBooking\Cron;

use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;

/**
 * Registers cron events for the plugin.
 */
class CleanupScheduler {
    /**
     * Cron event name.
     */
    public const EVENT_HOOK = 'smooth_booking_cleanup_event';

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var SchemaManager
     */
    private SchemaManager $schema_manager;

    /**
     * Constructor.
     */
    public function __construct( Logger $logger, SchemaManager $schema_manager ) {
        $this->logger         = $logger;
        $this->schema_manager = $schema_manager;
    }

    /**
     * Register the cron event if not already scheduled.
     */
    public function register(): void {
        if ( ! wp_next_scheduled( self::EVENT_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::EVENT_HOOK );
        }
    }

    /**
     * Handle cron event callback.
     */
    public function handle_event(): void {
        $this->logger->info( 'Running scheduled schema health check.' );
        $this->schema_manager->maybe_upgrade();
    }

    /**
     * Clear scheduled event.
     */
    public function clear(): void {
        $timestamp = wp_next_scheduled( self::EVENT_HOOK );
        if ( false !== $timestamp ) {
            wp_unschedule_event( $timestamp, self::EVENT_HOOK );
        }
    }
}
