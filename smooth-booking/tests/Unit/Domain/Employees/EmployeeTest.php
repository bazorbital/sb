<?php

namespace SmoothBooking\Tests\Unit\Domain\Employees;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Employees\Employee;

/**
 * @covers \SmoothBooking\Domain\Employees\Employee
 */
class EmployeeTest extends TestCase {
    public function test_to_array_contains_expected_keys(): void {
        $employee = new Employee(
            5,
            'Jane Doe',
            'jane@example.com',
            '+3612345678',
            'Hair Stylist',
            true,
            '2024-01-01 10:00:00',
            '2024-01-02 12:00:00'
        );

        $data = $employee->to_array();

        $this->assertSame( 5, $data['id'] );
        $this->assertSame( 'Jane Doe', $data['name'] );
        $this->assertSame( 'jane@example.com', $data['email'] );
        $this->assertSame( '+3612345678', $data['phone'] );
        $this->assertSame( 'Hair Stylist', $data['specialization'] );
        $this->assertTrue( $data['available_online'] );
        $this->assertSame( '2024-01-01 10:00:00', $data['created_at'] );
        $this->assertSame( '2024-01-02 12:00:00', $data['updated_at'] );
    }

    public function test_from_row_handles_nullables(): void {
        $row      = [
            'employee_id'    => '9',
            'name'           => 'John Doe',
            'email'          => '',
            'phone'          => '',
            'specialization' => '',
            'available_online' => '0',
        ];
        $employee = Employee::from_row( $row );

        $this->assertNull( $employee->get_email() );
        $this->assertNull( $employee->get_phone() );
        $this->assertNull( $employee->get_specialization() );
        $this->assertFalse( $employee->is_available_online() );
    }
}
