<?php
/**
 * Registers custom post types.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Infrastructure;

use SmoothBooking\Contracts\Registrable;
use SmoothBooking\Plugin;

/**
 * Registers booking post type.
 */
class PostTypeRegistrar implements Registrable {
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
add_action( 'init', [ $this, 'register_post_type' ] );
}

/**
 * Registers the booking custom post type.
 */
public static function register_post_type(): void {
$labels = [
'name'               => _x( 'Bookings', 'post type general name', 'smooth-booking' ),
'singular_name'      => _x( 'Booking', 'post type singular name', 'smooth-booking' ),
'menu_name'          => _x( 'Bookings', 'admin menu', 'smooth-booking' ),
'name_admin_bar'     => _x( 'Booking', 'add new on admin bar', 'smooth-booking' ),
'add_new'            => _x( 'Add New', 'booking', 'smooth-booking' ),
'add_new_item'       => __( 'Add New Booking', 'smooth-booking' ),
'new_item'           => __( 'New Booking', 'smooth-booking' ),
'edit_item'          => __( 'Edit Booking', 'smooth-booking' ),
'view_item'          => __( 'View Booking', 'smooth-booking' ),
'all_items'          => __( 'All Bookings', 'smooth-booking' ),
'search_items'       => __( 'Search Bookings', 'smooth-booking' ),
'parent_item_colon'  => __( 'Parent Bookings:', 'smooth-booking' ),
'not_found'          => __( 'No bookings found.', 'smooth-booking' ),
'not_found_in_trash' => __( 'No bookings found in Trash.', 'smooth-booking' ),
];

$args = [
'labels'             => $labels,
'public'             => false,
'publicly_queryable' => false,
'show_ui'            => true,
'show_in_menu'       => false,
'show_in_rest'       => true,
'supports'           => [ 'title', 'editor', 'custom-fields' ],
'capability_type'    => 'post',
'has_archive'        => false,
'rewrite'            => false,
];

register_post_type( 'sb_booking', $args );
}
}
