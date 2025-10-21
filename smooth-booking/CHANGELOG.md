# Changelog

All notable changes to Smooth Booking will be documented here.

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
