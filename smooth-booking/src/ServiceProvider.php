<?php
/**
 * Register services for the plugin.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Admin\AppointmentsPage;
use SmoothBooking\Admin\CustomersPage;
use SmoothBooking\Admin\CalendarPage;
use SmoothBooking\Admin\EmployeesPage;
use SmoothBooking\Admin\Menu;
use SmoothBooking\Admin\NotificationsPage;
use SmoothBooking\Admin\LocationsPage;
use SmoothBooking\Admin\SettingsPage;
use SmoothBooking\Admin\ServicesPage;
use SmoothBooking\Cli\Commands\AppointmentsCommand;
use SmoothBooking\Cli\Commands\CustomersCommand;
use SmoothBooking\Cli\Commands\EmployeesCommand;
use SmoothBooking\Cli\Commands\HolidaysCommand;
use SmoothBooking\Cli\Commands\SchemaCommand;
use SmoothBooking\Cli\Commands\ServicesCommand;
use SmoothBooking\Cli\Commands\LocationsCommand;
use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Domain\Appointments\AppointmentRepositoryInterface;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\BusinessHours\BusinessHoursRepositoryInterface;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Customers\CustomerRepositoryInterface;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Customers\CustomerTagRepositoryInterface;
use SmoothBooking\Domain\Holidays\HolidayRepositoryInterface;
use SmoothBooking\Domain\Holidays\HolidayService;
use SmoothBooking\Domain\Employees\EmployeeCategoryRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Notifications\EmailNotificationRepositoryInterface;
use SmoothBooking\Domain\Notifications\EmailNotificationService;
use SmoothBooking\Domain\Notifications\EmailSettingsService;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\SchemaStatusService;
use SmoothBooking\Domain\Services\ServiceCategoryRepositoryInterface;
use SmoothBooking\Domain\Services\ServiceRepositoryInterface;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Domain\Services\ServiceTagRepositoryInterface;
use SmoothBooking\Frontend\Blocks\SchemaStatusBlock;
use SmoothBooking\Frontend\Shortcodes\SchemaStatusShortcode;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use SmoothBooking\Infrastructure\Database\SchemaDefinitionBuilder;
use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Assets\Select2AssetRegistrar;
use SmoothBooking\Infrastructure\Repository\AppointmentRepository;
use SmoothBooking\Infrastructure\Repository\BusinessHoursRepository;
use SmoothBooking\Infrastructure\Repository\CustomerRepository;
use SmoothBooking\Infrastructure\Repository\CustomerTagRepository;
use SmoothBooking\Infrastructure\Repository\HolidayRepository;
use SmoothBooking\Infrastructure\Repository\EmailNotificationRepository;
use SmoothBooking\Infrastructure\Repository\EmployeeCategoryRepository;
use SmoothBooking\Infrastructure\Repository\EmployeeRepository;
use SmoothBooking\Infrastructure\Repository\LocationRepository;
use SmoothBooking\Infrastructure\Repository\ServiceCategoryRepository;
use SmoothBooking\Infrastructure\Repository\ServiceRepository;
use SmoothBooking\Infrastructure\Repository\ServiceTagRepository;
use SmoothBooking\Rest\AppointmentsController;
use SmoothBooking\Rest\CustomersController;
use SmoothBooking\Rest\EmployeesController;
use SmoothBooking\Rest\SchemaStatusController;
use SmoothBooking\Rest\ServicesController;
use SmoothBooking\Rest\LocationsController;
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

        $container->singleton( Select2AssetRegistrar::class, static function ( ServiceContainer $container ): Select2AssetRegistrar {
            return new Select2AssetRegistrar(
                $container->get( Logger::class )
            );
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

        $container->singleton( GeneralSettings::class, static function (): GeneralSettings {
            return new GeneralSettings();
        } );

        $container->singleton( EmployeeCategoryRepositoryInterface::class, static function ( ServiceContainer $container ): EmployeeCategoryRepositoryInterface {
            global $wpdb;

            return new EmployeeCategoryRepository(
                $wpdb,
                $container->get( Logger::class )
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
                $container->get( EmployeeCategoryRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( AppointmentRepositoryInterface::class, static function ( ServiceContainer $container ): AppointmentRepositoryInterface {
            global $wpdb;

            return new AppointmentRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( CustomerTagRepositoryInterface::class, static function ( ServiceContainer $container ): CustomerTagRepositoryInterface {
            global $wpdb;

            return new CustomerTagRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( CustomerRepositoryInterface::class, static function ( ServiceContainer $container ): CustomerRepositoryInterface {
            global $wpdb;

            return new CustomerRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( CustomerService::class, static function ( ServiceContainer $container ): CustomerService {
            return new CustomerService(
                $container->get( CustomerRepositoryInterface::class ),
                $container->get( CustomerTagRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( LocationRepositoryInterface::class, static function ( ServiceContainer $container ): LocationRepositoryInterface {
            global $wpdb;

            return new LocationRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( LocationService::class, static function ( ServiceContainer $container ): LocationService {
            return new LocationService(
                $container->get( LocationRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( BusinessHoursRepositoryInterface::class, static function ( ServiceContainer $container ): BusinessHoursRepositoryInterface {
            global $wpdb;

            return new BusinessHoursRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( BusinessHoursService::class, static function ( ServiceContainer $container ): BusinessHoursService {
            return new BusinessHoursService(
                $container->get( BusinessHoursRepositoryInterface::class ),
                $container->get( LocationRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( HolidayRepositoryInterface::class, static function ( ServiceContainer $container ): HolidayRepositoryInterface {
            global $wpdb;

            return new HolidayRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( HolidayService::class, static function ( ServiceContainer $container ): HolidayService {
            return new HolidayService(
                $container->get( HolidayRepositoryInterface::class ),
                $container->get( LocationRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( AppointmentService::class, static function ( ServiceContainer $container ): AppointmentService {
            return new AppointmentService(
                $container->get( AppointmentRepositoryInterface::class ),
                $container->get( EmployeeService::class ),
                $container->get( ServiceService::class ),
                $container->get( CustomerService::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( ServiceCategoryRepositoryInterface::class, static function ( ServiceContainer $container ): ServiceCategoryRepositoryInterface {
            global $wpdb;

            return new ServiceCategoryRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( ServiceTagRepositoryInterface::class, static function ( ServiceContainer $container ): ServiceTagRepositoryInterface {
            global $wpdb;

            return new ServiceTagRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( ServiceRepositoryInterface::class, static function ( ServiceContainer $container ): ServiceRepositoryInterface {
            global $wpdb;

            return new ServiceRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( ServiceService::class, static function ( ServiceContainer $container ): ServiceService {
            return new ServiceService(
                $container->get( ServiceRepositoryInterface::class ),
                $container->get( ServiceCategoryRepositoryInterface::class ),
                $container->get( ServiceTagRepositoryInterface::class ),
                $container->get( EmployeeRepositoryInterface::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( EmailNotificationRepositoryInterface::class, static function ( ServiceContainer $container ): EmailNotificationRepositoryInterface {
            global $wpdb;

            return new EmailNotificationRepository(
                $wpdb,
                $container->get( Logger::class )
            );
        } );

        $container->singleton( EmailNotificationService::class, static function ( ServiceContainer $container ): EmailNotificationService {
            return new EmailNotificationService(
                $container->get( EmailNotificationRepositoryInterface::class ),
                $container->get( ServiceService::class ),
                $container->get( Logger::class )
            );
        } );

        $container->singleton( EmailSettingsService::class, static function ( ServiceContainer $container ): EmailSettingsService {
            return new EmailSettingsService( $container->get( Logger::class ) );
        } );

        $container->singleton( SettingsPage::class, static function ( ServiceContainer $container ): SettingsPage {
            return new SettingsPage(
                $container->get( SchemaStatusService::class ),
                $container->get( BusinessHoursService::class ),
                $container->get( HolidayService::class ),
                $container->get( EmailSettingsService::class ),
                $container->get( GeneralSettings::class )
            );
        } );

        $container->singleton( LocationsPage::class, static function ( ServiceContainer $container ): LocationsPage {
            return new LocationsPage( $container->get( LocationService::class ) );
        } );

        $container->singleton( ServicesPage::class, static function ( ServiceContainer $container ): ServicesPage {
            return new ServicesPage(
                $container->get( ServiceService::class ),
                $container->get( EmployeeService::class )
            );
        } );

        $container->singleton( AppointmentsPage::class, static function ( ServiceContainer $container ): AppointmentsPage {
            return new AppointmentsPage(
                $container->get( AppointmentService::class ),
                $container->get( EmployeeService::class ),
                $container->get( ServiceService::class ),
                $container->get( CustomerService::class ),
                $container->get( GeneralSettings::class )
            );
        } );

        $container->singleton( CalendarService::class, static function ( ServiceContainer $container ): CalendarService {
            return new CalendarService(
                $container->get( AppointmentService::class ),
                $container->get( EmployeeService::class ),
                $container->get( BusinessHoursService::class ),
                $container->get( LocationService::class ),
                $container->get( GeneralSettings::class )
            );
        } );

        $container->singleton( CalendarPage::class, static function ( ServiceContainer $container ): CalendarPage {
            return new CalendarPage(
                $container->get( CalendarService::class ),
                $container->get( LocationService::class ),
                $container->get( EmployeeService::class ),
                $container->get( ServiceService::class ),
                $container->get( CustomerService::class ),
                $container->get( GeneralSettings::class )
            );
        } );

        $container->singleton( EmployeesPage::class, static function ( ServiceContainer $container ): EmployeesPage {
            return new EmployeesPage(
                $container->get( EmployeeService::class ),
                $container->get( LocationService::class ),
                $container->get( ServiceService::class ),
                $container->get( BusinessHoursService::class )
            );
        } );

        $container->singleton( CustomersPage::class, static function ( ServiceContainer $container ): CustomersPage {
            return new CustomersPage( $container->get( CustomerService::class ) );
        } );

        $container->singleton( Menu::class, static function ( ServiceContainer $container ): Menu {
            return new Menu(
                $container->get( ServicesPage::class ),
                $container->get( LocationsPage::class ),
                $container->get( AppointmentsPage::class ),
                $container->get( CalendarPage::class ),
                $container->get( EmployeesPage::class ),
                $container->get( CustomersPage::class ),
                $container->get( SettingsPage::class ),
                $container->get( NotificationsPage::class )
            );
        } );

        $container->singleton( NotificationsPage::class, static function ( ServiceContainer $container ): NotificationsPage {
            return new NotificationsPage( $container->get( EmailNotificationService::class ) );
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

        $container->singleton( CustomersController::class, static function ( ServiceContainer $container ): CustomersController {
            return new CustomersController( $container->get( CustomerService::class ) );
        } );

        $container->singleton( ServicesController::class, static function ( ServiceContainer $container ): ServicesController {
            return new ServicesController( $container->get( ServiceService::class ) );
        } );

        $container->singleton( LocationsController::class, static function ( ServiceContainer $container ): LocationsController {
            return new LocationsController( $container->get( LocationService::class ) );
        } );

        $container->singleton( CleanupScheduler::class, static function ( ServiceContainer $container ): CleanupScheduler {
            return new CleanupScheduler( $container->get( Logger::class ), $container->get( SchemaManager::class ) );
        } );

        $container->singleton( AppointmentsController::class, static function ( ServiceContainer $container ): AppointmentsController {
            return new AppointmentsController( $container->get( AppointmentService::class ) );
        } );

        $container->singleton( SchemaCommand::class, static function ( ServiceContainer $container ): SchemaCommand {
            return new SchemaCommand( $container->get( SchemaManager::class ), $container->get( Logger::class ) );
        } );

        $container->singleton( EmployeesCommand::class, static function ( ServiceContainer $container ): EmployeesCommand {
            return new EmployeesCommand( $container->get( EmployeeService::class ) );
        } );

        $container->singleton( CustomersCommand::class, static function ( ServiceContainer $container ): CustomersCommand {
            return new CustomersCommand( $container->get( CustomerService::class ) );
        } );

        $container->singleton( ServicesCommand::class, static function ( ServiceContainer $container ): ServicesCommand {
            return new ServicesCommand( $container->get( ServiceService::class ) );
        } );

        $container->singleton( LocationsCommand::class, static function ( ServiceContainer $container ): LocationsCommand {
            return new LocationsCommand( $container->get( LocationService::class ) );
        } );

        $container->singleton( AppointmentsCommand::class, static function ( ServiceContainer $container ): AppointmentsCommand {
            return new AppointmentsCommand( $container->get( AppointmentService::class ) );
        } );
    }
}
