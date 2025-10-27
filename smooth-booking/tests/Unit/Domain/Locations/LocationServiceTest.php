<?php

namespace SmoothBooking\Tests\Unit\Domain\Locations;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationRepositoryInterface;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Locations\LocationService
 */
class LocationServiceTest extends TestCase {
    public function test_create_location_requires_name(): void {
        $service = $this->createService();

        $result = $service->create_location( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_location_invalid_name', $result->get_error_code() );
    }

    public function test_create_location_validates_email(): void {
        $service = $this->createService();

        $result = $service->create_location(
            [
                'name'       => 'Downtown Office',
                'base_email' => 'not-an-email',
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_location_invalid_email', $result->get_error_code() );
    }

    public function test_create_location_persists_data(): void {
        $service = $this->createService();

        $result = $service->create_location(
            [
                'name'             => 'Studio',
                'address'          => 'Main street 1',
                'phone'            => '+36123456',
                'base_email'       => 'info@example.com',
                'website'          => 'https://example.com',
                'industry_id'      => 11,
                'is_event_location'=> true,
                'company_name'     => 'Studio LLC',
                'company_address'  => 'HQ street 2',
                'company_phone'    => '+36111222',
            ]
        );

        $this->assertInstanceOf( Location::class, $result );
        $this->assertSame( 'Studio', $result->get_name() );
        $this->assertSame( 'Main street 1', $result->get_address() );
        $this->assertSame( '+36123456', $result->get_phone() );
        $this->assertSame( 'info@example.com', $result->get_base_email() );
        $this->assertSame( 'https://example.com', $result->get_website() );
        $this->assertSame( 11, $result->get_industry_id() );
        $this->assertTrue( $result->is_event_location() );
        $this->assertSame( 'Studio LLC', $result->get_company_name() );
    }

    public function test_update_location_requires_existing(): void {
        $service = $this->createService();

        $result = $service->update_location( 99, [ 'name' => 'Ghost' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_location_not_found', $result->get_error_code() );
    }

    public function test_restore_location_requires_deleted_state(): void {
        $repository = new InMemoryLocationRepository();
        $logger     = new Logger( 'test' );
        $service    = new LocationService( $repository, $logger );

        $created = $service->create_location( [ 'name' => 'Primary' ] );
        $this->assertInstanceOf( Location::class, $created );

        $restore = $service->restore_location( $created->get_id() );

        $this->assertInstanceOf( WP_Error::class, $restore );
        $this->assertSame( 'smooth_booking_location_not_deleted', $restore->get_error_code() );
    }

    private function createService(): LocationService {
        return new LocationService( new InMemoryLocationRepository(), new Logger( 'test' ) );
    }
}

class InMemoryLocationRepository implements LocationRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, array<string, mixed>> */
    private array $records = [];

    public function list_active(): array {
        return $this->all();
    }

    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        $locations = [];

        foreach ( $this->records as $id => $row ) {
            $is_deleted = (int) $row['is_deleted'] === 1;

            if ( $only_deleted && ! $is_deleted ) {
                continue;
            }

            if ( ! $include_deleted && $is_deleted ) {
                continue;
            }

            $locations[] = $this->hydrate( $id, $row );
        }

        return $locations;
    }

    public function find( int $location_id ): ?Location {
        if ( ! isset( $this->records[ $location_id ] ) ) {
            return null;
        }

        $row = $this->records[ $location_id ];

        if ( (int) $row['is_deleted'] === 1 ) {
            return null;
        }

        return $this->hydrate( $location_id, $row );
    }

    public function find_with_deleted( int $location_id ): ?Location {
        if ( ! isset( $this->records[ $location_id ] ) ) {
            return null;
        }

        return $this->hydrate( $location_id, $this->records[ $location_id ] );
    }

    public function create( array $data ) {
        $id                  = $this->counter++;
        $data['is_deleted']  = 0;
        $data['created_at']  = gmdate( 'Y-m-d H:i:s' );
        $data['updated_at']  = $data['created_at'];
        $this->records[ $id ] = $data;

        return $this->find_with_deleted( $id );
    }

    public function update( int $location_id, array $data ) {
        if ( ! isset( $this->records[ $location_id ] ) ) {
            return new WP_Error( 'missing_location', 'Missing location' );
        }

        $data['is_deleted'] = $this->records[ $location_id ]['is_deleted'];
        $data['created_at'] = $this->records[ $location_id ]['created_at'];
        $data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
        $this->records[ $location_id ] = $data;

        return $this->find_with_deleted( $location_id );
    }

    public function soft_delete( int $location_id ) {
        if ( ! isset( $this->records[ $location_id ] ) ) {
            return new WP_Error( 'missing_location', 'Missing location' );
        }

        $this->records[ $location_id ]['is_deleted'] = 1;

        return true;
    }

    public function restore( int $location_id ) {
        if ( ! isset( $this->records[ $location_id ] ) ) {
            return new WP_Error( 'missing_location', 'Missing location' );
        }

        $this->records[ $location_id ]['is_deleted'] = 0;

        return true;
    }

    private function hydrate( int $id, array $row ): Location {
        return new Location(
            $id,
            $row['name'],
            $row['profile_image_id'] ?? null,
            $row['address'] ?? null,
            $row['phone'] ?? null,
            $row['base_email'] ?? null,
            $row['website'] ?? null,
            (int) ( $row['industry_id'] ?? 0 ),
            (int) ( $row['is_event_location'] ?? 0 ) === 1,
            (int) ( $row['is_deleted'] ?? 0 ) === 1,
            $row['company_name'] ?? null,
            $row['company_address'] ?? null,
            $row['company_phone'] ?? null,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}
