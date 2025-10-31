<?php

namespace SmoothBooking\Tests\Unit\Domain\Employees;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeCategory;
use SmoothBooking\Domain\Employees\EmployeeCategoryRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Employees\EmployeeService
 */
class EmployeeServiceTest extends TestCase {
    public function test_create_employee_requires_name(): void {
        $repository = new InMemoryEmployeeRepository();
        $categories = new InMemoryEmployeeCategoryRepository();
        $service    = new EmployeeService( $repository, $categories, new Logger( 'test' ) );

        $result = $service->create_employee( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_employee_missing_name', $result->get_error_code() );
    }

    public function test_create_employee_sanitizes_fields(): void {
        $repository = new InMemoryEmployeeRepository();
        $categories = new InMemoryEmployeeCategoryRepository();
        $service    = new EmployeeService( $repository, $categories, new Logger( 'test' ) );

        $result = $service->create_employee(
            [
                'name'             => '  Jane  ',
                'email'            => '  jane@example.com ',
                'phone'            => ' +361234 ',
                'specialization'   => 'Stylist ',
                'available_online' => '1',
                'profile_image_id' => '7',
                'default_color'    => ' #ff0000 ',
                'visibility'       => 'Public ',
            ]
        );

        $this->assertInstanceOf( Employee::class, $result );
        $this->assertSame( 'Jane', $result->get_name() );
        $this->assertSame( 'jane@example.com', $result->get_email() );
        $this->assertSame( '+361234', $result->get_phone() );
        $this->assertSame( 'Stylist', $result->get_specialization() );
        $this->assertTrue( $result->is_available_online() );
        $this->assertSame( 7, $result->get_profile_image_id() );
        $this->assertSame( '#ff0000', $result->get_default_color() );
        $this->assertSame( 'public', $result->get_visibility() );
    }

    public function test_create_employee_rejects_non_numeric_service_price(): void {
        $repository = new InMemoryEmployeeRepository();
        $categories = new InMemoryEmployeeCategoryRepository();
        $service    = new EmployeeService( $repository, $categories, new Logger( 'test' ) );

        $result = $service->create_employee(
            [
                'name'     => 'Jane Doe',
                'services' => [
                    10 => [
                        'service_id' => 10,
                        'selected'   => '1',
                        'price'      => 'abc',
                    ],
                ],
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_employee_invalid_service_price', $result->get_error_code() );
    }

    public function test_create_employee_rejects_schedule_with_invalid_hours(): void {
        $repository = new InMemoryEmployeeRepository();
        $categories = new InMemoryEmployeeCategoryRepository();
        $service    = new EmployeeService( $repository, $categories, new Logger( 'test' ) );

        $result = $service->create_employee(
            [
                'name'     => 'John Doe',
                'schedule' => [
                    1 => [
                        'start' => '10:00',
                        'end'   => '09:00',
                    ],
                ],
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_employee_invalid_schedule', $result->get_error_code() );
    }
}

/**
 * Simple in-memory repository for testing.
 */
class InMemoryEmployeeRepository implements EmployeeRepositoryInterface {
    private int $counter = 1;

    /** @var array<int, Employee> */
    private array $employees = [];

    /** @var array<int, int[]> */
    private array $locations = [];

    /** @var array<int, array<int, array{service_id:int, order:int, price:float|null}>> */
    private array $services = [];

    /** @var array<int, array<int, array{start_time:?string,end_time:?string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>> */
    private array $schedule = [];

    public function all( bool $include_deleted = false, bool $only_deleted = false ): array {
        return array_values( $this->employees );
    }

    public function find( int $employee_id ) {
        return $this->employees[ $employee_id ] ?? null;
    }

    public function find_with_deleted( int $employee_id ) {
        return $this->find( $employee_id );
    }

    public function create( array $data ) {
        $employee = new Employee(
            $this->counter++,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['specialization'],
            (bool) $data['available_online'],
            $data['profile_image_id'],
            $data['default_color'],
            $data['visibility'],
            null,
            null,
            []
        );

        $this->employees[ $employee->get_id() ] = $employee;
        $this->locations[ $employee->get_id() ] = [];
        $this->services[ $employee->get_id() ]  = [];
        $this->schedule[ $employee->get_id() ]  = [];

        return $employee;
    }

    public function update( int $employee_id, array $data ) {
        $employee = new Employee(
            $employee_id,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['specialization'],
            (bool) $data['available_online'],
            $data['profile_image_id'],
            $data['default_color'],
            $data['visibility'],
            null,
            null,
            []
        );

        $this->employees[ $employee_id ] = $employee;

        return $employee;
    }

    public function soft_delete( int $employee_id ) {
        unset( $this->employees[ $employee_id ] );
        unset( $this->locations[ $employee_id ], $this->services[ $employee_id ], $this->schedule[ $employee_id ] );

        return true;
    }

    public function restore( int $employee_id ) {
        return $this->find( $employee_id ) ?? new WP_Error( 'missing', 'not found' );
    }

    public function get_employee_locations( int $employee_id ): array {
        return $this->locations[ $employee_id ] ?? [];
    }

    public function sync_employee_locations( int $employee_id, array $location_ids ) {
        $this->locations[ $employee_id ] = array_values( $location_ids );

        return true;
    }

    public function get_employee_services( int $employee_id ): array {
        return $this->services[ $employee_id ] ?? [];
    }

    public function sync_employee_services( int $employee_id, array $services ) {
        $this->services[ $employee_id ] = array_values( $services );

        return true;
    }

    public function get_employee_schedule( int $employee_id ): array {
        return $this->schedule[ $employee_id ] ?? [];
    }

    public function save_employee_schedule( int $employee_id, array $schedule ) {
        $this->schedule[ $employee_id ] = $schedule;

        return true;
    }
}

/**
 * Simple in-memory category repository.
 */
class InMemoryEmployeeCategoryRepository implements EmployeeCategoryRepositoryInterface {
    public function all(): array {
        return [];
    }

    public function find( int $category_id ) {
        return null;
    }

    public function find_by_name( string $name ): ?EmployeeCategory {
        return null;
    }

    public function create( string $name ) {
        return new EmployeeCategory( 1, $name, $name );
    }

    public function sync_employee_categories( int $employee_id, array $category_ids ): bool {
        return true;
    }

    public function get_categories_for_employees( array $employee_ids ): array {
        return [];
    }

    public function get_employee_categories( int $employee_id ): array {
        return [];
    }
}
