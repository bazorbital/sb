<?php

declare(strict_types=1);

namespace SmoothBooking\Tests\Unit\Domain\Calendar;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use WP_Error;

class CalendarServiceTest extends TestCase {
    private function build_location(int $location_id = 5): Location {
        return new Location(
            $location_id,
            'HQ',
            null,
            null,
            null,
            null,
            null,
            0,
            'Europe/Budapest',
            false,
            false,
            null,
            null,
            null,
            null,
            null
        );
    }

    private function build_employee(int $id, array $location_ids): Employee {
        return new Employee(
            $id,
            'Employee ' . $id,
            null,
            null,
            null,
            true,
            null,
            null,
            'public',
            null,
            null,
            [],
            $location_ids,
            [],
            []
        );
    }

    public function test_get_daily_schedule_returns_calendar_payload(): void {
        $date         = new DateTimeImmutable('2025-05-01');
        $location     = $this->build_location();
        $employee     = $this->build_employee(11, [ $location->get_id() ]);
        $otherEmployee = $this->build_employee(99, [ 99 ]);

        $appointmentRow = [
            'booking_id'            => 42,
            'service_id'            => 7,
            'service_name'          => 'Consultation',
            'service_color'         => '#3366ff',
            'employee_id'           => $employee->get_id(),
            'employee_name'         => $employee->get_name(),
            'customer_id'           => 5,
            'customer_account_name' => 'Acme',
            'customer_first_name'   => 'Alex',
            'customer_last_name'    => 'Smith',
            'customer_phone'        => '+361234567',
            'customer_email'        => 'alex@example.com',
            'scheduled_start'       => '2025-05-01 09:00:00',
            'scheduled_end'         => '2025-05-01 09:30:00',
            'status'                => 'confirmed',
            'payment_status'        => 'paid',
            'total_amount'          => '0',
            'currency'              => 'HUF',
            'notes'                 => '',
            'internal_note'         => '',
            'should_notify'         => 1,
            'is_recurring'          => 0,
            'created_at'            => '2025-04-01 10:00:00',
            'updated_at'            => '2025-04-01 10:00:00',
            'is_deleted'            => 0,
        ];

        $appointment = Appointment::from_row($appointmentRow);

        /** @var AppointmentService|MockObject $appointmentService */
        $appointmentService = $this->createMock(AppointmentService::class);
        $appointmentService
            ->expects($this->once())
            ->method('get_appointments_for_employees')
            ->with(
                [ $employee->get_id() ],
                $this->isInstanceOf(DateTimeImmutable::class),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturn([ $appointment ]);

        /** @var EmployeeService|MockObject $employeeService */
        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService
            ->expects($this->once())
            ->method('list_employees')
            ->willReturn([ $employee, $otherEmployee ]);

        /** @var BusinessHoursService|MockObject $businessHours */
        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours
            ->expects($this->once())
            ->method('get_location_hours')
            ->with($location->get_id())
            ->willReturn([
                4 => [
                    'open'      => '09:00',
                    'close'     => '11:00',
                    'is_closed' => false,
                ],
            ]);

        /** @var LocationService|MockObject $locationService */
        $locationService = $this->createMock(LocationService::class);
        $locationService
            ->expects($this->once())
            ->method('get_location')
            ->with($location->get_id())
            ->willReturn($location);

        /** @var GeneralSettings|MockObject $settings */
        $settings = $this->createMock(GeneralSettings::class);
        $settings->method('get_time_slot_length')->willReturn(30);
        $settings
            ->expects($this->once())
            ->method('get_slots_for_range')
            ->willReturn(['09:00', '09:30', '10:00', '10:30']);

        $logger = $this->createMock(Logger::class);

        $service = new CalendarService(
            $appointmentService,
            $employeeService,
            $businessHours,
            $locationService,
            $settings,
            $logger
        );

        $result = $service->get_daily_schedule($location->get_id(), $date);

        $this->assertIsArray($result);
        $this->assertSame($location, $result['location']);
        $this->assertSame(30, $result['slot_length']);
        $this->assertCount(1, $result['employees']);
        $this->assertSame($employee->get_id(), $result['employees'][0]->get_id());
        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $result['slots']);
        $this->assertSame([ $appointment ], $result['appointments']);
        $this->assertFalse($result['is_closed']);
        $this->assertInstanceOf(DateTimeImmutable::class, $result['open']);
        $this->assertSame('09:00', $result['open']->format('H:i'));
        $this->assertSame('11:00', $result['close']->format('H:i'));
    }

    public function test_get_daily_schedule_returns_error_for_missing_location(): void {
        $date = new DateTimeImmutable('2025-05-01');

        $appointmentService = $this->createMock(AppointmentService::class);
        $employeeService    = $this->createMock(EmployeeService::class);
        $businessHours      = $this->createMock(BusinessHoursService::class);
        $locationService    = $this->createMock(LocationService::class);
        $settings           = $this->createMock(GeneralSettings::class);

        $locationService
            ->expects($this->once())
            ->method('get_location')
            ->willReturn(new WP_Error('missing', 'Missing location'));

        $logger = $this->createMock(Logger::class);

        $service = new CalendarService(
            $appointmentService,
            $employeeService,
            $businessHours,
            $locationService,
            $settings,
            $logger
        );

        $result = $service->get_daily_schedule(99, $date);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing', $result->get_error_code());
    }
}
