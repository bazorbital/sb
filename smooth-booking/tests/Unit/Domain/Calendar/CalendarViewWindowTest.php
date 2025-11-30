<?php

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;

class CalendarViewWindowTest extends TestCase {
    private function build_service(): CalendarService {
        $logger   = $this->createStub(Logger::class);
        $settings = $this->createStub(GeneralSettings::class);

        return new CalendarService(
            $this->createStub(AppointmentService::class),
            $this->createStub(EmployeeService::class),
            $this->createStub(BusinessHoursService::class),
            $this->createStub(LocationService::class),
            $settings,
            $logger
        );
    }

    public function test_build_view_window_applies_padding(): void {
        $service  = $this->build_service();
        $timezone = new DateTimeZone('Europe/Budapest');
        $open     = new DateTimeImmutable('2025-01-02 09:00:00', $timezone);
        $close    = new DateTimeImmutable('2025-01-02 17:00:00', $timezone);

        $window = $service->build_view_window($open, $close);

        $this->assertSame('07:00:00', $window['slotMinTime']);
        $this->assertSame('19:00:00', $window['slotMaxTime']);
        $this->assertSame('09:00:00', $window['scrollTime']);
    }

    public function test_build_view_window_clamps_to_calendar_day(): void {
        $service  = $this->build_service();
        $timezone = new DateTimeZone('Europe/Budapest');
        $open     = new DateTimeImmutable('2025-01-03 01:00:00', $timezone);
        $close    = new DateTimeImmutable('2025-01-03 02:00:00', $timezone);

        $window = $service->build_view_window($open, $close);

        $this->assertSame('00:00:00', $window['slotMinTime']);
        $this->assertSame('04:00:00', $window['slotMaxTime']);
    }
}
