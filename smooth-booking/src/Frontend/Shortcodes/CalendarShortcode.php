<?php
/**
 * Calendar shortcode output.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Frontend\Shortcodes;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Plugin;

/**
 * Provides [smooth_booking_calendar] shortcode.
 */
class CalendarShortcode implements Registrable {
private Plugin $plugin;

/**
 * Tracks localization state.
 *
 * @var bool
 */
private bool $localized = false;

public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

public function register(): void {
add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
add_shortcode( 'smooth_booking_calendar', [ $this, 'render_shortcode' ] );
add_action( 'init', [ $this, 'register_template_tag' ] );
}

/**
 * Registers reusable assets.
 */
public function register_assets(): void {
if ( ! wp_script_is( 'smooth-booking-vcalendar', 'registered' ) ) {
wp_register_script(
'smooth-booking-vcalendar',
'https://cdn.jsdelivr.net/npm/js-year-calendar@latest/dist/js-year-calendar.min.js',
[],
$this->plugin->version(),
true
);

wp_register_style(
'smooth-booking-vcalendar',
'https://cdn.jsdelivr.net/npm/js-year-calendar@latest/dist/js-year-calendar.min.css',
[],
$this->plugin->version()
);
}

wp_register_style(
'smooth-booking-frontend-calendar',
$this->plugin->url() . 'assets/css/calendar.css',
[ 'smooth-booking-vcalendar' ],
$this->plugin->version()
);

wp_register_script(
'smooth-booking-frontend-calendar',
$this->plugin->url() . 'assets/js/frontend-calendar.js',
[ 'smooth-booking-vcalendar', 'wp-api-fetch', 'wp-i18n' ],
$this->plugin->version(),
true
);
}

/**
 * Shortcode callback.
 *
 * @param array<string, mixed> $atts Attributes.
 */
public function render_shortcode( array $atts = [] ): string {
$atts = shortcode_atts(
[
'employee' => 0,
],
$atts,
'smooth_booking_calendar'
);

$employee = (int) $atts['employee'];

wp_enqueue_style( 'smooth-booking-frontend-calendar' );
wp_enqueue_script( 'smooth-booking-frontend-calendar' );

if ( ! $this->localized ) {
wp_localize_script(
'smooth-booking-frontend-calendar',
'SmoothBookingFrontend',
[
'restUrl' => esc_url_raw( rest_url( 'smooth-booking/v1/calendar' ) ),
'nonce'   => wp_create_nonce( 'wp_rest' ),
'i18n'    => [
'loading' => __( 'Loading events…', 'smooth-booking' ),
'empty'   => __( 'No events scheduled for this range.', 'smooth-booking' ),
],
]
);
$this->localized = true;
}

return sprintf(
'<div class="smooth-booking-calendar" data-employee="%1$s"></div>',
esc_attr( $employee )
);
}

/**
 * Declares template tag.
 */
public function register_template_tag(): void {
if ( function_exists( 'the_smooth_booking_calendar' ) ) {
return;
}

function the_smooth_booking_calendar( array $atts = [] ): void {
$employee = isset( $atts['employee'] ) ? (int) $atts['employee'] : 0;
echo do_shortcode( sprintf( '[smooth_booking_calendar employee="%d"]', $employee ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
}
}
