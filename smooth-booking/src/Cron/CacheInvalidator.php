<?php
/**
 * Cron job to purge cached calendar responses.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Cron;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Infrastructure\CacheRepository;
use SmoothBooking\Plugin;

/**
 * Handles cron hook registration.
 */
class CacheInvalidator implements Registrable {
private Plugin $plugin;

public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

public function register(): void {
add_action( 'smooth_booking_purge_cache', [ $this, 'purge' ] );
}

/**
 * Purges cache entries.
 */
public function purge(): void {
CacheRepository::flush();
}
}
