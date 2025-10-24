<?php

namespace SmoothBooking\Tests\Unit\Domain\Appointments;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Appointments\Appointment;

class AppointmentTest extends TestCase {
    public function test_from_row_and_to_array(): void {
        $row = [
            'booking_id'            => 12,
            'service_id'            => 5,
            'service_name'          => 'Massage',
            'employee_id'           => 3,
            'employee_name'         => 'Jane Doe',
            'customer_id'           => 9,
            'customer_account_name' => 'Acme Corp',
            'customer_first_name'   => 'John',
            'customer_last_name'    => 'Smith',
            'customer_phone'        => '+361234567',
            'customer_email'        => 'john@example.com',
            'scheduled_start'       => '2024-08-01 08:00:00',
            'scheduled_end'         => '2024-08-01 09:15:00',
            'status'                => 'confirmed',
            'payment_status'        => 'paid',
            'notes'                 => 'Bring documents',
            'internal_note'         => 'VIP',
            'total_amount'          => '150.00',
            'currency'              => 'EUR',
            'should_notify'         => 1,
            'is_recurring'          => 0,
            'created_at'            => '2024-07-01 12:00:00',
            'updated_at'            => '2024-07-01 12:30:00',
            'is_deleted'            => 0,
        ];

        $appointment = Appointment::from_row( $row );

        $this->assertSame( 12, $appointment->get_id() );
        $this->assertSame( 5, $appointment->get_service_id() );
        $this->assertSame( 'Massage', $appointment->get_service_name() );
        $this->assertSame( 'Jane Doe', $appointment->get_employee_name() );
        $this->assertSame( 'john@example.com', $appointment->get_customer_email() );
        $this->assertSame( 75, $appointment->get_duration_minutes() );
        $this->assertTrue( $appointment->should_notify() );

        $payload = $appointment->to_array();

        $this->assertSame( 'confirmed', $payload['status'] );
        $this->assertSame( 'paid', $payload['payment_status'] );
        $this->assertSame( 75, $payload['duration_minutes'] );
        $this->assertSame( 'VIP', $payload['internal_note'] );
    }
}
