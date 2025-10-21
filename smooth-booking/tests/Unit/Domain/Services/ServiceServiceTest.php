<?php

namespace SmoothBooking\Tests\Unit\Domain\Services;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceCategory;
use SmoothBooking\Domain\Services\ServiceCategoryRepositoryInterface;
use SmoothBooking\Domain\Services\ServiceRepositoryInterface;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Domain\Services\ServiceTag;
use SmoothBooking\Domain\Services\ServiceTagRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Services\ServiceService
 */
class ServiceServiceTest extends TestCase {
    public function test_create_service_requires_name(): void {
        $service = $this->createServiceService();

        $result = $service->create_service( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_service_invalid_name', $result->get_error_code() );
    }

    public function test_create_service_creates_terms_and_providers(): void {
        $service = $this->createServiceService();

        $result = $service->create_service(
            [
                'name'                 => 'Therapy Session',
                'price'                => '99.9',
                'visibility'           => 'private',
                'providers_preference' => 'most_expensive',
                'providers_random_tie' => 'enabled',
                'new_categories'       => 'Premium, Wellness',
                'new_tags'             => "Relaxation\nFocus",
                'providers'            => [
                    [ 'employee_id' => 1, 'order' => 0 ],
                    [ 'employee_id' => 2, 'order' => 3 ],
                ],
            ]
        );

        $this->assertInstanceOf( Service::class, $result );
        $this->assertSame( 'Therapy Session', $result->get_name() );
        $this->assertSame( 'private', $result->get_visibility() );
        $this->assertSame( 99.9, $result->get_price() );

        $category_names = array_map(
            static function ( ServiceCategory $category ): string {
                return $category->get_name();
            },
            $result->get_categories()
        );
        $this->assertCount( 2, $category_names );
        $this->assertContains( 'Premium', $category_names );
        $this->assertContains( 'Wellness', $category_names );

        $tag_names = array_map(
            static function ( ServiceTag $tag ): string {
                return $tag->get_name();
            },
            $result->get_tags()
        );
        $this->assertCount( 2, $tag_names );
        $this->assertContains( 'Relaxation', $tag_names );
        $this->assertContains( 'Focus', $tag_names );

        $providers = $result->get_providers();
        $this->assertCount( 2, $providers );
        $this->assertSame( 1, $providers[0]['employee_id'] );
        $this->assertSame( 1, $providers[0]['order'] );
        $this->assertSame( 2, $providers[1]['employee_id'] );
        $this->assertSame( 3, $providers[1]['order'] );
    }

    public function test_create_service_fails_for_unknown_provider(): void {
        $service = $this->createServiceService();

        $result = $service->create_service(
            [
                'name'      => 'Consultation',
                'providers' => [ [ 'employee_id' => 999, 'order' => 1 ] ],
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_service_invalid_provider', $result->get_error_code() );
    }

    private function createServiceService(): ServiceService {
        $category_repository = new InMemoryServiceCategoryRepository();
        $tag_repository      = new InMemoryServiceTagRepository();
        $service_repository  = new InMemoryServiceRepository( $category_repository, $tag_repository );
        $employee_repository = new InMemoryEmployeeRepository();

        $employee_repository->add_employee( new Employee( 1, 'John Doe', null, null, null, true, null, null, 'public', null, null, [] ) );
        $employee_repository->add_employee( new Employee( 2, 'Jane Doe', null, null, null, true, null, null, 'public', null, null, [] ) );

        return new ServiceService(
            $service_repository,
            $category_repository,
            $tag_repository,
            $employee_repository,
            new Logger( 'test' )
        );
    }
}

/**
 * In-memory service repository stub.
 */
class InMemoryServiceRepository implements ServiceRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, array<string, mixed>> */
    private array $records = [];

    /** @var array<int, int[]> */
    private array $category_map = [];

    /** @var array<int, int[]> */
    private array $tag_map = [];

    /** @var array<int, array<int, array{employee_id:int, order:int}>> */
    private array $provider_map = [];

    private ServiceCategoryRepositoryInterface $category_repository;

    private ServiceTagRepositoryInterface $tag_repository;

    public function __construct( ServiceCategoryRepositoryInterface $category_repository, ServiceTagRepositoryInterface $tag_repository ) {
        $this->category_repository = $category_repository;
        $this->tag_repository      = $tag_repository;
    }

    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        $services = [];

        foreach ( $this->records as $id => $row ) {
            $is_deleted = (int) $row['is_deleted'] === 1;

            if ( $only_deleted && ! $is_deleted ) {
                continue;
            }

            if ( ! $include_deleted && $is_deleted ) {
                continue;
            }

            $services[] = $this->hydrate_service( $row );
        }

        return $services;
    }

    public function find( int $service_id ) {
        if ( ! isset( $this->records[ $service_id ] ) ) {
            return null;
        }

        return $this->hydrate_service( $this->records[ $service_id ] );
    }

    public function find_with_deleted( int $service_id ) {
        return isset( $this->records[ $service_id ] ) ? $this->hydrate_service( $this->records[ $service_id ] ) : null;
    }

    public function create( array $data ) {
        $id  = $this->counter++;
        $row = $this->normalize_row( $id, $data );

        $this->records[ $id ] = $row;

        return $this->hydrate_service( $row );
    }

    public function update( int $service_id, array $data ) {
        if ( ! isset( $this->records[ $service_id ] ) ) {
            return new WP_Error( 'missing', 'Service not found' );
        }

        $existing              = $this->records[ $service_id ];
        $data['created_at']    = $existing['created_at'];
        $row                   = $this->normalize_row( $service_id, $data );
        $row['created_at']     = $existing['created_at'];
        $this->records[ $service_id ] = $row;

        return $this->hydrate_service( $row );
    }

    public function soft_delete( int $service_id ) {
        if ( ! isset( $this->records[ $service_id ] ) ) {
            return new WP_Error( 'missing', 'Service not found' );
        }

        $this->records[ $service_id ]['is_deleted'] = 1;

        return true;
    }

    public function restore( int $service_id ) {
        if ( ! isset( $this->records[ $service_id ] ) ) {
            return new WP_Error( 'missing', 'Service not found' );
        }

        $this->records[ $service_id ]['is_deleted'] = 0;

        return true;
    }

    public function sync_service_categories( int $service_id, array $category_ids ): bool {
        $this->category_map[ $service_id ] = array_values( array_map( 'intval', $category_ids ) );

        return true;
    }

    public function sync_service_tags( int $service_id, array $tag_ids ): bool {
        $this->tag_map[ $service_id ] = array_values( array_map( 'intval', $tag_ids ) );

        return true;
    }

    public function sync_service_providers( int $service_id, array $providers ): bool {
        $this->provider_map[ $service_id ] = array_values( $providers );

        return true;
    }

    public function get_categories_for_services( array $service_ids ): array {
        $map = [];

        foreach ( $service_ids as $service_id ) {
            $categories = [];

            foreach ( $this->category_map[ $service_id ] ?? [] as $category_id ) {
                $category = $this->category_repository->find( $category_id );
                if ( $category ) {
                    $categories[] = $category;
                }
            }

            if ( ! empty( $categories ) ) {
                $map[ $service_id ] = $categories;
            }
        }

        return $map;
    }

    public function get_tags_for_services( array $service_ids ): array {
        $map = [];

        foreach ( $service_ids as $service_id ) {
            $tags = [];

            foreach ( $this->tag_map[ $service_id ] ?? [] as $tag_id ) {
                $tag = $this->tag_repository->find( $tag_id );
                if ( $tag ) {
                    $tags[] = $tag;
                }
            }

            if ( ! empty( $tags ) ) {
                $map[ $service_id ] = $tags;
            }
        }

        return $map;
    }

    public function get_providers_for_services( array $service_ids ): array {
        $map = [];

        foreach ( $service_ids as $service_id ) {
            if ( isset( $this->provider_map[ $service_id ] ) ) {
                $map[ $service_id ] = $this->provider_map[ $service_id ];
            }
        }

        return $map;
    }

    public function get_service_categories( int $service_id ): array {
        return $this->get_categories_for_services( [ $service_id ] )[ $service_id ] ?? [];
    }

    public function get_service_tags( int $service_id ): array {
        return $this->get_tags_for_services( [ $service_id ] )[ $service_id ] ?? [];
    }

    public function get_service_providers( int $service_id ): array {
        return $this->provider_map[ $service_id ] ?? [];
    }

    /**
     * Hydrate a service from stored data.
     *
     * @param array<string, mixed> $row Row data.
     */
    private function hydrate_service( array $row ): Service {
        $service_id = (int) $row['service_id'];
        $service    = Service::from_row( $row );

        return $service
            ->with_categories( $this->get_service_categories( $service_id ) )
            ->with_tags( $this->get_service_tags( $service_id ) )
            ->with_providers( $this->get_service_providers( $service_id ) );
    }

    /**
     * Normalize record data.
     *
     * @param array<string, mixed> $data Data to persist.
     *
     * @return array<string, mixed>
     */
    private function normalize_row( int $id, array $data ): array {
        $now     = isset( $data['updated_at'] ) ? $data['updated_at'] : ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
        $created = $data['created_at'] ?? $now;

        return [
            'service_id'                  => $id,
            'name'                        => $data['name'],
            'profile_image_id'            => $data['profile_image_id'] ?? null,
            'default_color'               => $data['default_color'] ?? null,
            'visibility'                  => $data['visibility'],
            'price'                       => $data['price'],
            'payment_methods_mode'        => $data['payment_methods_mode'],
            'info'                        => $data['info'],
            'providers_preference'        => $data['providers_preference'],
            'providers_random_tie'        => $data['providers_random_tie'],
            'occupancy_period_before'     => $data['occupancy_period_before'],
            'occupancy_period_after'      => $data['occupancy_period_after'],
            'duration_key'                => $data['duration_key'],
            'slot_length_key'             => $data['slot_length_key'],
            'padding_before_key'          => $data['padding_before_key'],
            'padding_after_key'           => $data['padding_after_key'],
            'online_meeting_provider'     => $data['online_meeting_provider'],
            'limit_per_customer'          => $data['limit_per_customer'],
            'final_step_url_enabled'      => $data['final_step_url_enabled'],
            'final_step_url'              => $data['final_step_url'],
            'min_time_prior_booking_key'  => $data['min_time_prior_booking_key'],
            'min_time_prior_cancel_key'   => $data['min_time_prior_cancel_key'],
            'is_deleted'                  => $data['is_deleted'] ?? 0,
            'created_at'                  => $created,
            'updated_at'                  => $now,
        ];
    }
}

/**
 * In-memory category repository stub.
 */
class InMemoryServiceCategoryRepository implements ServiceCategoryRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, ServiceCategory> */
    private array $categories = [];

    public function all(): array {
        return array_values( $this->categories );
    }

    public function find( int $category_id ) {
        return $this->categories[ $category_id ] ?? null;
    }

    public function find_by_name( string $name ): ?ServiceCategory {
        foreach ( $this->categories as $category ) {
            if ( strtolower( $category->get_name() ) === strtolower( $name ) ) {
                return $category;
            }
        }

        return null;
    }

    public function create( string $name ) {
        $id   = $this->counter++;
        $slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $name ) ?? '' );
        $row  = [
            'category_id' => $id,
            'name'        => $name,
            'slug'        => $slug,
            'created_at'  => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
            'updated_at'  => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
        ];

        $category = ServiceCategory::from_row( $row );
        $this->categories[ $id ] = $category;

        return $category;
    }
}

/**
 * In-memory tag repository stub.
 */
class InMemoryServiceTagRepository implements ServiceTagRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, ServiceTag> */
    private array $tags = [];

    public function all(): array {
        return array_values( $this->tags );
    }

    public function find( int $tag_id ) {
        return $this->tags[ $tag_id ] ?? null;
    }

    public function find_by_name( string $name ): ?ServiceTag {
        foreach ( $this->tags as $tag ) {
            if ( strtolower( $tag->get_name() ) === strtolower( $name ) ) {
                return $tag;
            }
        }

        return null;
    }

    public function create( string $name ) {
        $id   = $this->counter++;
        $slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $name ) ?? '' );
        $row  = [
            'tag_id'     => $id,
            'name'       => $name,
            'slug'       => $slug,
            'created_at' => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
            'updated_at' => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
        ];

        $tag = ServiceTag::from_row( $row );
        $this->tags[ $id ] = $tag;

        return $tag;
    }
}

/**
 * In-memory employee repository stub.
 */
class InMemoryEmployeeRepository implements EmployeeRepositoryInterface {
    /** @var array<int, Employee> */
    private array $employees = [];

    public function add_employee( Employee $employee ): void {
        $this->employees[ $employee->get_id() ] = $employee;
    }

    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        unset( $include_deleted, $only_deleted );
        return array_values( $this->employees );
    }

    public function find( int $employee_id ) {
        return $this->employees[ $employee_id ] ?? null;
    }

    public function find_with_deleted( int $employee_id ) {
        return $this->find( $employee_id );
    }

    public function create( array $data ) {
        unset( $data );
        return new WP_Error( 'unsupported', 'Not implemented' );
    }

    public function update( int $employee_id, array $data ) {
        unset( $employee_id, $data );
        return new WP_Error( 'unsupported', 'Not implemented' );
    }

    public function soft_delete( int $employee_id ) {
        unset( $employee_id );
        return new WP_Error( 'unsupported', 'Not implemented' );
    }

    public function restore( int $employee_id ) {
        unset( $employee_id );
        return new WP_Error( 'unsupported', 'Not implemented' );
    }
}
