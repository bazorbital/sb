# Changelog

All notable changes to Smooth Booking will be documented here.

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
