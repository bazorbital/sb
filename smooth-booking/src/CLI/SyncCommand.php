<?php
/**
 * WP-CLI integration.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\CLI;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Infrastructure\CacheRepository;
use SmoothBooking\Plugin;
use WP_CLI;

/**
 * `wp smooth-booking flush-cache` command.
 */
class SyncCommand implements Registrable {
private Plugin $plugin;

public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

public function register(): void {
if ( defined( 'WP_CLI' ) && WP_CLI ) {
WP_CLI::add_command( 'smooth-booking', [ $this, 'handle' ] );
}
}

/**
 * Handles CLI.
 *
 * @param array<int, string> $args Positional args.
 * @param array<string, mixed> $assoc_args Named args.
 */
public function handle( array $args, array $assoc_args ): void {
$subcommand = $args[0] ?? 'flush-cache';

if ( 'flush-cache' === $subcommand ) {
$count = CacheRepository::flush();
WP_CLI::success( sprintf( '%d cached responses removed.', $count ) );
return;
}

WP_CLI::error( __( 'Unknown command.', 'smooth-booking' ) );
}
}
