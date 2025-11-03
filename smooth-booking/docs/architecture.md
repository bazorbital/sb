# Smooth Booking Plugin Architecture

## Overview
Smooth Booking is a modular WordPress plugin that exposes appointment booking features through admin screens, REST APIs, shortcodes, and scheduled maintenance routines. The system is organised into the following layers:

- **Plugin Bootstrap (`src/Plugin.php`)** – wires the service container, registers WordPress hooks, and coordinates feature controllers.
- **Service Provider (`src/ServiceProvider.php`)** – registers repositories, domain services, admin controllers, REST controllers, CLI commands, and infrastructure utilities with the dependency container.
- **Support Utilities (`src/Support`)** – contains the lightweight `ServiceContainer` used as the shared dependency injection mechanism.
- **Domain Layer (`src/Domain`)** – defines entities (customers, employees, services, appointments, etc.) plus associated service interfaces for business rules and invariants.
- **Infrastructure (`src/Infrastructure`)** – implements repositories, logging, database schema management, and WordPress settings integration.
- **User Interfaces**:
  - **Admin (`src/Admin`)** – renders WordPress admin pages for managing locations, staff, appointments, notifications, and settings.
  - **Frontend (`src/Frontend`)** – exposes Gutenberg blocks, shortcodes, and template tags for frontend embedding.
  - **REST (`src/Rest`)** – implements REST controllers to manage domain resources programmatically.
  - **CLI (`src/Cli`)** – provides WP-CLI commands for automation, mirroring REST operations.
- **Automation (`src/Cron`)** – schedules recurring cleanup tasks using WP-Cron.
- **Tests (`tests/`)** – contains automated coverage for repositories and domain logic.

## Bootstrapping Flow
1. `smooth-booking.php` defines plugin metadata, constants, autoloading, and activation/deactivation hooks.
2. `SmoothBooking\Plugin::instance()->run()` loads template tags, synchronises the logger state, and registers hooks for admin pages, REST routes, blocks, shortcodes, and cron events.
3. `SmoothBooking\ServiceProvider::register()` binds concrete implementations to interfaces inside the container. Most services are registered as singletons to share database and logging dependencies.
4. Admin controllers, REST controllers, CLI commands, and cron schedulers are resolved from the container and registered with WordPress at runtime.

## Module Responsibilities
### Admin Controllers (`src/Admin`)
Each admin class represents a WordPress admin page. Methods follow this pattern:
- `render_page()` outputs markup with translation, escaping, and nonce protection.
- `handle_save()/handle_delete()/handle_restore()` process form submissions using domain services and perform capability checks (`current_user_can`).
- `enqueue_assets()` loads page-specific scripts and styles.

### Domain Services (`src/Domain`)
Domain services coordinate repository operations and enforce validation rules. Entities expose rich getters, formatting helpers, and `to_array()` methods for API responses.

### Infrastructure Repositories (`src/Infrastructure/Repository`)
Repositories interact with `$wpdb` to persist domain entities. They encapsulate SQL, mapping, and logging through `SmoothBooking\Infrastructure\Logging\Logger`.

### REST Controllers (`src/Rest`)
REST controllers wrap domain services in REST routes. Each controller implements:
- Route registration via `register_rest_route` with permission callbacks.
- CRUD methods that sanitise input, call the domain service, and return `WP_REST_Response` objects.
- Helper methods to normalise payloads, ensuring all user input is sanitised (emails, text, booleans) before reaching the domain layer.

### CLI Commands (`src/Cli`)
WP-CLI classes mirror REST controllers, exposing administrative tasks such as migrating schema, listing customers, and seeding demo data. Commands are registered by `Plugin::register_cli()` when WP-CLI is available.

### Cron (`src/Cron`)
`CleanupScheduler` schedules and executes cleanup routines (e.g., purging soft-deleted records) by hooking into WordPress cron events.

### Frontend Blocks & Shortcodes (`src/Frontend`)
Frontend modules provide reusable UI integrations:
- `SchemaStatusBlock` registers the Gutenberg block for surfacing schema health.
- `SchemaStatusShortcode` exposes the same status as a shortcode for classic editors.
- `TemplateTags.php` contains procedural helpers used in theme templates.

## Security Considerations
- Admin controllers check user capabilities (typically `manage_options`) before executing sensitive operations.
- Nonce verification is performed for POST handlers and REST actions that mutate state (`check_admin_referer`, `check_ajax_referer`).
- Input sanitisation is performed both in REST controllers (via `sanitize_*` helpers) and admin handlers (via `sanitize_text_field`, `wp_unslash`, etc.).
- Output escaping is enforced through WordPress helpers (`esc_html`, `esc_url`, `esc_attr`) in admin templates.

## Extensibility Notes
- Service bindings can be replaced by calling `$container->singleton()` before `Plugin::run()` completes, enabling custom repositories or services.
- New REST endpoints should follow the pattern established in `src/Rest/*Controller.php`: register routes in `register_routes()` and return `WP_REST_Response` objects.
- Admin pages share UI traits (`AdminStylesTrait`, `AppointmentFormRendererTrait`) to maintain consistent markup; new pages should reuse these traits for styling and accessibility.
- The dependency container allows adding custom bindings or overriding existing ones without modifying core classes, facilitating child plugins or extensions.

## TODO
- Add granular documentation for individual domain entities and repositories to clarify complex business rules (e.g., appointment recurrence and payment workflows).
- Document JavaScript modules inside `assets/js/` to highlight DOM interactions and REST integrations used on admin screens.
