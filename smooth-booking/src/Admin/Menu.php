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
    private const MENU_ICON = 'dashicons-groups';

    /**
     * Menu position.
     */
    private const MENU_POSITION = 56;

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
    public function __construct( EmployeesPage $employees_page, SettingsPage $settings_page ) {
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
            EmployeesPage::CAPABILITY,
            EmployeesPage::MENU_SLUG,
            [ $this->employees_page, 'render_page' ],
            self::MENU_ICON,
            self::MENU_POSITION
        );

        add_submenu_page(
            EmployeesPage::MENU_SLUG,
            __( 'Employees', 'smooth-booking' ),
            __( 'Alkalmazottak', 'smooth-booking' ),
            EmployeesPage::CAPABILITY,
            EmployeesPage::MENU_SLUG,
            [ $this->employees_page, 'render_page' ]
        );

        $this->settings_page->register_submenu( EmployeesPage::MENU_SLUG );
    }
}
