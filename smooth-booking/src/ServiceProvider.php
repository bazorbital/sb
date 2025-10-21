<?php
/**
 * Register services for the plugin.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Admin\EmployeesPage;
use SmoothBooking\Admin\Menu;
use SmoothBooking\Admin\SettingsPage;
use SmoothBooking\Cli\Commands\EmployeesCommand;
use SmoothBooking\Cli\Commands\SchemaCommand;
use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\SchemaStatusService;
use SmoothBooking\Frontend\Blocks\SchemaStatusBlock;
use SmoothBooking\Frontend\Shortcodes\SchemaStatusShortcode;
use SmoothBooking\Infrastructure\Database\SchemaDefinitionBuilder;
use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Repository\EmployeeRepository;
use SmoothBooking\Rest\EmployeesController;
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

        $container->singleton( EmployeeRepositoryInterface::class, static function ( ServiceContainer $container ): EmployeeRepositoryInterface {
            global $wpdb;

            return new EmployeeRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( EmployeeService::class, static function ( ServiceContainer $container ): EmployeeService {
            return new EmployeeService(
                $container->get( EmployeeRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( SettingsPage::class, static function ( ServiceContainer $container ): SettingsPage {
            return new SettingsPage( $container->get( SchemaStatusService::class ) );
        } );

        $container->singleton( EmployeesPage::class, static function ( ServiceContainer $container ): EmployeesPage {
            return new EmployeesPage( $container->get( EmployeeService::class ) );
        } );

        $container->singleton( Menu::class, static function ( ServiceContainer $container ): Menu {
            return new Menu(
                $container->get( EmployeesPage::class ),
                $container->get( SettingsPage::class )
            );
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

        $container->singleton( EmployeesController::class, static function ( ServiceContainer $container ): EmployeesController {
            return new EmployeesController( $container->get( EmployeeService::class ) );
        } );

        $container->singleton( CleanupScheduler::class, static function ( ServiceContainer $container ): CleanupScheduler {
            return new CleanupScheduler( $container->get( Logger::class ), $container->get( SchemaManager::class ) );
        } );

        $container->singleton( SchemaCommand::class, static function ( ServiceContainer $container ): SchemaCommand {
            return new SchemaCommand( $container->get( SchemaManager::class ), $container->get( Logger::class ) );
        } );

        $container->singleton( EmployeesCommand::class, static function ( ServiceContainer $container ): EmployeesCommand {
            return new EmployeesCommand( $container->get( EmployeeService::class ) );
        } );
    }
}
