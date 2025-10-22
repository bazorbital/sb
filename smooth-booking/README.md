# Smooth Booking

Smooth Booking ensures that the booking-specific database schema is provisioned and exposes tools to manage booking resources. The plugin automatically provisions tables, reports their health status, and now introduces employee management workflows to prepare for full booking flows.

## Features
- Automatic schema installation and upgrades with `dbDelta()`
- Schema health overview and configuration page under **Smooth Booking → Settings**
- Employee, customer, and service directories with profile images, configurable colors, tag assignment, soft-delete/restore actions, searchable tables, and 2025-ready admin headers with on-demand creation/edit drawers
- Rich services form with General/Time/Additional tabs, provider preference logic, color picker, payment method selection, online meeting integration, and customer booking constraints
- REST API endpoints at `/wp-json/smooth-booking/v1/schema-status`, `/wp-json/smooth-booking/v1/employees`, and `/wp-json/smooth-booking/v1/customers`
- REST API endpoint suite at `/wp-json/smooth-booking/v1/services` for listing, creating, updating, deleting, and restoring services
- Shortcode `[smooth_booking_schema_status]` and Gutenberg block “Smooth Booking Schema Status”
- Top-level Smooth Booking admin menu with the **Employees**, **Customers**, and **Services** screens
- Daily cron health check
- WP-CLI commands `wp smooth schema <status|repair>`, `wp smooth employees <list|create|update|delete|restore>`, `wp smooth customers <list|create|update|delete|restore>`, and `wp smooth services <list|create|update|delete|restore>`
- Multisite-aware activation, deactivation, and uninstall workflows

## Installation
1. Copy the `smooth-booking` directory into `wp-content/plugins/` or install via Composer.
2. Run `composer install` if you need the development dependencies.
3. Activate **Smooth Booking** from the Plugins screen or via WP-CLI: `wp plugin activate smooth-booking`.

## Usage
- Visit **Smooth Booking → Services** to create offerings with dedicated General/Time/Additional tabs, provider preferences (including occupancy windows and random tie-breaking), color pickers, payment method selections, online meeting providers, customer booking limits, and soft-delete/restore workflows. Use the prominent **Add new** button to open the creation drawer only when you need it.
- Visit **Smooth Booking → Employees** to manage staff members, add new employees, edit existing profiles, or soft-delete entries. The page supports media library profile images, WordPress color pickers, visibility options, category assignment, a toggle to review or restore deleted employees, and the same on-demand drawer flow for creation or editing.
- Visit **Smooth Booking → Customers** to capture client information, upload profile imagery, assign or create tags, connect WordPress users, and manage soft-deleted records via searchable, sortable, and paginated tables.
- Configure schema repair behaviour under **Smooth Booking → Settings**.
- Use the REST API for automation:
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
- Run WP-CLI helpers:
  - `wp smooth employees list`
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

## Developer Notes
- PHP 8.1+ and WordPress 6.x are required.
- Tables are prefixed with the site prefix plus `smooth_` to avoid collisions and include dedicated employee category and relationship tables.
- Schema definitions live in `src/Infrastructure/Database/SchemaDefinitionBuilder.php`.
- Employee persistence is handled by `src/Infrastructure/Repository/EmployeeRepository.php` and exposed via `SmoothBooking\Domain\Employees\EmployeeService`.
- Customer persistence is handled by `src/Infrastructure/Repository/CustomerRepository.php`, tag repositories, and orchestrated through `SmoothBooking\Domain\Customers\CustomerService`.
- Service persistence is handled by `src/Infrastructure/Repository/ServiceRepository.php` plus category/tag repositories and orchestrated through `SmoothBooking\Domain\Services\ServiceService`.
- Database versioning is stored in the `smooth_booking_db_version` option.
- Run coding standards with `vendor/bin/phpcs` (ruleset in `phpcs.xml`).
- Run unit tests with `composer test` (PHPUnit bootstrap in `tests/bootstrap.php`).
- REST controllers reside in `src/Rest/` and register routes on `rest_api_init`.

## Hooks
- `smooth_booking_cleanup_event` — Daily cron event for health checks.
- `smooth_booking_employees_list` — Filter the employee list before rendering or API responses.
- `smooth_booking_customers_paginated` — Filter the paginated customer result before rendering or API responses.

## Internationalization
Translations are loaded from the `languages/` directory. Generate a POT file via `wp i18n make-pot . languages/smooth-booking.pot`.

## License
GPL-2.0-or-later. See `LICENSE` at https://www.gnu.org/licenses/gpl-2.0.html.
