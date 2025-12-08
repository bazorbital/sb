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

    public function test_update_preserves_missing_fields_from_existing_record(): void {
        $existing_row = [
            'booking_id'      => 10,
            'service_id'      => 2,
            'service_name'    => 'Massage',
            'service_background_color' => '#000000',
            'service_text_color' => '#ffffff',
            'employee_id'     => 5,
            'employee_name'   => 'Alex',
            'customer_id'     => 7,
            'customer_account_name' => 'Acme Inc.',
            'customer_first_name' => 'Jane',
            'customer_last_name' => 'Doe',
            'customer_phone'  => '+3611',
            'customer_email'  => 'jane@example.com',
            'scheduled_start' => '2024-09-10 10:00:00',
            'scheduled_end'   => '2024-09-10 11:00:00',
            'status'          => 'confirmed',
            'payment_status'  => 'paid',
            'total_amount'    => '50.00',
            'currency'        => 'HUF',
            'notes'           => 'Keep details',
            'internal_note'   => 'Internal',
            'should_notify'   => 1,
            'is_recurring'    => 0,
            'created_at'      => '2024-09-01 10:00:00',
            'updated_at'      => '2024-09-01 10:00:00',
            'is_deleted'      => 0,
        ];

        $repository = new class( $existing_row ) implements AppointmentRepositoryInterface {
            public array $updated = [];
            private array $row;

            public function __construct( array $row ) {
                $this->row = $row;
            }

            public function paginate( array $args ): array {
                return [ 'appointments' => [], 'total' => 0 ];
            }

            public function find_with_deleted( int $appointment_id ): ?Appointment {
                return Appointment::from_row( $this->row );
            }

            public function create( array $data ) {
                return new WP_Error( 'not_implemented', 'create not used' );
            }

            public function update( int $appointment_id, array $data ) {
                $this->updated = $data;

                $row                         = $this->row;
                $row['employee_id']          = $data['provider_id'];
                $row['service_id']           = $data['service_id'];
                $row['customer_id']          = $data['customer_id'];
                $row['customer_email']       = $data['customer_email'];
                $row['customer_phone']       = $data['customer_phone'];
                $row['notes']                = $data['notes'];
                $row['internal_note']        = $data['internal_note'];
                $row['status']               = $data['status'];
                $row['payment_status']       = $data['payment_status'];
                $row['scheduled_start']      = $data['scheduled_start']->format( 'Y-m-d H:i:s' );
                $row['scheduled_end']        = $data['scheduled_end']->format( 'Y-m-d H:i:s' );
                $row['should_notify']        = $data['should_notify'];
                $row['is_recurring']         = $data['is_recurring'];
                $row['updated_at']           = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );

                return Appointment::from_row( $row );
            }

            public function soft_delete( int $appointment_id ): bool {
                return true;
            }

            public function restore( int $appointment_id ): bool {
                return true;
            }
        };

        $employee_service = $this->createMock( EmployeeService::class );
        $employee_service->method( 'get_employee' )->willReturn( $this->createConfiguredMock( Employee::class, [ 'get_id' => 9 ] ) );

        $service_service = $this->createMock( ServiceService::class );
        $service_service->method( 'get_service' )->willReturn( $this->createConfiguredMock( Service::class, [ 'get_id' => 2 ] ) );

        $customer_service = $this->createMock( CustomerService::class );
        $customer_service->method( 'get_customer' )->willReturn( new \stdClass() );

        $service = new AppointmentService(
            $repository,
            $employee_service,
            $service_service,
            $customer_service,
            new Logger( 'test' )
        );

        $result = $service->update_appointment(
            10,
            [
                'provider_id'       => 9,
                'service_id'        => 2,
                'appointment_date'  => '2024-09-12',
                'appointment_start' => '12:00',
                'appointment_end'   => '13:00',
            ]
        );

        $this->assertInstanceOf( Appointment::class, $result );
        $this->assertSame( 7, $repository->updated['customer_id'] );
        $this->assertSame( 'jane@example.com', $repository->updated['customer_email'] );
        $this->assertSame( '+3611', $repository->updated['customer_phone'] );
        $this->assertSame( 'Keep details', $repository->updated['notes'] );
        $this->assertSame( 'Internal', $repository->updated['internal_note'] );
        $this->assertSame( 'confirmed', $repository->updated['status'] );
        $this->assertSame( 'paid', $repository->updated['payment_status'] );
        $this->assertTrue( $repository->updated['should_notify'] );
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
