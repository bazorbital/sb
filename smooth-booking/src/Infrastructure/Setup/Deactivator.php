<?php
/**
 * Deactivation hooks for the plugin.
 *
 * @package SmoothBooking\Infrastructure\Setup
 */

namespace SmoothBooking\Infrastructure\Setup;

use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Plugin;

/**
 * Handles plugin deactivation logic.
 */
class Deactivator {
    /**
     * Plugin deactivation callback.
     */
    public static function deactivate( bool $network_wide = false ): void {
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( [ 'fields' => 'ids' ] );
            foreach ( $sites as $site_id ) {
                switch_to_blog( (int) $site_id );
                self::deactivate_site();
                restore_current_blog();
            }
        } else {
            self::deactivate_site();
        }
    }

    /**
     * Perform deactivation for a single site.
     */
    private static function deactivate_site(): void {
        $plugin         = Plugin::instance();
        $logger         = $plugin->getContainer()->get( Logger::class );
        $cron_scheduler = $plugin->getContainer()->get( CleanupScheduler::class );

        $logger->info( 'Deactivating Smooth Booking plugin.' );
        $cron_scheduler->clear();
    }
}
