<?php
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Calendar\CalendarService;

require_once dirname( __DIR__ ) . '/bootstrap.php';

class FakeRepository extends SmoothBooking\Infrastructure\BookingRepository {
public array $posts = [];

public function get_bookings( string $start, string $end, ?int $employee_id = null, ?int $limit = null ): array {
return $this->posts;
}

public function get_next_booking(): ?WP_Post {
return $this->posts[0] ?? null;
}
}

class CalendarServiceTest extends TestCase {
public function test_formats_events(): void {
$repo = new FakeRepository();
$post       = new WP_Post();
$post->ID   = 1;
$post->post_title   = 'Demo Event';
$post->post_content = 'Content';

$GLOBALS['sb_test_meta'][1] = [
'sb_start'        => '2024-01-01 10:00:00',
'sb_end'          => '2024-01-01 11:00:00',
'sb_employee_id'  => 5,
'sb_employee_name'=> 'Alice',
'sb_color'        => '#ff0000',
];

$repo->posts = [ $post ];

$service = new CalendarService( $repo );
$events  = $service->get_events( '2024-01-01 00:00:00', '2024-01-02 00:00:00' );

$this->assertSame( 'Demo Event', $events[0]['name'] );
$this->assertSame( '#ff0000', $events[0]['color'] );
$this->assertSame( 5, $events[0]['employee'] );
}
}
