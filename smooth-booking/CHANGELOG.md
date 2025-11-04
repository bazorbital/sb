# Changelog

All notable changes to Smooth Booking will be documented here.

# [0.16.5] - 2025-11-05
### Fixed
- Added a canonical notification template lookup to prevent duplicate email rules and aligned database schema updates with the new index.
- Normalised notification recipient keys between the admin form and storage, enforcing the correct combinations for customer, staff, administrator, and custom recipients.
- Surfaced a validation error when attempting to create duplicate notification rules instead of triggering a database exception.

# [0.16.4] - 2025-07-05
### Added
- Debug logging switch under **Settings → General** that controls whether the plugin emits structured diagnostics for calendar aggregation, SelectWoo registration, and booking repositories, plus expanded log coverage for daily schedule hydration.

### Fixed
- Always register the core SelectWoo script and stylesheet handles (regardless of filesystem checks) so Select2-powered filters render consistently, and ensure the Calendar Apply action rehydrates the sentinel "All" filter selections when nothing is chosen.

# [0.16.3] - 2025-06-20
### Fixed
- Registered Select2 assets against WordPress' bundled SelectWoo library and added a guarded alias so calendar, appointment, and notification filters consistently render as Select2 dropdowns even when no external Select2 script is present.

# [0.16.2] - 2025-06-15
### Fixed
- Deduplicated the Calendar service and employee filters so only the sentinel “All” options render as selected by default and Select2 keeps a single set of chips without repeating entries when locations share overlapping assignments.

# [0.16.1] - 2025-06-10
### Fixed
- Removed the VanillaCalendar date picker and duplicate markup so the Calendar screen relies on the browser’s native single-day selector without injecting extra DOM nodes that prevented the EventCalendar grid from rendering after filter changes.
- Guarded Select2 initialisation against duplicate execution to keep the Services and Employees filters from spawning multiple dropdown containers when the screen reloads or scripts run again.

# [0.16.0] - 2025-06-07
### Changed
- Renamed the Calendar filter to “Employees”, added explicit “All services” and “All employees” Select2 options, and ensured clearing selections defaults back to showing every employee column for the chosen location and day before rendering the EventCalendar grid.
- Normalised Select2 handling so location, service, and employee filters share consistent dropdown parents and automatic “all” fallbacks, restoring the employee columns after pressing **Apply** even when only the sentinel options are submitted.

# [0.15.1] - 2025-06-02
### Fixed
- Ensure the admin calendar stores schedule payloads in a dedicated global so the EventCalendar grid continues to render appointments after applying location, service, or staff filters and reloading the page.
- Reapply the hydrated schedule to the localized settings object after scripts load, keeping downstream integrations compatible.

# [0.15.0] - 2025-05-31
### Changed
- Replaced the bespoke admin calendar grid with an EventCalendar-powered timeline that renders employee columns, slot grids, and colour-coded appointments sourced from the schedule payload.
- Localized appointment metadata, edit/delete affordances, and the booking modal launcher into the EventCalendar instance for a cohesive scheduling workflow.

# [0.14.0] - 2025-05-20
### Changed
- Calendar admin workspace now offers service and staff filters with Select2 multi-selects, preserving selections across reloads and limiting the appointment grid to the chosen criteria.
- Added chip-style quick buttons representing each location staff member plus an "All staff" shortcut that stay in sync with the employee filter.
- Updated calendar rendering to hide appointments outside the selected service/provider set and surface guidance when no staff are selected, while keeping the appointment modal fed with the full employee roster.

# [0.13.1] - 2025-05-15
### Fixed
- Ensure the email notification drawer re-prepares the TinyMCE editor when opened so the "Add new notification" action no longer triggers asynchronous listener errors. Editor assets are now enqueued explicitly and the instance is refreshed after the drawer becomes visible.

# [0.13.0] - 2025-05-01
### Added
- Calendar admin workspace presenting per-location employee columns, configurable slot length, colour-coded appointments, and inline booking modal with edit/delete affordances.
- General settings for time slot length powering appointment forms and calendar calculations, plus repository support for fetching employee appointments within a day range and appointment entities now exposing service colours.
- Dedicated calendar JavaScript/CSS assets and PHPUnit coverage for general settings sanitisation alongside calendar aggregation logic.

# [0.12.0] - 2025-04-01
### Added
- Employee administration drawer now features tabbed General, Location, Services, and Schedule sections with location assignment, visibility controls, and working hours seeded from location business hours.
- Per-provider service price overrides with validation and repository persistence, alongside UI affordances for enabling or disabling services per employee.
- Weekly schedule editor supporting copy-to-all, applying location templates, and managing multiple breaks per day, backed by new JavaScript and CSS assets.
- PHPUnit coverage for employee service pricing validation and schedule sanitisation rules.

# [0.11.0] - 2025-03-01
### Added
- Email notifications admin workspace with drawer-based creation/editing, recipient scoping (client, employee, administrators, custom), Select2-powered service filters, placeholder reference table, ICS attachment toggle, and soft delete/disable actions with notices.
- Notification domain layer (entity, service, repository interface) plus wpdb-backed repository and schema tables covering channels, recipients, templates, rules, send jobs, attempts, suppression list, and delivery events.
- Email settings tab with sender identity, HTML/Text format toggle, reply-to behaviour, retry period dropdown, SMTP gateway credentials with conditional visibility, and test email submission backed by `EmailSettingsService` hooks.
- Location timezone field defaulting to `Europe/Budapest`, dropdown selection in the admin UI, validation in the domain service, repository persistence, and PHPUnit coverage for timezone handling.

# [0.10.0] - 2025-02-01
### Added
- Locations administration screen with media-powered profile images, address/phone/email/website capture, industry optgroups, event toggle, company metadata, admin notices, and soft delete/restore workflows.
- Location domain service, REST controller at `/wp-json/smooth-booking/v1/locations`, WP-CLI command suite `wp smooth locations <list|create|update|delete|restore>`, PHPUnit coverage, and localized admin JavaScript for media selection.
- Expanded `smooth_locations` schema with profile image, contact channels, and company columns plus repository caching and validation hooks powering business hours and holidays.

### Changed
- Updated top-level admin menu, README, and readme.txt to surface the Locations workspace and new automation touchpoints.

# [0.9.0] - 2025-01-15
### Added
- Holidays settings tab with a yearly calendar per location, range selection, and support for recurring closures styled via Smooth Booking design tokens.
- Location holiday domain service, repository, schema table, and caching hooks with REST-safe validation and public actions for integrators.
- WP-CLI command suite `wp smooth holidays <list|add|delete>` for headless maintenance plus admin JavaScript and CSS powering the calendar experience.

### Changed
- Settings navigation now remembers the active section when saving business hours or holidays and shares responsive styling between panels.
- Holidays calendar buttons respond to keyboard activation and display a visible focus outline for improved accessibility.

# [0.8.0] - 2024-12-01
### Added
- Business Hours settings panel with per-location dropdowns for opening and closing times, success/error notices, and nonce-protected submissions that persist templates to `smooth_opening_hours`.
- Location and business hours domain layers with repositories, validation, and PHPUnit coverage ensuring close times cannot precede open times.

# [0.7.0] - 2024-11-01
### Added
- Appointments administration screen with filterable list, soft-delete workflow, and form drawer for creating bookings, including provider, service, customer selection, schedule period, and notification controls.
- Appointment domain layer with repository, validation service, and updated database schema storing payment status, internal notes, notification flags, and contact overrides.
- REST API endpoints at `/wp-json/smooth-booking/v1/appointments` supporting list, create, update, delete, and restore operations aligned with admin validations.
- WP-CLI command suite `wp smooth appointments <list|delete|restore>` for headless management of bookings.
- Dedicated admin CSS/JS assets powering search-enabled selects, action menus, and dynamic time-slot handling, plus PHPUnit coverage for appointment entities and service validation.

## [0.6.0] - 2024-10-01
### Added
- Customers administration screen with search, sorting, pagination, soft-delete toggles, and full CRUD workflow including profile image selection and tag management.
- Customer domain layer with repositories, tag entities, validation service, and multisite-aware schema tables for storing extended customer profiles.
- REST API endpoints at `/wp-json/smooth-booking/v1/customers` supporting list, create, update, delete, and restore operations.
- WP-CLI commands `wp smooth customers <list|create|update|delete|restore>` for headless customer maintenance.
- PHPUnit coverage for customer service sanitisation rules using in-memory repositories.

## [0.5.0] - 2024-09-15
### Changed
- Modernised the Services and Employees admin pages with 2025-ready headers, prominent "Add new" call-to-action buttons, and collapsible form drawers that open only when triggered or editing.
- Added cancel/back affordances, focus management, and refreshed styling to align with current WordPress design guidelines.

### Added
- JavaScript drawer controller that synchronises button state, focus behaviour, and accessibility attributes across admin screens.

## [0.4.0] - 2024-08-01
### Added
- Services administration screen with General/Time/Additional tabs, provider preference controls, online meeting configuration, booking limits, and media-enabled imagery.
- Service domain layer with repositories for services, categories, tags, and provider assignments alongside validation logging.
- REST API controller at `/wp-json/smooth-booking/v1/services` with CRUD and restore operations.
- WP-CLI command suite `wp smooth services <list|create|update|delete|restore>` for headless operations.
- Dedicated admin CSS/JS assets powering tab navigation, provider toggles, color picker initialisation, and media selection for services.
- PHPUnit coverage for `ServiceService` with in-memory repositories.

## [0.3.0] - 2024-07-01
### Added
- Employee profile images with media library selection, removal controls, and avatar previews in the admin list.
- Default color preferences, visibility states (public, private, archived), and employee categories stored in dedicated tables.
- Deleted employee view toggle with restore actions and WP-CLI `wp smooth employees restore` command.
- REST API payload support for profile image IDs, colors, visibility, and category management.
- In-memory category repository test doubles and expanded PHPUnit coverage for the enriched employee entity.

### Changed
- Employee admin screen terminology updated to English and enhanced with accessibility improvements.
- Schema version bumped to 0.3.0 with new database tables for categories and extended employee columns.

## [0.2.0] - 2024-06-15
### Added
- Employee management service, repository, and domain model with validation and soft delete support.
- Smooth Booking top-level admin menu and **Employees** screen with flash notices, dropdown actions, and enqueueable assets.
- REST API endpoints at `/wp-json/smooth-booking/v1/employees` for listing, creating, updating, and deleting employees.
- WP-CLI command suite `wp smooth employees <list|create|update|delete>` for headless operations.
- PHPUnit coverage for employee entities and service sanitisation behaviour.

### Changed
- Settings page now appears under the Smooth Booking menu as **Settings**.
- Plugin version bumped to 0.2.0.

## [0.1.0] - 2024-06-01
### Added
- Initial plugin bootstrap with schema auto-provisioning via `dbDelta()`.
- Multisite-aware activation, deactivation, and uninstall handlers.
- Schema status Settings page with automatic repair option.
- REST API endpoint `/wp-json/smooth-booking/v1/schema-status` for monitoring and repairs.
- Shortcode `[smooth_booking_schema_status]`, Gutenberg block, and template tags for displaying table health.
- WP-CLI commands `wp smooth schema status` and `wp smooth schema repair`.
- Cron-based schema health check and logging support.
- PHPUnit test scaffolding and PHPCS ruleset.
