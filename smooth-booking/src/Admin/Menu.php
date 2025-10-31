<?php
/**
 * Registers the Smooth Booking admin menu structure.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

/**
 * Responsible for registering menu and submenu pages.
 */
class Menu {
    /**
     * Top-level menu icon.
     */
    private const MENU_ICON = 'dashicons-calendar-alt';

    /**
     * Menu position.
     */
    private const MENU_POSITION = 56;

    /**
     * @var ServicesPage
     */
    private ServicesPage $services_page;

    /**
     * @var LocationsPage
     */
    private LocationsPage $locations_page;

    /**
     * @var AppointmentsPage
     */
    private AppointmentsPage $appointments_page;

    /**
     * @var CalendarPage
     */
    private CalendarPage $calendar_page;

    /**
     * @var EmployeesPage
     */
    private EmployeesPage $employees_page;

    /**
     * @var CustomersPage
     */
    private CustomersPage $customers_page;

    /**
     * @var SettingsPage
     */
    private SettingsPage $settings_page;

    /**
     * @var NotificationsPage
     */
    private NotificationsPage $notifications_page;

    /**
     * Constructor.
     */
    public function __construct( ServicesPage $services_page, LocationsPage $locations_page, AppointmentsPage $appointments_page, CalendarPage $calendar_page, EmployeesPage $employees_page, CustomersPage $customers_page, SettingsPage $settings_page, NotificationsPage $notifications_page ) {
        $this->services_page     = $services_page;
        $this->locations_page    = $locations_page;
        $this->appointments_page = $appointments_page;
        $this->calendar_page     = $calendar_page;
        $this->employees_page    = $employees_page;
        $this->customers_page    = $customers_page;
        $this->settings_page     = $settings_page;
        $this->notifications_page = $notifications_page;
    }

    /**
     * Register menu pages with WordPress.
     */
    public function register(): void {
        add_menu_page(
            __( 'Smooth Booking', 'smooth-booking' ),
            __( 'Smooth Booking', 'smooth-booking' ),
            ServicesPage::CAPABILITY,
            ServicesPage::MENU_SLUG,
            [ $this->services_page, 'render_page' ],
            self::MENU_ICON,
            self::MENU_POSITION
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Services', 'smooth-booking' ),
            __( 'Szolgáltatások', 'smooth-booking' ),
            ServicesPage::CAPABILITY,
            ServicesPage::MENU_SLUG,
            [ $this->services_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Locations', 'smooth-booking' ),
            __( 'Locations', 'smooth-booking' ),
            LocationsPage::CAPABILITY,
            LocationsPage::MENU_SLUG,
            [ $this->locations_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Employees', 'smooth-booking' ),
            __( 'Alkalmazottak', 'smooth-booking' ),
            EmployeesPage::CAPABILITY,
            EmployeesPage::MENU_SLUG,
            [ $this->employees_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Appointments', 'smooth-booking' ),
            __( 'Foglalások', 'smooth-booking' ),
            AppointmentsPage::CAPABILITY,
            AppointmentsPage::MENU_SLUG,
            [ $this->appointments_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Calendar', 'smooth-booking' ),
            __( 'Naptár', 'smooth-booking' ),
            CalendarPage::CAPABILITY,
            CalendarPage::MENU_SLUG,
            [ $this->calendar_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Customers', 'smooth-booking' ),
            __( 'Vásárlók', 'smooth-booking' ),
            CustomersPage::CAPABILITY,
            CustomersPage::MENU_SLUG,
            [ $this->customers_page, 'render_page' ]
        );

        add_submenu_page(
            ServicesPage::MENU_SLUG,
            __( 'Email notifications', 'smooth-booking' ),
            __( 'Email notifications', 'smooth-booking' ),
            NotificationsPage::CAPABILITY,
            NotificationsPage::MENU_SLUG,
            [ $this->notifications_page, 'render_page' ]
        );

        $this->settings_page->register_submenu( ServicesPage::MENU_SLUG );
    }
}
