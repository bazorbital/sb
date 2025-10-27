<?php

namespace SmoothBooking\Tests\Unit\Domain\Holidays;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Holidays\HolidayRepositoryInterface;
use SmoothBooking\Domain\Holidays\HolidayService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Holidays\HolidayService
 */
class HolidayServiceTest extends TestCase {
    public function test_save_location_holiday_requires_valid_location(): void {
        $service = $this->createService();

        $result = $service->save_location_holiday( 0, [ 'start_date' => '2024-12-24', 'end_date' => '2024-12-25' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_location_not_found', $result->get_error_code() );
    }

    public function test_save_location_holiday_validates_dates(): void {
        $repositories = $this->createRepositories();
        $repositories['locations']->add_location( new Location( 3, 'HQ', null, true, false ) );
        $service = new HolidayService( $repositories['holidays'], $repositories['locations'], new Logger( 'test' ) );

        $result = $service->save_location_holiday( 3, [ 'start_date' => 'invalid', 'end_date' => '2024-01-02' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_holiday_invalid_date', $result->get_error_code() );
    }

    public function test_save_location_holiday_rejects_long_ranges(): void {
        $repositories = $this->createRepositories();
        $repositories['locations']->add_location( new Location( 7, 'Studio', null, true, false ) );
        $service = new HolidayService( $repositories['holidays'], $repositories['locations'], new Logger( 'test' ) );

        $result = $service->save_location_holiday( 7, [
            'start_date' => '2024-01-01',
            'end_date'   => '2025-12-31',
        ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_holiday_range_limit', $result->get_error_code() );
    }

    public function test_save_location_holiday_persists_payload(): void {
        $repositories = $this->createRepositories();
        $repositories['locations']->add_location( new Location( 5, 'Clinic', null, true, false ) );
        $service = new HolidayService( $repositories['holidays'], $repositories['locations'], new Logger( 'test' ) );

        $result = $service->save_location_holiday( 5, [
            'start_date'   => '2024-12-24',
            'end_date'     => '2024-12-25',
            'note'         => 'Christmas',
            'is_recurring' => true,
        ] );

        $this->assertTrue( $result );
        $this->assertArrayHasKey( 5, $repositories['holidays']->saved );
        $saved = $repositories['holidays']->saved[5];
        $this->assertCount( 2, $saved );
        $this->assertSame( '2024-12-24', $saved[0]['date'] );
        $this->assertSame( 'Christmas', $saved[0]['note'] );
        $this->assertTrue( $saved[0]['is_recurring'] );
        $this->assertSame( '2024-12-25', $saved[1]['date'] );
    }

    /**
     * @return array{holidays:InMemoryHolidayRepository,locations:InMemoryLocationRepository}
     */
    private function createRepositories(): array {
        return [
            'holidays'  => new InMemoryHolidayRepository(),
            'locations' => new InMemoryLocationRepository(),
        ];
    }

    private function createService(): HolidayService {
        $repositories = $this->createRepositories();

        return new HolidayService( $repositories['holidays'], $repositories['locations'], new Logger( 'test' ) );
    }
}

class InMemoryHolidayRepository implements HolidayRepositoryInterface {
    /** @var array<int, array<int, array{date:string,note:string,is_recurring:bool}>> */
    public array $saved = [];

    public function list_for_location( int $location_id ): array {
        return [];
    }

    public function save_range( int $location_id, array $holidays ) {
        $this->saved[ $location_id ] = $holidays;

        return true;
    }

    public function delete( int $holiday_id, int $location_id ) {
        return true;
    }
}

class InMemoryLocationRepository implements LocationRepositoryInterface {
    /** @var array<int, Location> */
    private array $locations = [];

    public function add_location( Location $location ): void {
        $this->locations[ $location->get_id() ] = $location;
    }

    public function list_active(): array {
        return array_values( $this->locations );
    }

    public function find( int $location_id ): ?Location {
        return $this->locations[ $location_id ] ?? null;
    }
}
