=== Smooth Booking ===
Contributors: smoothbooking
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A booking companion plugin with an AJAX-powered calendar for employees.

== Description ==
* Staff/employee calendar rendered with the js-year-calendar library and the open-source vkurko/calendar example.
* Settings page to define default employee and calendar behaviour.
* Front end shortcode `[smooth_booking_calendar]`, Gutenberg block and template tag helper.
* REST endpoint `wp-json/smooth-booking/v1/calendar` and transient caching.
* WP-CLI command `wp smooth-booking flush-cache` to clear cached responses.

== Installation ==
1. Upload the `smooth-booking` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Visit **Smooth Booking → Settings** to configure date format and employees.
4. Use the **Calendar** submenu for the AJAX-powered admin calendar.

== Frequently Asked Questions ==
= How do I display the calendar on the front end? =
Use the `[smooth_booking_calendar employee="0"]` shortcode, the `Smooth Booking Calendar` Gutenberg block, or call `the_smooth_booking_calendar();` inside a theme.

= How do I register employees? =
Hook into `smooth_booking_admin_employees` filter and return an associative array of `id => label`.

== Hooks ==
* `smooth_booking_admin_employees` filter: customize employee dropdown.
* `smooth_booking_rest_calendar_response` filter: alter REST payloads.

== Changelog ==
= 1.1.0 =
* ADDED: AJAX admin calendar built from the vkurko/calendar integration sample with employee filtering.
* ADDED: REST endpoint, Gutenberg block, shortcode and template tag for the calendar.
* ADDED: WP-CLI cache flush command and cron-based transient invalidation.
