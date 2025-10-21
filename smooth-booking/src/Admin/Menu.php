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
     * @var EmployeesPage
     */
    private EmployeesPage $employees_page;

    /**
     * @var SettingsPage
     */
    private SettingsPage $settings_page;

    /**
     * Constructor.
     */
    public function __construct( ServicesPage $services_page, EmployeesPage $employees_page, SettingsPage $settings_page ) {
        $this->services_page  = $services_page;
        $this->employees_page = $employees_page;
        $this->settings_page  = $settings_page;
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
            __( 'Employees', 'smooth-booking' ),
            __( 'Alkalmazottak', 'smooth-booking' ),
            EmployeesPage::CAPABILITY,
            EmployeesPage::MENU_SLUG,
            [ $this->employees_page, 'render_page' ]
        );

        $this->settings_page->register_submenu( ServicesPage::MENU_SLUG );
    }
}
