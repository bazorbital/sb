# Smooth Booking

Booking/calendar helper plugin tailored for the Smooth Booking project. It bundles the employee calendar refactor requested in the specification based on the [vkurko/calendar](https://github.com/vkurko/calendar) example.

## Requirements
- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+/8+

## Installation
1. Copy the `smooth-booking` directory into `wp-content/plugins/`.
2. Run `composer install` if you want autoloading through Composer.
3. Activate **Smooth Booking** inside WordPress.
4. Navigate to **Smooth Booking → Settings** to set up date formats and default employee ID.

## Usage
- Admin users can open **Smooth Booking → Calendar** to access the AJAX calendar that mirrors the GitHub sample. Use the employee dropdown to filter bookings.
- Frontend output is available through:
  - Shortcode: `[smooth_booking_calendar employee="0"]`
  - Gutenberg block: *Smooth Booking Calendar*
  - Template tag: `the_smooth_booking_calendar();`
- Booking data is exposed at `wp-json/smooth-booking/v1/calendar` and cached for five minutes. Clear caches via WP-CLI `wp smooth-booking flush-cache` or wait for the cron hook `smooth_booking_purge_cache`.

## Developer Notes
- Custom employees can be injected using the `smooth_booking_admin_employees` filter.
- REST responses can be filtered with `smooth_booking_rest_calendar_response`.
- Coding standards: `composer lint` (PHPCS) and tests via `composer test` (PHPUnit).
- Cached REST responses are tracked in the `smooth_booking_cached_calendars` option; the cron and CLI helpers rely on this state.

## Testing
```
composer install
composer test
```

## License
GPL-2.0-or-later
