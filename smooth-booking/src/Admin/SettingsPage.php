<?php
/**
 * Settings page.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Plugin;

/**
 * Handles plugin settings page.
 */
class SettingsPage implements Registrable {
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
 * {@inheritDoc}
 */
public function register(): void {
add_action( 'admin_menu', [ $this, 'register_menu' ] );
add_action( 'admin_init', [ $this, 'register_settings' ] );
}

/**
 * Registers top level menu.
 */
public function register_menu(): void {
add_menu_page(
__( 'Smooth Booking', 'smooth-booking' ),
__( 'Smooth Booking', 'smooth-booking' ),
'manage_options',
'smooth-booking',
[ $this, 'render_page' ],
'dashicons-calendar-alt'
);
}

/**
 * Registers plugin settings.
 */
public function register_settings(): void {
register_setting( 'smooth_booking', 'smooth_booking_options', [ $this, 'sanitize_options' ] );

add_settings_section(
'sb_general',
__( 'Calendar settings', 'smooth-booking' ),
'__return_false',
'smooth-booking'
);

add_settings_field(
'sb_date_format',
__( 'Date format', 'smooth-booking' ),
[ $this, 'render_date_format_field' ],
'smooth-booking',
'sb_general'
);

add_settings_field(
'sb_default_employee',
__( 'Default employee ID', 'smooth-booking' ),
[ $this, 'render_employee_field' ],
'smooth-booking',
'sb_general'
);

add_settings_field(
'sb_page_size',
__( 'Calendar page size', 'smooth-booking' ),
[ $this, 'render_page_size_field' ],
'smooth-booking',
'sb_general'
);
}

/**
 * Sanitizes options.
 *
 * @param array<string, mixed> $input Raw values.
 * @return array<string, mixed>
 */
public function sanitize_options( array $input ): array {
$options = get_option( 'smooth_booking_options', [] );

$options['date_format']        = isset( $input['date_format'] ) ? sanitize_text_field( wp_unslash( $input['date_format'] ) ) : 'Y-m-d H:i';
$options['default_employee']   = isset( $input['default_employee'] ) ? absint( $input['default_employee'] ) : 0;
$options['calendar_page_size'] = isset( $input['calendar_page_size'] ) ? max( 1, absint( $input['calendar_page_size'] ) ) : 20;

return $options;
}

/**
 * Renders settings page.
 */
public function render_page(): void {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You do not have permission to access this page.', 'smooth-booking' ) );
}

?>
<div class="wrap">
<h1><?php echo esc_html__( 'Smooth Booking Settings', 'smooth-booking' ); ?></h1>
<form action="options.php" method="post">
<?php
settings_fields( 'smooth_booking' );
do_settings_sections( 'smooth-booking' );
submit_button();
?>
</form>
</div>
<?php
}

/**
 * Renders date format field.
 */
public function render_date_format_field(): void {
$options = get_option( 'smooth_booking_options', [] );
$value   = isset( $options['date_format'] ) ? $options['date_format'] : 'Y-m-d H:i';
?>
<input type="text" id="sb_date_format" name="smooth_booking_options[date_format]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
<p class="description"><?php esc_html_e( 'PHP date format used across the calendar.', 'smooth-booking' ); ?></p>
<?php
}

/**
 * Renders employee field.
 */
public function render_employee_field(): void {
$options = get_option( 'smooth_booking_options', [] );
$value   = isset( $options['default_employee'] ) ? (int) $options['default_employee'] : 0;
?>
<input type="number" id="sb_default_employee" name="smooth_booking_options[default_employee]" value="<?php echo esc_attr( $value ); ?>" />
<p class="description"><?php esc_html_e( 'Employee ID used when no selection is made in the calendar filter.', 'smooth-booking' ); ?></p>
<?php
}

/**
 * Renders page size field.
 */
public function render_page_size_field(): void {
$options = get_option( 'smooth_booking_options', [] );
$value   = isset( $options['calendar_page_size'] ) ? (int) $options['calendar_page_size'] : 20;
?>
<input type="number" id="sb_page_size" min="1" max="200" name="smooth_booking_options[calendar_page_size]" value="<?php echo esc_attr( $value ); ?>" />
<p class="description"><?php esc_html_e( 'Maximum number of events fetched per request.', 'smooth-booking' ); ?></p>
<?php
}
}
