<?php
/**
 * REST controller for calendar data.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Infrastructure\BookingRepository;
use SmoothBooking\Infrastructure\CacheRepository;
use SmoothBooking\Plugin;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes /smooth-booking/v1/calendar endpoint.
 */
class CalendarController implements Registrable {
private Plugin $plugin;

public function __construct( Plugin $plugin ) {
$this->plugin = $plugin;
}

public function register(): void {
add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}

public function register_routes(): void {
register_rest_route(
'smooth-booking/v1',
'/calendar',
[
'methods'             => 'GET',
'callback'            => [ $this, 'get_items' ],
'permission_callback' => '__return_true',
'args'                => [
'start'    => [
'required' => true,
'sanitize_callback' => 'sanitize_text_field',
],
'end'      => [
'required' => true,
'sanitize_callback' => 'sanitize_text_field',
],
'employee' => [
'sanitize_callback' => 'absint',
],
],
],
true
);
}

/**
 * Returns events.
 */
public function get_items( WP_REST_Request $request ): WP_REST_Response {
$start    = $request->get_param( 'start' ) ?: gmdate( 'Y-m-01 00:00:00' );
$end      = $request->get_param( 'end' ) ?: gmdate( 'Y-m-t 23:59:59' );
$employee = $request->get_param( 'employee' ) ? absint( $request->get_param( 'employee' ) ) : null;

$key = 'smooth_booking_cal_' . md5( $start . $end . ( $employee ?? 0 ) );
$cache = get_transient( $key );

if ( false !== $cache ) {
return rest_ensure_response( $cache );
}

$service = new CalendarService( new BookingRepository() );
$events  = $service->get_events( $start, $end, $employee ?: null );

$response = [ 'events' => $events ];

set_transient( $key, $response, 5 * MINUTE_IN_SECONDS );
CacheRepository::add_key( $key );

/**
 * Filters REST response before returning.
 *
 * @param array<string, mixed> $response Response data.
 * @param WP_REST_Request      $request  Request instance.
 */
$response = apply_filters( 'smooth_booking_rest_calendar_response', $response, $request );

return rest_ensure_response( $response );
}
}
