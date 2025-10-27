<?php

namespace SmoothBooking\Tests\Unit\Domain\BusinessHours;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\BusinessHours\BusinessHoursRepositoryInterface;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\BusinessHours\BusinessHoursService
 */
class BusinessHoursServiceTest extends TestCase {
    public function test_save_location_hours_requires_valid_location(): void {
        $service = $this->createService();

        $result = $service->save_location_hours( 999, [] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_location_not_found', $result->get_error_code() );
    }

    public function test_save_location_hours_validates_time_order(): void {
        $repository = new InMemoryBusinessHoursRepository();
        $locations  = new InMemoryLocationRepository();
        $locations->add_location( new Location( 5, 'HQ', null, true, false ) );

        $service = new BusinessHoursService( $repository, $locations, new Logger( 'test' ) );

        $result = $service->save_location_hours(
            5,
            [
                1 => [
                    'open'  => '12:00',
                    'close' => '11:00',
                ],
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_business_hours_order', $result->get_error_code() );
    }

    public function test_save_location_hours_persists_normalised_payload(): void {
        $repository = new InMemoryBusinessHoursRepository();
        $locations  = new InMemoryLocationRepository();
        $locations->add_location( new Location( 7, 'Studio', null, true, false ) );

        $service = new BusinessHoursService( $repository, $locations, new Logger( 'test' ) );

        $result = $service->save_location_hours(
            7,
            [
                1 => [
                    'open'  => '09:00',
                    'close' => '17:00',
                ],
                2 => [
                    'is_closed' => '1',
                ],
            ]
        );

        $this->assertTrue( $result );
        $this->assertArrayHasKey( 7, $repository->saved );

        $saved = $repository->saved[7];
        $this->assertCount( 7, $saved );

        $monday = $saved[0];
        $this->assertSame( 1, $monday['day'] );
        $this->assertSame( '09:00:00', $monday['open_time'] );
        $this->assertSame( '17:00:00', $monday['close_time'] );
        $this->assertFalse( $monday['is_closed'] );

        $tuesday = $saved[1];
        $this->assertSame( 2, $tuesday['day'] );
        $this->assertNull( $tuesday['open_time'] );
        $this->assertNull( $tuesday['close_time'] );
        $this->assertTrue( $tuesday['is_closed'] );
    }

    private function createService(): BusinessHoursService {
        $repository = new InMemoryBusinessHoursRepository();
        $locations  = new InMemoryLocationRepository();

        return new BusinessHoursService( $repository, $locations, new Logger( 'test' ) );
    }
}

class InMemoryBusinessHoursRepository implements BusinessHoursRepositoryInterface {
    /** @var array<int, array<int, array{day:int, open_time:?string, close_time:?string, is_closed:bool}>> */
    public array $saved = [];

    public function get_for_location( int $location_id ): array {
        return [];
    }

    public function save_for_location( int $location_id, array $hours ) {
        $this->saved[ $location_id ] = $hours;

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
