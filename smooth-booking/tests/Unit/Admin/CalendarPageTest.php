<?php

namespace SmoothBooking\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SmoothBooking\Admin\CalendarPage;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;

/**
 * @covers \SmoothBooking\Admin\CalendarPage
 */
class CalendarPageTest extends TestCase {
    public function test_unique_services_removes_duplicates(): void {
        $page = $this->make_page();

        $first     = $this->createConfiguredMock( Service::class, [ 'get_id' => 1 ] );
        $second    = $this->createConfiguredMock( Service::class, [ 'get_id' => 2 ] );
        $duplicate = $this->createConfiguredMock( Service::class, [ 'get_id' => 1 ] );

        /** @var Service[] $result */
        $result = $this->invoke_private_method( $page, 'unique_services', [ [ $first, $duplicate, $second ] ] );

        $this->assertCount( 2, $result );
        $ids = array_map(
            static function ( Service $service ): int {
                return $service->get_id();
            },
            $result
        );
        $this->assertSame( [ 1, 2 ], $ids );
    }

    public function test_unique_employees_removes_duplicates(): void {
        $page = $this->make_page();

        $first     = $this->createConfiguredMock( Employee::class, [ 'get_id' => 5 ] );
        $second    = $this->createConfiguredMock( Employee::class, [ 'get_id' => 9 ] );
        $duplicate = $this->createConfiguredMock( Employee::class, [ 'get_id' => 5 ] );

        /** @var Employee[] $result */
        $result = $this->invoke_private_method( $page, 'unique_employees', [ [ $first, $duplicate, $second ] ] );

        $this->assertCount( 2, $result );
        $ids = array_map(
            static function ( Employee $employee ): int {
                return $employee->get_id();
            },
            $result
        );
        $this->assertSame( [ 5, 9 ], $ids );
    }

    private function make_page(): CalendarPage {
        return new CalendarPage(
            $this->createMock( CalendarService::class ),
            $this->createMock( LocationService::class ),
            $this->createMock( EmployeeService::class ),
            $this->createMock( ServiceService::class ),
            $this->createMock( CustomerService::class ),
            $this->createMock( GeneralSettings::class ),
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
