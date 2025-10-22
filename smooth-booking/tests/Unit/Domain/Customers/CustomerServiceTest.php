<?php

namespace SmoothBooking\Tests\Unit\Domain\Customers;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerRepositoryInterface;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Customers\CustomerTag;
use SmoothBooking\Domain\Customers\CustomerTagRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use function sanitize_title;

/**
 * @covers \SmoothBooking\Domain\Customers\CustomerService
 */
class CustomerServiceTest extends TestCase {
    public function test_create_customer_requires_name(): void {
        $repository = new InMemoryCustomerRepository();
        $tags       = new InMemoryCustomerTagRepository();
        $service    = new CustomerService( $repository, $tags, new Logger( 'test' ) );

        $result = $service->create_customer( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_customer_invalid_name', $result->get_error_code() );
    }

    public function test_create_customer_sanitizes_fields(): void {
        $repository = new InMemoryCustomerRepository();
        $tags       = new InMemoryCustomerTagRepository();
        $service    = new CustomerService( $repository, $tags, new Logger( 'test' ) );

        $result = $service->create_customer(
            [
                'name'             => '  Jane Client ',
                'first_name'       => ' Jane ',
                'last_name'        => ' Doe ',
                'email'            => ' jane@example.com ',
                'phone'            => ' +3612345 ',
                'date_of_birth'    => '1990-01-05',
                'country'          => ' Hungary ',
                'city'             => ' Budapest ',
                'street_address'   => ' Main st ',
                'additional_address' => ' Floor 2 ',
                'street_number'    => ' 12/A ',
                'notes'            => " Note with spaces \n",
                'profile_image_id' => '7',
                'user_action'      => 'none',
            ]
        );

        $this->assertInstanceOf( Customer::class, $result );
        $this->assertSame( 'Jane Client', $result->get_name() );
        $this->assertSame( 'Jane', $result->get_first_name() );
        $this->assertSame( 'Doe', $result->get_last_name() );
        $this->assertSame( 'jane@example.com', $result->get_email() );
        $this->assertSame( '+3612345', $result->get_phone() );
        $this->assertSame( '1990-01-05', $result->get_date_of_birth() );
        $this->assertSame( 'Hungary', $result->get_country() );
        $this->assertSame( 'Budapest', $result->get_city() );
        $this->assertSame( 'Main st', $result->get_street_address() );
        $this->assertSame( 'Floor 2', $result->get_additional_address() );
        $this->assertSame( '12/A', $result->get_street_number() );
        $this->assertSame( 'Note with spaces', $result->get_notes() );
        $this->assertSame( 7, $result->get_profile_image_id() );
    }

    public function test_create_customer_validates_date_format(): void {
        $repository = new InMemoryCustomerRepository();
        $tags       = new InMemoryCustomerTagRepository();
        $service    = new CustomerService( $repository, $tags, new Logger( 'test' ) );

        $result = $service->create_customer(
            [
                'name'          => 'Client',
                'date_of_birth' => '05/12/2020',
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_customer_invalid_birthdate', $result->get_error_code() );
    }

    public function test_create_customer_assigns_existing_and_new_tags(): void {
        $repository = new InMemoryCustomerRepository();
        $tags       = new InMemoryCustomerTagRepository();
        $service    = new CustomerService( $repository, $tags, new Logger( 'test' ) );

        $existing_tag = $tags->create( 'VIP' );

        $result = $service->create_customer(
            [
                'name'      => 'Tagged Client',
                'tag_ids'   => [ (string) $existing_tag->get_id() ],
                'new_tags'  => 'Preferred, Partner',
                'user_action' => 'none',
            ]
        );

        $this->assertInstanceOf( Customer::class, $result );
        $this->assertCount( 3, $result->get_tags() );

        $tag_ids = array_map(
            static function ( CustomerTag $tag ): int {
                return $tag->get_id();
            },
            $result->get_tags()
        );

        $this->assertContains( $existing_tag->get_id(), $tag_ids );
        $this->assertCount( 3, array_unique( $tag_ids ) );
    }
}

/**
 * Simple in-memory customer repository for testing.
 */
class InMemoryCustomerRepository implements CustomerRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, Customer> */
    private array $customers = [];

    /** @var array<int, array<int>> */
    private array $customer_tags = [];

    public function paginate( array $args = [] ): array {
        return [
            'customers' => array_values( $this->customers ),
            'total'     => count( $this->customers ),
        ];
    }

    public function find( int $customer_id ) {
        return $this->customers[ $customer_id ] ?? null;
    }

    public function find_with_deleted( int $customer_id ) {
        return $this->find( $customer_id );
    }

    public function create( array $data ) {
        $customer = $this->make_customer( $this->counter++, $data );
        $this->customers[ $customer->get_id() ] = $customer;

        return $customer;
    }

    public function update( int $customer_id, array $data ) {
        $customer = $this->make_customer( $customer_id, $data );
        $this->customers[ $customer_id ] = $customer;

        return $customer;
    }

    public function soft_delete( int $customer_id ) {
        unset( $this->customers[ $customer_id ] );

        return true;
    }

    public function restore( int $customer_id ) {
        return $this->customers[ $customer_id ] ?? new WP_Error( 'missing', 'not found' );
    }

    public function sync_tags( int $customer_id, array $tag_ids ): void {
        $this->customer_tags[ $customer_id ] = $tag_ids;
    }

    public function get_tags_for_customers( array $customer_ids ): array {
        $results = [];
        foreach ( $customer_ids as $id ) {
            $ids = $this->customer_tags[ $id ] ?? [];
            $results[ $id ] = array_map(
                static function ( int $tag_id ): CustomerTag {
                    return new CustomerTag( $tag_id, 'Tag ' . $tag_id, 'tag-' . $tag_id );
                },
                $ids
            );
        }

        return $results;
    }

    private function make_customer( int $id, array $data ): Customer {
        return new Customer(
            $id,
            $data['name'],
            $data['user_id'] ?? null,
            $data['profile_image_id'] ?? null,
            $data['first_name'] ? $data['first_name'] : null,
            $data['last_name'] ? $data['last_name'] : null,
            $data['phone'] ? $data['phone'] : null,
            $data['email'] ? $data['email'] : null,
            $data['date_of_birth'] ? $data['date_of_birth'] : null,
            $data['country'] ? $data['country'] : null,
            $data['state_region'] ? $data['state_region'] : null,
            $data['postal_code'] ? $data['postal_code'] : null,
            $data['city'] ? $data['city'] : null,
            $data['street_address'] ? $data['street_address'] : null,
            $data['additional_address'] ? $data['additional_address'] : null,
            $data['street_number'] ? $data['street_number'] : null,
            $data['notes'] ? $data['notes'] : null,
            null,
            0,
            0.0,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            false
        );
    }
}

/**
 * Simple in-memory tag repository for testing.
 */
class InMemoryCustomerTagRepository implements CustomerTagRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, CustomerTag> */
    private array $tags = [];

    public function all(): array {
        return array_values( $this->tags );
    }

    public function create( string $name ) {
        $tag = new CustomerTag( $this->counter++, $name, sanitize_title( $name ) );
        $this->tags[ $tag->get_id() ] = $tag;

        return $tag;
    }

    public function find_by_ids( array $ids ): array {
        return array_values( array_intersect_key( $this->tags, array_flip( $ids ) ) );
    }

    public function find_by_slug( string $slug ): ?CustomerTag {
        foreach ( $this->tags as $tag ) {
            if ( $tag->get_slug() === $slug ) {
                return $tag;
            }
        }

        return null;
    }
}
