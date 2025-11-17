<?php
/**
 * Gutenberg block registration.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Frontend\Blocks;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Plugin;

/**
 * Registers calendar block.
 */
class CalendarBlockRegistrar implements Registrable {
private Plugin $plugin;

public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

public function register(): void {
add_action( 'init', [ $this, 'register_block' ] );
}

public function register_block(): void {
wp_register_script(
'smooth-booking-calendar-block',
$this->plugin->url() . 'blocks/calendar/index.js',
[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render' ],
$this->plugin->version(),
true
);

register_block_type(
$this->plugin->path() . 'blocks/calendar',
[
'render_callback' => [ $this, 'render_block' ],
]
);
}

/**
 * Server-side render.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
public function render_block( array $attributes, string $content = '' ): string {
$employee = isset( $attributes['employee'] ) ? (int) $attributes['employee'] : 0;

return do_shortcode( sprintf( '[smooth_booking_calendar employee="%d"]', $employee ) );
}
}
