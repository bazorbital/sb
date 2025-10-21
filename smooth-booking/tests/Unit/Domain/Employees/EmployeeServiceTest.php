<?php

namespace SmoothBooking\Tests\Unit\Domain\Employees;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Employees\EmployeeService
 */
class EmployeeServiceTest extends TestCase {
    public function test_create_employee_requires_name(): void {
        $repository = new InMemoryEmployeeRepository();
        $service    = new EmployeeService( $repository, new Logger( 'test' ) );

        $result = $service->create_employee( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_employee_missing_name', $result->get_error_code() );
    }

    public function test_create_employee_sanitizes_fields(): void {
        $repository = new InMemoryEmployeeRepository();
        $service    = new EmployeeService( $repository, new Logger( 'test' ) );

        $result = $service->create_employee(
            [
                'name'             => '  Jane  ',
                'email'            => '  jane@example.com ',
                'phone'            => ' +361234 ',
                'specialization'   => 'Stylist ',
                'available_online' => '1',
            ]
        );

        $this->assertInstanceOf( Employee::class, $result );
        $this->assertSame( 'Jane', $result->get_name() );
        $this->assertSame( 'jane@example.com', $result->get_email() );
        $this->assertSame( '+361234', $result->get_phone() );
        $this->assertSame( 'Stylist', $result->get_specialization() );
        $this->assertTrue( $result->is_available_online() );
    }
}

/**
 * Simple in-memory repository for testing.
 */
class InMemoryEmployeeRepository implements EmployeeRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, Employee> */
    private array $employees = [];

    public function all(): array {
        return array_values( $this->employees );
    }

    public function find( int $employee_id ) {
        return $this->employees[ $employee_id ] ?? null;
    }

    public function create( array $data ) {
        $employee = new Employee(
            $this->counter++,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['specialization'],
            (bool) $data['available_online'],
            null,
            null
        );

        $this->employees[ $employee->get_id() ] = $employee;

        return $employee;
    }

    public function update( int $employee_id, array $data ) {
        $employee = new Employee(
            $employee_id,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['specialization'],
            (bool) $data['available_online'],
            null,
            null
        );

        $this->employees[ $employee_id ] = $employee;

        return $employee;
    }

    public function soft_delete( int $employee_id ) {
        unset( $this->employees[ $employee_id ] );

        return true;
    }
}
