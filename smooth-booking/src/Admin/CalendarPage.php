<?php
/**
 * Calendar admin screen.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Infrastructure\BookingRepository;
use SmoothBooking\Plugin;
use WP_Error;

/**
 * Provides calendar admin page backed by AJAX similar to vkurko/calendar integration.
 */
class CalendarPage implements Registrable {
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
 * Registers hooks.
 */
public function register(): void {
add_action( 'admin_menu', [ $this, 'register_menu' ] );
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
add_action( 'wp_ajax_smooth_booking_calendar', [ $this, 'handle_ajax' ] );
}

/**
 * Adds submenu for calendar.
 */
public function register_menu(): void {
add_submenu_page(
'smooth-booking',
__( 'Calendar', 'smooth-booking' ),
__( 'Calendar', 'smooth-booking' ),
'manage_options',
'smooth-booking-calendar',
[ $this, 'render_page' ]
);
}

/**
 * Enqueues scripts when needed.
 *
 * @param string $hook Current admin page hook.
 */
public function enqueue_assets( string $hook ): void {
if ( 'smooth-booking_page_smooth-booking-calendar' !== $hook ) {
return;
}

wp_enqueue_style(
'smooth-booking-calendar',
$this->plugin->url() . 'assets/css/calendar.css',
[],
$this->plugin->version()
);

wp_enqueue_script(
'smooth-booking-vcalendar',
'https://cdn.jsdelivr.net/npm/js-year-calendar@latest/dist/js-year-calendar.min.js',
[],
$this->plugin->version(),
true
);

wp_enqueue_style(
'smooth-booking-vcalendar',
'https://cdn.jsdelivr.net/npm/js-year-calendar@latest/dist/js-year-calendar.min.css',
[],
$this->plugin->version()
);

wp_enqueue_script(
'smooth-booking-admin-calendar',
$this->plugin->url() . 'assets/js/admin-calendar.js',
[ 'jquery', 'smooth-booking-vcalendar', 'wp-i18n' ],
$this->plugin->version(),
true
);

$nonce = wp_create_nonce( 'smooth_booking_calendar' );

wp_localize_script(
'smooth-booking-admin-calendar',
'SmoothBookingCalendar',
[
'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
'nonce'     => $nonce,
'texts'     => [
'loading' => __( 'Loading calendar…', 'smooth-booking' ),
],
]
);
}

/**
 * Handles AJAX calendar requests.
 */
public function handle_ajax(): void {
check_ajax_referer( 'smooth_booking_calendar', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( new WP_Error( 'forbidden', __( 'You are not allowed to view this calendar.', 'smooth-booking' ), [ 'status' => 403 ] ), 403 );
}

$start    = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : gmdate( 'Y-m-01 00:00:00' );
$end      = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : gmdate( 'Y-m-t 23:59:59' );
$employee = isset( $_GET['employee'] ) ? absint( wp_unslash( $_GET['employee'] ) ) : null;

$service = new CalendarService( new BookingRepository() );
$events  = $service->get_events( $start, $end, $employee ?: null );

wp_send_json_success( [ 'events' => $events ] );
}

/**
 * Outputs calendar page markup.
 */
public function render_page(): void {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You are not allowed to view this page.', 'smooth-booking' ) );
}

$options = get_option( 'smooth_booking_options', [] );
$default = isset( $options['default_employee'] ) ? (int) $options['default_employee'] : 0;
?>
<div class="wrap">
<h1><?php echo esc_html__( 'Booking Calendar', 'smooth-booking' ); ?></h1>
<p><?php esc_html_e( 'The calendar uses the js-year-calendar library shipped with vkurko/calendar to render employee bookings with AJAX updates.', 'smooth-booking' ); ?></p>
<label for="smooth-booking-employee" class="screen-reader-text"><?php esc_html_e( 'Select employee', 'smooth-booking' ); ?></label>
<select id="smooth-booking-employee" class="smooth-booking-employee">
<option value="0"><?php esc_html_e( 'All employees', 'smooth-booking' ); ?></option>
<?php
$employees = apply_filters( 'smooth_booking_admin_employees', [] );
foreach ( $employees as $employee_id => $employee_name ) :
?>
<option value="<?php echo esc_attr( (int) $employee_id ); ?>" <?php selected( $default, (int) $employee_id ); ?>><?php echo esc_html( $employee_name ); ?></option>
<?php
endforeach;
?>
</select>
<div id="smooth-booking-calendar" data-default-employee="<?php echo esc_attr( $default ); ?>">
<p class="description"><?php esc_html_e( 'Loading calendar…', 'smooth-booking' ); ?></p>
</div>
</div>
<?php
}
}
