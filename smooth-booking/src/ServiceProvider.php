<?php
/**
 * Register services for the plugin.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Admin\SettingsPage;
use SmoothBooking\Cli\Commands\SchemaCommand;
use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Domain\SchemaStatusService;
use SmoothBooking\Frontend\Blocks\SchemaStatusBlock;
use SmoothBooking\Frontend\Shortcodes\SchemaStatusShortcode;
use SmoothBooking\Infrastructure\Database\SchemaDefinitionBuilder;
use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Rest\SchemaStatusController;
use SmoothBooking\Support\ServiceContainer;

/**
 * Service provider for dependency registration.
 */
class ServiceProvider {
    /**
     * Register plugin services in the container.
     */
    public function register( ServiceContainer $container ): void {
        $container->singleton( Logger::class, static function ( ServiceContainer $_container ): Logger {
            return new Logger( 'smooth-booking' );
        } );

        $container->singleton( SchemaDefinitionBuilder::class, static function ( ServiceContainer $_container ): SchemaDefinitionBuilder {
            return new SchemaDefinitionBuilder();
        } );

        $container->singleton( SchemaManager::class, static function ( ServiceContainer $container ): SchemaManager {
            global $wpdb;

            return new SchemaManager(
                $wpdb,
                $container->get( SchemaDefinitionBuilder::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( SchemaStatusService::class, static function ( ServiceContainer $container ): SchemaStatusService {
            return new SchemaStatusService(
                $container->get( SchemaManager::class )
            );
        } );

        $container->singleton( SettingsPage::class, static function ( ServiceContainer $container ): SettingsPage {
            return new SettingsPage( $container->get( SchemaStatusService::class ) );
        } );

        $container->singleton( SchemaStatusShortcode::class, static function ( ServiceContainer $container ): SchemaStatusShortcode {
            return new SchemaStatusShortcode( $container->get( SchemaStatusService::class ) );
        } );

        $container->singleton( SchemaStatusBlock::class, static function ( ServiceContainer $container ): SchemaStatusBlock {
            return new SchemaStatusBlock( $container->get( SchemaStatusService::class ) );
        } );

        $container->singleton( SchemaStatusController::class, static function ( ServiceContainer $container ): SchemaStatusController {
            return new SchemaStatusController( $container->get( SchemaStatusService::class ) );
        } );

        $container->singleton( CleanupScheduler::class, static function ( ServiceContainer $container ): CleanupScheduler {
            return new CleanupScheduler( $container->get( Logger::class ), $container->get( SchemaManager::class ) );
        } );

        $container->singleton( SchemaCommand::class, static function ( ServiceContainer $container ): SchemaCommand {
            return new SchemaCommand( $container->get( SchemaManager::class ), $container->get( Logger::class ) );
        } );
    }
}
