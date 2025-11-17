<?php
/**
 * Calendar service responsible for transforming booking data.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Domain\Calendar;

use SmoothBooking\Infrastructure\BookingRepository;
use WP_Post;

/**
 * Calendar service.
 */
class CalendarService {
/**
 * Repository instance.
 *
 * @var BookingRepository
 */
private BookingRepository $repository;

/**
 * Constructor.
 *
 * @param BookingRepository $repository Booking repository.
 */
public function __construct( BookingRepository $repository ) {
$this->repository = $repository;
}

/**
 * Returns events for vkurko/calendar integration.
 *
 * @param string   $start       Range start.
 * @param string   $end         Range end.
 * @param int|null $employee_id Employee id.
 * @return array<int, array<string, mixed>>
 */
public function get_events( string $start, string $end, ?int $employee_id = null ): array {
$bookings = $this->repository->get_bookings( $start, $end, $employee_id );
$events   = [];

foreach ( $bookings as $booking ) {
$events[] = $this->format_booking( $booking );
}

return $events;
}

/**
 * Returns the next booking formatted for public output.
 *
 * @return array<string, mixed>|null
 */
public function get_next_booking(): ?array {
$next = $this->repository->get_next_booking();

if ( ! $next ) {
return null;
}

return $this->format_booking( $next );
}

/**
 * Formats booking into calendar event structure.
 *
 * @param WP_Post $booking Booking post.
 * @return array<string, mixed>
 */
private function format_booking( WP_Post $booking ): array {
$start      = get_post_meta( $booking->ID, 'sb_start', true );
$end        = get_post_meta( $booking->ID, 'sb_end', true );
$employee   = (int) get_post_meta( $booking->ID, 'sb_employee_id', true );
$employee_n = get_post_meta( $booking->ID, 'sb_employee_name', true );

return [
'id'        => $booking->ID,
'name'      => $booking->post_title,
'employee'  => $employee,
'employee_name' => $employee_n ? $employee_n : __( 'Unassigned', 'smooth-booking' ),
'body'      => apply_filters( 'the_content', $booking->post_content ),
'begin'     => $start,
'end'       => $end,
'url'       => get_edit_post_link( $booking->ID, '' ) ?: '',
'classes'   => [ 'sb-calendar-event' ],
'color'     => get_post_meta( $booking->ID, 'sb_color', true ) ?: '#2c89d9',
];
}
}
