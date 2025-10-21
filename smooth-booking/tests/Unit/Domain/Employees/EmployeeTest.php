<?php

namespace SmoothBooking\Tests\Unit\Domain\Employees;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeCategory;

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
            42,
            '#ff9933',
            'public',
            '2024-01-01 10:00:00',
            '2024-01-02 12:00:00',
            [ new EmployeeCategory( 9, 'Orthodontist', 'orthodontist' ) ]
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
        $this->assertSame( 42, $data['profile_image_id'] );
        $this->assertSame( '#ff9933', $data['default_color'] );
        $this->assertSame( 'public', $data['visibility'] );
        $this->assertCount( 1, $data['categories'] );
        $this->assertSame( 'Orthodontist', $data['categories'][0]['name'] );
    }

    public function test_from_row_handles_nullables(): void {
        $row      = [
            'employee_id'    => '9',
            'name'           => 'John Doe',
            'email'          => '',
            'phone'          => '',
            'specialization' => '',
            'available_online' => '0',
            'profile_image_id' => '0',
            'default_color'    => '',
            'visibility'       => 'archived',
        ];
        $employee = Employee::from_row( $row );

        $this->assertNull( $employee->get_email() );
        $this->assertNull( $employee->get_phone() );
        $this->assertNull( $employee->get_specialization() );
        $this->assertFalse( $employee->is_available_online() );
        $this->assertNull( $employee->get_profile_image_id() );
        $this->assertNull( $employee->get_default_color() );
        $this->assertSame( 'archived', $employee->get_visibility() );
    }
}
