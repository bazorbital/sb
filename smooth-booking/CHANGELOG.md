# Changelog

All notable changes to Smooth Booking will be documented here.

## [0.2.0] - 2024-06-15
### Added
- Employee management service, repository, and domain model with validation and soft delete support.
- Smooth Booking top-level admin menu and **Alkalmazottak** screen with flash notices, dropdown actions, and enqueueable assets.
- REST API endpoints at `/wp-json/smooth-booking/v1/employees` for listing, creating, updating, and deleting employees.
- WP-CLI command suite `wp smooth employees <list|create|update|delete>` for headless operations.
- PHPUnit coverage for employee entities and service sanitisation behaviour.

### Changed
- Settings page now appears under the Smooth Booking menu as **Beállítások**.
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
