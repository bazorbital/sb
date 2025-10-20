<?php
/**
 * Activation hooks for the plugin.
 *
 * @package SmoothBooking\Infrastructure\Setup
 */

namespace SmoothBooking\Infrastructure\Setup;

use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Plugin;

/**
 * Handles plugin activation logic.
 */
class Activator {
    /**
     * Plugin activation callback.
     */
    public static function activate( bool $network_wide ): void {
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( [ 'fields' => 'ids' ] );
            foreach ( $sites as $site_id ) {
                switch_to_blog( (int) $site_id );
                self::activate_site();
                restore_current_blog();
            }
        } else {
            self::activate_site();
        }
    }

    /**
     * Perform activation for a single site.
     */
    private static function activate_site(): void {
        $plugin        = Plugin::instance();
        $schema        = $plugin->getContainer()->get( SchemaManager::class );
        $logger        = $plugin->getContainer()->get( Logger::class );
        $cron_scheduler = $plugin->getContainer()->get( CleanupScheduler::class );

        $logger->info( 'Activating Smooth Booking plugin.' );
        $schema->ensure_schema();
        $cron_scheduler->register();
    }
}
