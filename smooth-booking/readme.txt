=== Smooth Booking ===
Contributors: smoothbooking
Tags: booking, appointments, scheduling
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.18.15
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smooth Booking ensures the booking database environment is ready for custom scheduling workflows and now ships with full employee, customer, service, and appointment management tooling.

== Description ==
Smooth Booking validates and creates required database tables on activation and at runtime to guarantee a healthy environment for booking features. It ships with a schema status dashboard, REST API endpoints, shortcode, Gutenberg block, employee/customer/service/appointment management UIs, and WP-CLI tooling for administrators.
Administrators can also define per-location business hours templates that inform new staff defaults and optional calendar visibility hours.
Administrators can now configure per-location holidays from a yearly calendar, including date ranges, recurring closures, and color-coded statuses that surface in the admin UI and WP-CLI tooling.
Administrators gain a dedicated **Locations** workspace to capture physical or virtual venues with media-powered profile images, address/phone/email/website fields, industry selection, event toggles, company details, and soft delete/restore workflows powering business hours, holidays, REST, and CLI automation.
Administrators can assign employees to one or more locations, toggle service availability with custom price overrides, and configure per-day working hours with optional breaks directly inside the Employees drawer.
Administrators can manage booking-related email notifications, choose recipients, restrict by services, attach ICS files, and design HTML/Text content with placeholder codes. Email delivery preferences are configurable under **Smooth Booking → Settings → Email**, including SMTP credentials, retry periods, and test-sending.
Administrators gain a dedicated **Calendar** view under **Smooth Booking → Calendar** to inspect each location's day-at-a-glance schedule using an EventCalendar-powered day view. The screen defaults to today's bookings, renders employee resources as columns, applies service colours to every appointment, offers lightweight location and date filters so teams can jump between venues without losing context, and exposes **Resources**/**Timeline** view buttons inspired by the `calforsb.html` demo. Employee and service filters are Select2-enhanced with branded placeholders and chips, gracefully falling back to native multi-selects when assets are unavailable.

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
= 0.17.0 =
* Change: Rebuilt the Calendar screen to focus on a resource-based daily view that defaults to today's bookings, displaying employee columns and service-coloured appointments using EventCalendar's timeline layout.
* Change: Simplified calendar filtering to native location and date inputs, loading the day's schedule without Select2 dependencies and aligning colours directly with service palettes.
* Fix: Normalised calendar payload injection to expose locale, slot length, and schedule window details for the new EventCalendar integration.

= 0.16.6 =
* Fix: Prevent schema upgrades from issuing unsupported dbDelta foreign key statements by applying constraints separately after tables exist, eliminating SQL syntax errors on update.
* Fix: Skip foreign key creation on non-InnoDB tables and guard duplicate constraint creation to keep upgrade logs clean and avoid timeouts.

= 0.16.4 =
* Fix: Always load WordPress' bundled SelectWoo assets so the Calendar filters render with the expected Select2 dropdowns regardless of filesystem restrictions, and harden the Apply action to resubmit the "All" sentinel options when nothing is selected.
* Add: General → Debug logging toggle that controls structured diagnostics for calendar aggregation, asset registration, and repositories when troubleshooting booking visibility issues.

= 0.16.3 =
* Ensure the calendar, appointments, and notifications screens register Select2 using WordPress' bundled SelectWoo assets so the multi-select filters render with the expected dropdown UI even when no third-party Select2 library is available.

= 0.16.2 =
* Fix: Remove duplicate service and employee entries from the Calendar filters so only the sentinel “All” options are pre-selected and the Select2 multi-selects render a single set of choices.

= 0.16.1 =
* Fix: Remove the duplicate VanillaCalendar date picker in favour of the native single-day selector, ensure Select2 filters are initialised only once, and keep the EventCalendar grid rendering appointments after applying new filters.

= 0.16.0 =
* Change: Refresh the Calendar filters so the “Employees” terminology matches the rest of the admin UI, include dedicated “All services” and “All employees” Select2 options, and automatically fall back to those defaults when clearing selections, ensuring every employee column renders for the chosen location and date after pressing **Apply**.

= 0.15.1 =
* Fix: Persist calendar schedule data in a dedicated global so the EventCalendar grid continues to render appointments after applying service, location, or staff filters and reloading the page.

= 0.15.0 =
* Replaced the bespoke calendar grid with an EventCalendar-powered timeline that renders employee columns, honours slot lengths, and paints appointments using their service colours directly from the schedule payload.
* Wired appointment actions, edit/delete affordances, and the shared booking modal to the EventCalendar instance so creating or maintaining bookings from the calendar keeps the existing workflows intact.

= 0.14.0 =
* Added service, location, and staff filters to the Calendar workspace with Select2 multi-selects so administrators can focus on specific offerings and providers per location.
* Introduced quick staff toggle buttons that mirror the location roster, including an “All staff” shortcut, while keeping selections in sync with the filter form.
* Enhanced appointment cards with consistent filtering logic so only the selected staff/service combinations render, and surfaced guidance when no staff are selected.

= 0.13.1 =
* Fix: Reinitialised the notification drawer editor on open so TinyMCE no longer triggers asynchronous listener errors when adding a new email notification. Editor scripts are now enqueued explicitly and refreshed whenever the drawer becomes visible.

= 0.13.0 =
* Added a Calendar workspace that renders location-specific employee columns, configurable time slots, colour-coded appointments, and inline creation/edit/delete actions surfaced through the shared appointment modal.
* Introduced General settings for time-slot length that drive both the appointment forms and calendar grid, alongside repository support for retrieving employee appointments within a daily range.
* Extended appointment entities with service colour metadata, delivered dedicated calendar JavaScript/CSS assets, and shipped PHPUnit coverage for the new settings sanitisation and calendar aggregation logic.

= 0.12.0 =
* Revamped the Employees drawer with tabbed General, Location, Services, and Schedule sections so administrators can manage locations, visibility, service availability, and working hours from a single panel.
* Added per-employee service price overrides with validation to prevent negative or non-numeric entries and persisted overrides in the repository layer.
* Introduced a weekly schedule editor that supports copying hours, applying location templates, and defining multiple breaks per day with client-side controls and server-side validation.
* Updated admin JavaScript and CSS to power the new tab navigation, service toggles, and schedule interactions while localizing schedule data seeded from location business hours.
* Expanded PHPUnit coverage with tests for service pricing validation and schedule sanitisation in the employee domain service.

= 0.11.0 =
* Added Email Notifications admin screen with drawer-based creation/editing, recipient targeting (client, employee, administrators, custom), per-status filters, service scoping, Select2 multi-selects, placeholder tables, and soft delete/disable workflows.
* Introduced notification domain models, repository, and schema tables for channels, recipients, templates, rules, send jobs, attempts, suppression list, and delivery events alongside PHPUnit coverage.
* Added Email settings tab with sender identity, send format, reply-to behaviour, retry windows, SMTP credentials with dynamic field visibility, and nonce-protected submissions plus test email action.
* Extended Locations with configurable time zones (defaulting to Europe/Budapest) and ensured CRUD flows persist the timezone value.

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
= 0.12.0 =
Adds location assignment, per-service price overrides, and a weekly schedule editor with breaks to the Employees workspace. Update to manage staff availability and pricing without leaving the employee drawer.

= 0.11.0 =
Adds configurable email notifications with per-recipient targeting, placeholder guidance, and SMTP-aware email settings plus location timezone support. Update to enable automated communication flows from Smooth Booking.

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
