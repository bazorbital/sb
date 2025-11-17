<?php
/**
 * Main plugin orchestrator.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Admin\CalendarPage;
use SmoothBooking\Admin\SettingsPage;
use SmoothBooking\Cron\CacheInvalidator;
use SmoothBooking\Frontend\Blocks\CalendarBlockRegistrar;
use SmoothBooking\Frontend\Shortcodes\CalendarShortcode;
use SmoothBooking\Rest\CalendarController;
use SmoothBooking\ServiceProvider;
use SmoothBooking\Infrastructure\PostTypeRegistrar;
use SmoothBooking\CLI\SyncCommand;

/**
 * Plugin bootstrap class.
 */
class Plugin {
/**
 * Plugin file path.
 *
 * @var string
 */
private string $plugin_file;

/**
 * Service provider instance.
 *
 * @var ServiceProvider
 */
private ServiceProvider $provider;

/**
 * Constructor.
 *
 * @param string $plugin_file Main plugin file.
 */
public function __construct( string $plugin_file ) {
$this->plugin_file = $plugin_file;
$this->provider    = new ServiceProvider( $this );
}

/**
 * Bootstraps the plugin.
 */
public function run(): void {
$this->provider->register( [
PostTypeRegistrar::class,
SettingsPage::class,
CalendarPage::class,
CalendarShortcode::class,
CalendarBlockRegistrar::class,
CalendarController::class,
CacheInvalidator::class,
SyncCommand::class,
] );
}

/**
 * Returns plugin url.
 */
public function url(): string {
return plugin_dir_url( $this->plugin_file );
}

/**
 * Returns plugin path.
 */
public function path(): string {
return plugin_dir_path( $this->plugin_file );
}

/**
 * Returns plugin version.
 */
public function version(): string {
return SMOOTH_BOOKING_VERSION;
}
}
