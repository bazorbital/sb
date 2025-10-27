=== Smooth Booking ===
Contributors: smoothbooking
Tags: booking, appointments, scheduling
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smooth Booking ensures the booking database environment is ready for custom scheduling workflows and now ships with full employee, customer, service, and appointment management tooling.

== Description ==
Smooth Booking validates and creates required database tables on activation and at runtime to guarantee a healthy environment for booking features. It ships with a schema status dashboard, REST API endpoints, shortcode, Gutenberg block, employee/customer/service/appointment management UIs, and WP-CLI tooling for administrators.
Administrators can also define per-location business hours templates that inform new staff defaults and optional calendar visibility hours.
Administrators can now configure per-location holidays from a yearly calendar, including date ranges, recurring closures, and color-coded statuses that surface in the admin UI and WP-CLI tooling.
Administrators gain a dedicated **Locations** workspace to capture physical or virtual venues with media-powered profile images, address/phone/email/website fields, industry selection, event toggles, company details, and soft delete/restore workflows powering business hours, holidays, REST, and CLI automation.

== Installation ==
1. Upload the `smooth-booking` folder to the `/wp-content/plugins/` directory or install via Composer.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Smooth Booking → Locations** to register venues, **Smooth Booking → Appointments** to manage bookings, **Smooth Booking → Services** to configure offerings, **Smooth Booking → Employees** to create staff profiles, **Smooth Booking → Customers** to manage client records, and **Smooth Booking → Settings** to review schema health and configure automatic repairs.

== Frequently Asked Questions ==
= Does the plugin support multisite? =
Yes. Activation, deactivation, and uninstall operations run across all sites when network activated.

= How can I verify the schema from the command line? =
Use WP-CLI:

`wp smooth schema status`

= Can I manage locations, employees, customers, services, and appointments programmatically? =
Yes. Use the REST API at `/wp-json/smooth-booking/v1/locations`, `/wp-json/smooth-booking/v1/employees`, `/wp-json/smooth-booking/v1/customers`, `/wp-json/smooth-booking/v1/services`, and `/wp-json/smooth-booking/v1/appointments` or the CLI commands `wp smooth locations <list|create|update|delete|restore>`, `wp smooth employees <list|create|update|delete|restore>`, `wp smooth customers <list|create|update|delete|restore>`, `wp smooth services <list|create|update|delete|restore>`, and `wp smooth appointments <list|delete|restore>`.

== Screenshots ==
1. Settings page summarizing schema health.
2. Employee administration table with quick actions.

== Changelog ==
= 0.10.0 =
* Added Locations administration screen with media-powered profile images, address/phone/email/website capture, industry dropdowns, event toggle, and soft delete/restore workflows.
* Introduced `LocationService`, REST controller, WP-CLI command suite `wp smooth locations <list|create|update|delete|restore>`, and admin notices with capability/nonce enforcement.
* Expanded database schema for `smooth_locations` to store contact channels, company metadata, and profile image IDs, and wired repositories with object cache support.

= 0.9.0 =
* Added Holidays settings tab with a yearly calendar per location, range selection, recurring closures, and color-coded styling.
* Introduced location holiday service, repository, schema table, caching hooks, and WP-CLI command suite `wp smooth holidays <list|add|delete>`.
* Updated settings navigation and enqueue logic to remember the active section and share responsive styling between business hours and holidays.

= 0.8.0 =
* Added a Business Hours configuration section under Settings allowing administrators to select a location and define opening/closing times for each day of the week with 15-minute intervals.
* Persisted location-specific business hours templates using nonces, capability checks, and WordPress Settings styling so calendar visibility and new staff defaults stay aligned.

= 0.7.0 =
* Added Appointments administration screen with searchable filters, soft delete/restore, Select2-powered provider/service/customer dropdowns, schedule selectors, and notification toggles matching the existing admin design system.
* Introduced appointment domain service and repository backed by schema upgrades storing payment status, internal notes, notification flags, and contact overrides.
* Registered REST API routes at `/wp-json/smooth-booking/v1/appointments` with list, create, update, delete, and restore operations plus pagination filters.
* Added WP-CLI commands `wp smooth appointments <list|delete|restore>` and PHPUnit coverage for appointment entity parsing and validation.

= 0.6.0 =
* Added Customers administration screen with searchable, sortable, and paginated listings, full CRUD forms, WordPress user linking, profile imagery, tagging, soft delete/restore, and admin notices.
* Introduced customer domain layer with repositories, tag entities, sanitising service, and new database tables for customer records and tag relations.
* Registered REST API routes at `/wp-json/smooth-booking/v1/customers` with list, create, update, delete, and restore operations plus pagination parameters.
* Added WP-CLI commands `wp smooth customers <list|create|update|delete|restore>` to automate customer maintenance alongside new PHPUnit coverage.

= 0.5.0 =
* Refreshed the Services and Employees admin experiences with modern headers, floating "Add new" actions, and collapsible form drawers that open on demand.
* Added contextual cancel/back controls and improved focus management so creating and editing items aligns with 2025 WordPress design expectations.

= 0.4.0 =
* Added full Services administration screen with General/Time/Additional tabs, provider preference logic, media integration, booking limits, and soft delete/restore workflows.
* Introduced service domain layer with repositories for services, categories, tags, and provider relationships plus automated schema updates.
* Added REST API routes at `/wp-json/smooth-booking/v1/services` with CRUD and restore operations.
* Added WP-CLI commands `wp smooth services <list|create|update|delete|restore>` for headless service management.
* Bundled dedicated admin CSS/JS for the Services form including color pickers, media selection, tab navigation, and dynamic provider controls.

= 0.3.0 =
* Added employee profile images with media library integration and remove/reset controls.
* Introduced visibility states (public, private, archived) and automatic archiving on delete with restore support.
* Added color picker preferences and free-form employee categories stored in dedicated tables.
* Updated admin UI with deleted-employee view toggle, category selectors, and accessibility improvements.
* Extended REST API and WP-CLI commands to accept new profile, visibility, and category parameters.

= 0.2.0 =
* Added Smooth Booking top-level admin menu with an Employees management screen.
* Implemented employee CRUD with validation, soft delete, admin notices, and dropdown action menus.
* Registered REST API routes at `/wp-json/smooth-booking/v1/employees` for integrations.
* Added WP-CLI commands for listing, creating, updating, and deleting employees.
* Bundled dedicated admin CSS/JS assets for the employee grid and flash notices.

= 0.1.0 =
* Initial release. Creates booking schema on activation and runtime. Provides Settings API integration, REST endpoint, shortcode, Gutenberg block, cron maintenance, and WP-CLI commands for schema management.

== Upgrade Notice ==
= 0.10.0 =
Adds a full Locations management workspace with contact metadata, industry selection, REST/CLI automation, and schema upgrades supporting business hours and holidays.

= 0.8.0 =
Adds a Business Hours settings panel with per-location templates powering staff defaults and calendar visibility. Update to manage weekly schedules centrally.

= 0.7.0 =
Introduces a full Appointments hub with CRUD forms, filters, REST/CLI automation, and upgraded schema fields for payments and notifications. Update to manage bookings alongside services, staff, and customers.

= 0.6.0 =
Introduces a full Customers directory with tagging, WordPress user linkage, REST/CLI automation, and upgraded schema. Update to manage clients alongside services and staff.

= 0.5.0 =
Polished the Services and Employees admin interfaces with 2025-ready layouts, "Add new" toggles, and streamlined editing flows. Update to unlock the refreshed management experience.

= 0.4.0 =
Introduces the Services management screen with advanced scheduling preferences, REST/CLI tooling, and supporting schema updates. Update to configure offerings alongside employees.

= 0.3.0 =
Adds profile images, visibility controls, categories, and restore workflows for employees. Update to access the enhanced staff directory.

= 0.2.0 =
Adds employee CRUD, REST API routes, and CLI tooling. Update to manage staff members directly from the admin area.

= 0.1.0 =
First release.
