=== Smooth Booking ===
Contributors: smoothbooking
Tags: booking, appointments, scheduling
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smooth Booking ensures the booking database environment is ready for custom scheduling workflows and now ships with an employee directory for staffing management.

== Description ==
Smooth Booking validates and creates required database tables on activation and at runtime to guarantee a healthy environment for booking features. It ships with a schema status dashboard, REST API endpoints, shortcode, Gutenberg block, employee management UI, and WP-CLI tooling for administrators.

== Installation ==
1. Upload the `smooth-booking` folder to the `/wp-content/plugins/` directory or install via Composer.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Smooth Booking → Alkalmazottak** to create staff profiles and **Smooth Booking → Beállítások** to review schema health and configure automatic repairs.

== Frequently Asked Questions ==
= Does the plugin support multisite? =
Yes. Activation, deactivation, and uninstall operations run across all sites when network activated.

= How can I verify the schema from the command line? =
Use WP-CLI:

`wp smooth schema status`

= Can I manage employees programmatically? =
Yes. Use the REST API at `/wp-json/smooth-booking/v1/employees` or the CLI commands `wp smooth employees <list|create|update|delete>`.

== Screenshots ==
1. Settings page summarizing schema health.
2. Employee administration table with quick actions.

== Changelog ==
= 0.2.0 =
* Added Smooth Booking top-level admin menu with an **Alkalmazottak** management screen.
* Implemented employee CRUD with validation, soft delete, admin notices, and dropdown action menus.
* Registered REST API routes at `/wp-json/smooth-booking/v1/employees` for integrations.
* Added WP-CLI commands for listing, creating, updating, and deleting employees.
* Bundled dedicated admin CSS/JS assets for the employee grid and flash notices.

= 0.1.0 =
* Initial release. Creates booking schema on activation and runtime. Provides Settings API integration, REST endpoint, shortcode, Gutenberg block, cron maintenance, and WP-CLI commands for schema management.

== Upgrade Notice ==
= 0.2.0 =
Adds employee CRUD, REST API routes, and CLI tooling. Update to manage staff members directly from the admin area.

= 0.1.0 =
First release.
