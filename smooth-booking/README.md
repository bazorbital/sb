# Smooth Booking

Smooth Booking ensures that the booking-specific database schema is present on every WordPress site. The plugin automatically provisions tables defined in `db.sql` on activation and validates them on each load, preventing missing-table errors before the full booking feature-set is delivered.

## Features
- Automatic schema installation and upgrades with `dbDelta()`
- Schema health overview via Settings API page
- REST API endpoint at `/wp-json/smooth-booking/v1/schema-status`
- Shortcode `[smooth_booking_schema_status]`
- Dynamic Gutenberg block “Smooth Booking Schema Status”
- Daily cron health check
- WP-CLI command `wp smooth schema <status|repair>`
- Multisite-aware activation, deactivation, and uninstall workflows

## Installation
1. Copy the `smooth-booking` directory into `wp-content/plugins/` or install via Composer.
2. Run `composer install` if you need the development dependencies.
3. Activate **Smooth Booking** from the Plugins screen or via WP-CLI: `wp plugin activate smooth-booking`.

## Usage
- Visit **Settings → Smooth Booking** to monitor table status and toggle automatic repairs.
- Use the shortcode or Gutenberg block to surface schema health in admin dashboards.
- Call the REST endpoint with an authenticated request to fetch the current status. Include an `_wpnonce` created via `wp_create_nonce( 'wp_rest' )` for repair operations.

## Developer Notes
- PHP 8.1+ and WordPress 6.x are required.
- Tables are prefixed with the site prefix plus `smooth_` to avoid collisions.
- Schema definitions live in `src/Infrastructure/Database/SchemaDefinitionBuilder.php`.
- Database versioning is stored in the `smooth_booking_db_version` option.
- Run coding standards with `vendor/bin/phpcs` (ruleset in `phpcs.xml`).
- Run unit tests with `composer test` (PHPUnit bootstrap in `tests/bootstrap.php`).

## Hooks
- `smooth_booking_cleanup_event` — Daily cron event for health checks.

## Internationalization
Translations are loaded from the `languages/` directory. Generate a POT file via `wp i18n make-pot . languages/smooth-booking.pot`.

## License
GPL-2.0-or-later. See `LICENSE` at https://www.gnu.org/licenses/gpl-2.0.html.
