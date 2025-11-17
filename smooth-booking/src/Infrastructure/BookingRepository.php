<?php
/**
 * Booking repository using wpdb/WP_Query.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Infrastructure;

use WP_Post;
use WP_Query;

/**
 * Provides read access to booking data.
 */
class BookingRepository {
/**
 * Retrieves bookings within date range.
 *
 * @param string      $start       Start datetime (Y-m-d H:i:s).
 * @param string      $end         End datetime.
 * @param int|null    $employee_id Employee filter.
 * @param int|null    $limit       Limit results.
 * @return array<int, WP_Post> List of booking posts.
 */
public function get_bookings( string $start, string $end, ?int $employee_id = null, ?int $limit = null ): array {
$meta_query = [
'relation' => 'AND',
[
'key'     => 'sb_start',
'value'   => $start,
'compare' => '>=',
'type'    => 'DATETIME',
],
[
'key'     => 'sb_end',
'value'   => $end,
'compare' => '<=',
'type'    => 'DATETIME',
],
];

if ( null !== $employee_id ) {
$meta_query[] = [
'key'     => 'sb_employee_id',
'value'   => $employee_id,
'compare' => '=',
];
}

$args = [
'post_type'      => 'sb_booking',
'post_status'    => 'publish',
'posts_per_page' => $limit ?? -1,
'orderby'        => 'meta_value',
'order'          => 'ASC',
'meta_key'       => 'sb_start',
'meta_query'     => $meta_query,
'no_found_rows'  => true,
];

$query = new WP_Query( $args );

return $query->posts;
}

/**
 * Returns next booking.
 *
 * @return WP_Post|null
 */
public function get_next_booking(): ?WP_Post {
$args  = [
'post_type'      => 'sb_booking',
'post_status'    => 'publish',
'posts_per_page' => 1,
'orderby'        => 'meta_value',
'order'          => 'ASC',
'meta_key'       => 'sb_start',
'meta_query'     => [
[
'key'     => 'sb_start',
'value'   => current_time( 'mysql' ),
'compare' => '>=',
'type'    => 'DATETIME',
],
],
'no_found_rows'  => true,
];
$query = new WP_Query( $args );

return $query->posts[0] ?? null;
}
}
