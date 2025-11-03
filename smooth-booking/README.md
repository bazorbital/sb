# Smooth Booking

Smooth Booking ensures that the booking-specific database schema is provisioned and exposes tools to manage booking resources. The plugin automatically provisions tables, reports their health status, and now ships with employee, service, customer, and appointment management workflows to run end-to-end booking flows.

## Features
- Automatic schema installation and upgrades with `dbDelta()`
- Schema health overview and configuration page under **Smooth Booking → Settings**
- Location, employee, customer, service, and appointment directories with profile images, configurable colors, tag assignment, soft-delete/restore actions, searchable tables, and 2025-ready admin headers with on-demand creation/edit drawers
- Employee drawer with tabbed General/Location/Services/Schedule sections supporting location assignment, per-service price overrides, and a weekly schedule editor with break management seeded from location business hours
- Locations workspace featuring media-powered profile images, address/phone/email/website capture, industry selection with optgroups, event location toggle, company details, soft delete/restore workflow, and WP media integration
- Rich services form with General/Time/Additional tabs, provider preference logic, color picker, payment method selection, online meeting integration, and customer booking constraints
- REST API endpoints at `/wp-json/smooth-booking/v1/schema-status`, `/wp-json/smooth-booking/v1/locations`, `/wp-json/smooth-booking/v1/employees`, `/wp-json/smooth-booking/v1/customers`, `/wp-json/smooth-booking/v1/services`, and `/wp-json/smooth-booking/v1/appointments`
- REST API endpoint suite at `/wp-json/smooth-booking/v1/services` for listing, creating, updating, deleting, and restoring services
- Shortcode `[smooth_booking_schema_status]` and Gutenberg block “Smooth Booking Schema Status”
- Top-level Smooth Booking admin menu with the **Locations**, **Appointments**, **Employees**, **Customers**, and **Services** screens
- Calendar workspace powered by the EventCalendar library, showing per-location employee columns, configurable time-slot length, service-coloured appointments, inline booking modal, quick edit/delete affordances, a native single-day date selector, and Select2-powered location/service/employee filters with default “All services” and “All employees” options plus chip-style employee toggles that automatically deduplicate options when the roster contains overlapping assignments
- Email notifications workspace with list view, drawer-based creation/editing, recipient scoping, placeholder reference table, and soft delete/disable flows plus SMTP-aware delivery settings
- Daily cron health check
- WP-CLI commands `wp smooth schema <status|repair>`, `wp smooth locations <list|create|update|delete|restore>`, `wp smooth employees <list|create|update|delete|restore>`, `wp smooth customers <list|create|update|delete|restore>`, `wp smooth services <list|create|update|delete|restore>`, `wp smooth appointments <list|delete|restore>`, and `wp smooth holidays <list|add|delete>`
- Multisite-aware activation, deactivation, and uninstall workflows
- Location-based Business Hours editor under **Smooth Booking → Settings → Business Hours** with 15-minute dropdowns for each weekday powering staff templates and calendar visibility
- Location-based Holidays planner under **Smooth Booking → Settings → Holidays** with yearly calendars, range selection, recurring closures, and color-coded status indicators

## Installation
1. Copy the `smooth-booking` directory into `wp-content/plugins/` or install via Composer.
2. Run `composer install` if you need the development dependencies.
3. Activate **Smooth Booking** from the Plugins screen or via WP-CLI: `wp plugin activate smooth-booking`.

## Usage
- Visit **Smooth Booking → Appointments** to create and manage bookings: pick a provider, service, date, and time range; optionally capture customer overrides, internal notes, recurrence flag, and notification preferences. Filter the table by ID, schedule window, creation window, status, employee, service, or customer name, and use soft delete/restore actions.
- Visit **Smooth Booking → Services** to create offerings with dedicated General/Time/Additional tabs, provider preferences (including occupancy windows and random tie-breaking), color pickers, payment method selections, online meeting providers, customer booking limits, and soft-delete/restore workflows. Use the prominent **Add new** button to open the creation drawer only when you need it.
- Visit **Smooth Booking → Locations** to capture physical and virtual locations with media-powered profile images, address/phone/email fields, website URLs, industry taxonomy, event toggle, company details, and soft delete/restore controls.
- Visit **Smooth Booking → Calendar** to inspect location schedules at a glance, review employee availability per slot, colour-coded appointments by service, filter by services or employees via multi-selects or quick chips (with “All services” and “All employees” defaults), and create/edit/delete bookings directly from the grid using the shared appointment form modal.
- Visit **Smooth Booking → Employees** to manage staff members, add new employees, edit existing profiles, or soft-delete entries. The refreshed drawer includes tabbed General, Location, Services, and Schedule sections with media library profile images, WordPress color pickers, visibility options, category assignment, location availability toggles, per-service price overrides, and day-by-day working hours with break management sourced from location business hours.
- Visit **Smooth Booking → Customers** to capture client information, upload profile imagery, assign or create tags, connect WordPress users, and manage soft-deleted records via searchable, sortable, and paginated tables.
- Visit **Smooth Booking → Email notifications** to configure automated emails for booking events, choose recipients (client, employee, administrators, custom), limit by appointment status or services, attach ICS files, and compose HTML/Text bodies with placeholder codes.
- Configure schema repair behaviour under **Smooth Booking → Settings**.
- Configure sender identity, format, reply-to handling, retry periods, and SMTP credentials under **Smooth Booking → Settings → Email**, and send a test message to confirm delivery.
- Define default open and close times per location under **Smooth Booking → Settings → Business Hours**, then apply the template to inform staff schedules and calendar visibility when “Show only business hours in the calendar” is enabled.
- Mark company-wide closures per location under **Smooth Booking → Settings → Holidays** by selecting days or ranges, adding notes, and optionally repeating the closure every year. Existing holidays appear beside the calendar for quick removal.
- Use the REST API for automation:
  - `GET /wp-json/smooth-booking/v1/locations` — list locations (active by default, supports `include_deleted` and `only_deleted`).
  - `POST /wp-json/smooth-booking/v1/locations` — create a location with JSON keys `name`, `address`, `phone`, `base_email`, `website`, `industry_id`, `is_event_location`, `profile_image_id`, `company_name`, `company_address`, and `company_phone`.
  - `GET/PUT/DELETE /wp-json/smooth-booking/v1/locations/<id>` — retrieve, update, or soft-delete a location record using the same payload keys.
  - `POST /wp-json/smooth-booking/v1/locations/<id>/restore` — restore a soft-deleted location.
  - `GET /wp-json/smooth-booking/v1/employees` — list employees (active by default).
  - `POST /wp-json/smooth-booking/v1/employees` — create an employee with JSON body fields `name`, `email`, `phone`, `specialization`, `available_online`, `profile_image_id`, `default_color`, `visibility`, `category_ids`, and `new_categories`.
  - `GET/PUT/DELETE /wp-json/smooth-booking/v1/employees/<id>` — retrieve, update, or soft-delete a record. Updates accept the same payload keys as creation.
  - `GET /wp-json/smooth-booking/v1/customers` — list customers with optional `search`, `page`, `per_page`, and deleted view filters.
  - `POST /wp-json/smooth-booking/v1/customers` — create a customer with payload keys such as `name`, `first_name`, `last_name`, `email`, `phone`, `profile_image_id`, `user_action`, `existing_user_id`, `tag_ids`, `new_tags`, address fields, and `notes`.
  - `GET/PUT/DELETE /wp-json/smooth-booking/v1/customers/<id>` — retrieve, update, or soft-delete a customer record.
  - `POST /wp-json/smooth-booking/v1/customers/<id>/restore` — restore a soft-deleted customer.
  - `GET /wp-json/smooth-booking/v1/services` — list services with optional `include_deleted` or `only_deleted` query parameters.
  - `POST /wp-json/smooth-booking/v1/services` — create a service with payload keys such as `name`, `price`, `visibility`, `providers_preference`, `providers_random_tie`, `category_ids`, `new_categories`, `tag_ids`, `new_tags`, `providers`, `duration_key`, `slot_length_key`, `padding_before_key`, `padding_after_key`, `limit_per_customer`, and `online_meeting_provider`.
  - `GET/PUT/DELETE /wp-json/smooth-booking/v1/services/<id>` — retrieve, update, or soft-delete a service.
  - `POST /wp-json/smooth-booking/v1/services/<id>/restore` — restore a soft-deleted service.
  - `GET /wp-json/smooth-booking/v1/appointments` — list appointments with optional `status`, `employee_id`, `service_id`, `include_deleted`, `only_deleted`, `page`, and `per_page` parameters.
  - `POST /wp-json/smooth-booking/v1/appointments` — create an appointment using keys `provider_id`, `service_id`, `customer_id`, `appointment_date`, `appointment_start`, `appointment_end`, `notes`, `internal_note`, `status`, `payment_status`, `send_notifications`, `is_recurring`, `customer_email`, and `customer_phone`.
  - `GET/PUT/DELETE /wp-json/smooth-booking/v1/appointments/<id>` — retrieve, update, or soft-delete an appointment.
  - `POST /wp-json/smooth-booking/v1/appointments/<id>/restore` — restore a soft-deleted appointment.
- Run WP-CLI helpers:
  - `wp smooth employees list`
  - `wp smooth locations list`
  - `wp smooth locations create --name="Headquarters" --address="Budapest, Example tér 1" --industry=55 --is-event=no`
  - `wp smooth locations update 2 --phone="+3612345678" --website=https://hq.example`
  - `wp smooth locations delete 2`
  - `wp smooth locations restore 2`
  - `wp smooth employees create --name="Jane Doe" --email=jane@example.com --default-color="#3366ff" --visibility=private`
  - `wp smooth employees update 12 --available-online=off --profile-image-id=321`
  - `wp smooth employees delete 12`
  - `wp smooth employees restore 12`
  - `wp smooth customers list`
  - `wp smooth customers create --name="Acme Corp" --email=client@example.com --country=HU`
  - `wp smooth customers update 5 --phone="+36123123" --city="Budapest"`
  - `wp smooth customers delete 5`
  - `wp smooth customers restore 5`
  - `wp smooth services list --include-deleted`
  - `wp smooth services create --name="Massage" --price=89 --providers-preference=least_occupied_day --provider=1 --provider=2:4`
  - `wp smooth services update 7 --visibility=private --limit-per-customer=per_week`
  - `wp smooth services delete 7`
  - `wp smooth services restore 7`
  - `wp smooth appointments list --status=confirmed`
  - `wp smooth appointments delete 42`
  - `wp smooth appointments restore 42`
  - `wp smooth holidays list 1 --year=2025`
  - `wp smooth holidays add 1 2024-12-24 2024-12-26 --note="Christmas" --repeat`
  - `wp smooth holidays delete 1 12`

## Developer Notes
- PHP 8.1+ and WordPress 6.x are required.
- Tables are prefixed with the site prefix plus `smooth_` to avoid collisions and include dedicated employee category and relationship tables.
- Schema definitions live in `src/Infrastructure/Database/SchemaDefinitionBuilder.php`.
- Employee persistence is handled by `src/Infrastructure/Repository/EmployeeRepository.php` and exposed via `SmoothBooking\Domain\Employees\EmployeeService`.
- Location persistence is handled by `src/Infrastructure/Repository/LocationRepository.php` and orchestrated through `SmoothBooking\Domain\Locations\LocationService` and `src/Admin/LocationsPage.php`.
- Customer persistence is handled by `src/Infrastructure/Repository/CustomerRepository.php`, tag repositories, and orchestrated through `SmoothBooking\Domain\Customers\CustomerService`.
- Service persistence is handled by `src/Infrastructure/Repository/ServiceRepository.php` plus category/tag repositories and orchestrated through `SmoothBooking\Domain\Services\ServiceService`.
- Appointment persistence is handled by `src/Infrastructure/Repository/AppointmentRepository.php` and orchestrated through `SmoothBooking\Domain\Appointments\AppointmentService`.
- Email notification rules/templates are handled by `src/Infrastructure/Repository/EmailNotificationRepository.php` and orchestrated through `SmoothBooking\Domain\Notifications\EmailNotificationService` and `EmailSettingsService`.
- Business hours templates are handled by `src/Domain/BusinessHours/BusinessHoursService.php` backed by `src/Infrastructure/Repository/BusinessHoursRepository.php` and the location repository in `src/Infrastructure/Repository/LocationRepository.php`.
- Location holidays are handled by `src/Domain/Holidays/HolidayService.php` backed by `src/Infrastructure/Repository/HolidayRepository.php`, cached via the object cache when available, and exposed through Settings, WP-CLI, and public hooks.
- Database versioning is stored in the `smooth_booking_db_version` option.
- Run coding standards with `vendor/bin/phpcs` (ruleset in `phpcs.xml`).
- Run unit tests with `composer test` (PHPUnit bootstrap in `tests/bootstrap.php`).
- REST controllers reside in `src/Rest/` and register routes on `rest_api_init`.
- Admin calendar data is exposed via the `window.SmoothBookingCalendarData` global before `admin-calendar.js` runs to prevent WordPress localisation helpers from overwriting the schedule payload that feeds the EventCalendar grid.
- Select2 assets are registered through `SmoothBooking\Infrastructure\Assets\Select2AssetRegistrar`, which aliases WordPress' bundled SelectWoo library to guarantee the enhanced multi-select UI without shipping duplicate vendor scripts.

## Hooks
- `smooth_booking_cleanup_event` — Daily cron event for health checks.
- `smooth_booking_locations_list` — Filter the location list before rendering in admin, REST, or CLI contexts.
- `smooth_booking_employees_list` — Filter the employee list before rendering or API responses.
- `smooth_booking_customers_paginated` — Filter the paginated customer result before rendering or API responses.
- `smooth_booking_location_holidays_saved` — Fires after one or more holidays have been saved for a location.
- `smooth_booking_location_holiday_deleted` — Fires after a holiday has been deleted for a location.

## Internationalization
Translations are loaded from the `languages/` directory. Generate a POT file via `wp i18n make-pot . languages/smooth-booking.pot`.

## License
GPL-2.0-or-later. See `LICENSE` at https://www.gnu.org/licenses/gpl-2.0.html.
