=== Smooth Booking ===
Contributors: smoothbooking
Tags: booking, appointments, scheduling
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smooth Booking ensures the booking database environment is ready for custom scheduling workflows.

== Description ==
Smooth Booking validates and creates required database tables on activation and at runtime to guarantee a healthy environment for future booking features. It ships with a schema status dashboard, REST API endpoint, shortcode, and Gutenberg block for monitoring installations.

== Installation ==
1. Upload the `smooth-booking` folder to the `/wp-content/plugins/` directory or install via Composer.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Settings → Smooth Booking** to review schema health and configure automatic repairs.

== Frequently Asked Questions ==
= Does the plugin support multisite? =
Yes. Activation, deactivation, and uninstall operations run across all sites when network activated.

= How can I verify the schema from the command line? =
Use WP-CLI:

`wp smooth schema status`

== Screenshots ==
1. Settings page summarizing schema health.

== Changelog ==
= 0.1.0 =
* Initial release. Creates booking schema on activation and runtime. Provides Settings API integration, REST endpoint, shortcode, Gutenberg block, cron maintenance, and WP-CLI commands for schema management.

== Upgrade Notice ==
= 0.1.0 =
First release.
