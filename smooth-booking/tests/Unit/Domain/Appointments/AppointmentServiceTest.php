<?php

namespace SmoothBooking\Tests\Unit\Domain\Appointments;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentRepositoryInterface;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

class AppointmentServiceTest extends TestCase {
    public function test_create_appointment_requires_provider(): void {
        $service = $this->make_service();

        $result = $service->create_appointment( [
            'provider_id'      => 0,
            'service_id'       => 1,
            'appointment_date' => '2024-08-01',
            'appointment_start'=> '09:00',
            'appointment_end'  => '10:00',
        ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_invalid_provider', $result->get_error_code() );
    }

    public function test_create_appointment_successful(): void {
        $repository = new class() implements AppointmentRepositoryInterface {
            public array $created = [];

            public function paginate( array $args ): array {
                return [ 'appointments' => [], 'total' => 0 ];
            }

            public function find_with_deleted( int $appointment_id ): ?Appointment {
                return null;
            }

            public function create( array $data ) {
                $this->created = $data;

                $row = [
                    'booking_id'      => 1,
                    'service_id'      => $data['service_id'],
                    'service_name'    => 'Consultation',
                    'employee_id'     => $data['provider_id'],
                    'employee_name'   => 'Dr. Test',
                    'customer_id'     => $data['customer_id'],
                    'scheduled_start' => $data['scheduled_start']->format( 'Y-m-d H:i:s' ),
                    'scheduled_end'   => $data['scheduled_end']->format( 'Y-m-d H:i:s' ),
                    'status'          => $data['status'],
                    'payment_status'  => $data['payment_status'],
                    'notes'           => $data['notes'],
                    'internal_note'   => $data['internal_note'],
                    'total_amount'    => '0.00',
                    'currency'        => 'HUF',
                    'should_notify'   => $data['should_notify'],
                    'is_recurring'    => $data['is_recurring'],
                    'customer_email'  => $data['customer_email'],
                    'customer_phone'  => $data['customer_phone'],
                    'created_at'      => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
                    'updated_at'      => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
                    'is_deleted'      => 0,
                ];

                return Appointment::from_row( $row );
            }

            public function update( int $appointment_id, array $data ) {
                return new WP_Error( 'not_implemented', 'not used' );
            }

            public function soft_delete( int $appointment_id ): bool {
                return true;
            }

            public function restore( int $appointment_id ): bool {
                return true;
            }
        };

        $employee_service = $this->createMock( EmployeeService::class );
        $employee = $this->createConfiguredMock( Employee::class, [ 'get_id' => 2 ] );
        $employee_service->method( 'get_employee' )->willReturn( $employee );

        $service_service = $this->createMock( ServiceService::class );
        $service_obj = $this->createConfiguredMock( Service::class, [ 'get_id' => 3 ] );
        $service_service->method( 'get_service' )->willReturn( $service_obj );

        $customer_service = $this->createMock( CustomerService::class );
        $customer_service->method( 'get_customer' )->willReturn( new \stdClass() );

        $service = new AppointmentService(
            $repository,
            $employee_service,
            $service_service,
            $customer_service,
            new Logger( 'test' )
        );

        $result = $service->create_appointment(
            [
                'provider_id'        => 2,
                'service_id'         => 3,
                'customer_id'        => 5,
                'appointment_date'   => '2024-08-15',
                'appointment_start'  => '08:00',
                'appointment_end'    => '09:00',
                'notes'              => 'Note',
                'internal_note'      => 'Internal',
                'status'             => 'confirmed',
                'payment_status'     => 'paid',
                'send_notifications' => true,
                'is_recurring'       => false,
                'customer_email'     => 'client@example.com',
                'customer_phone'     => '+361234',
            ]
        );

        $this->assertInstanceOf( Appointment::class, $result );
        $this->assertSame( 2, $repository->created['provider_id'] );
        $this->assertSame( 'confirmed', $repository->created['status'] );
        $this->assertTrue( $repository->created['should_notify'] );
    }

    private function make_service(): AppointmentService {
        $repository       = $this->createMock( AppointmentRepositoryInterface::class );
        $employee_service = $this->createMock( EmployeeService::class );
        $service_service  = $this->createMock( ServiceService::class );
        $customer_service = $this->createMock( CustomerService::class );

        $employee_service->method( 'get_employee' )->willReturn( new WP_Error( 'not_found' ) );
        $service_service->method( 'get_service' )->willReturn( new WP_Error( 'not_found' ) );
        $customer_service->method( 'get_customer' )->willReturn( new WP_Error( 'not_found' ) );

        return new AppointmentService( $repository, $employee_service, $service_service, $customer_service, new Logger( 'test' ) );
    }
}
