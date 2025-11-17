<?php
/**
 * Simple service provider.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Contracts\Registrable;

/**
 * Registers services with WordPress.
 */
class ServiceProvider {
/**
 * Plugin instance.
 *
 * @var Plugin
 */
private Plugin $plugin;

/**
 * Constructor.
 *
 * @param Plugin $plugin Plugin instance.
 */
public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

/**
 * Register provided services.
 *
 * @param array<int, class-string<Registrable>> $services Services to register.
 */
public function register( array $services ): void {
foreach ( $services as $service ) {
$instance = new $service( $this->plugin );

if ( $instance instanceof Registrable ) {
$instance->register();
}
}
}
}
