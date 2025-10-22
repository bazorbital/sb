# Changelog

All notable changes to Smooth Booking will be documented here.

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
