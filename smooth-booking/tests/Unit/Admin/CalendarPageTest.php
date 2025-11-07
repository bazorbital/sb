<?php

namespace SmoothBooking\Tests\Unit\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SmoothBooking\Admin\CalendarPage;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;

/**
 * @covers \SmoothBooking\Admin\CalendarPage
 */
class CalendarPageTest extends TestCase {
    public function test_unique_employees_removes_duplicates(): void {
        $page = $this->make_page();

        $first     = $this->createConfiguredMock( Employee::class, [ 'get_id' => 5 ] );
        $second    = $this->createConfiguredMock( Employee::class, [ 'get_id' => 9 ] );
        $duplicate = $this->createConfiguredMock( Employee::class, [ 'get_id' => 5 ] );

        /** @var Employee[] $result */
        $result = $this->invoke_private_method( $page, 'unique_employees', [ [ $first, $duplicate, $second ] ] );

        $this->assertCount( 2, $result );
        $this->assertSame( [ 5, 9 ], array_map( static fn ( Employee $employee ): int => $employee->get_id(), $result ) );
    }

    public function test_build_events_maps_service_colour_and_customer(): void {
        $page = $this->make_page();
        $timezone = new DateTimeZone( 'Europe/Budapest' );

        $appointment = $this->createConfiguredMock(
            Appointment::class,
            [
                'get_id'                     => 21,
                'get_employee_id'            => 7,
                'get_service_name'           => 'Haircut',
                'get_service_color'          => 'ff0066',
                'get_employee_name'          => 'Jane Doe',
                'get_status'                 => 'confirmed',
                'get_customer_first_name'    => 'Alex',
                'get_customer_last_name'     => 'Taylor',
                'get_customer_account_name'  => null,
                'get_scheduled_start'        => new DateTimeImmutable( '2024-04-12 09:00:00', new DateTimeZone( 'UTC' ) ),
                'get_scheduled_end'          => new DateTimeImmutable( '2024-04-12 10:00:00', new DateTimeZone( 'UTC' ) ),
            ]
        );

        $events = $this->invoke_private_method( $page, 'build_events', [ [ $appointment ], $timezone ] );

        $this->assertCount( 1, $events );
        $event = $events[0];

        $this->assertSame( 7, $event['resourceId'] );
        $this->assertSame( '#ff0066', $event['color'] );
        $this->assertSame( 'Haircut', $event['title'] );
        $this->assertSame( '2024-04-12 11:00', $event['end'] );
        $this->assertSame( 'Alex Taylor', $event['extendedProps']['customer'] );
    }

    public function test_format_slot_duration_builds_hours_and_minutes(): void {
        $page = $this->make_page();

        $result = $this->invoke_private_method( $page, 'format_slot_duration', [ 95 ] );

        $this->assertSame( '01:35', $result );
    }

    private function make_page(): CalendarPage {
        return new CalendarPage(
            $this->createMock( CalendarService::class ),
            $this->createMock( LocationService::class ),
            $this->createMock( Logger::class )
        );
    }

    /**
     * @param CalendarPage $page   Instance under test.
     * @param string       $method Private method name.
     * @param array        $args   Arguments to pass.
     *
     * @return mixed
     */
    private function invoke_private_method( CalendarPage $page, string $method, array $args = [] ) {
        $reflection = new ReflectionClass( $page );
        $target     = $reflection->getMethod( $method );
        $target->setAccessible( true );

        return $target->invokeArgs( $page, $args );
    }
}
