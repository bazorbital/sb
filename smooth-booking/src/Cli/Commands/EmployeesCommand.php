<?php
/**
 * WP-CLI commands for employee management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function is_wp_error;

/**
 * Provides employee commands for WP-CLI.
 */
class EmployeesCommand extends WP_CLI_Command {
    /**
     * @var EmployeeService
     */
    private EmployeeService $service;

    /**
     * Constructor.
     */
    public function __construct( EmployeeService $service ) {
        $this->service = $service;
    }

    /**
     * List employees.
     *
     * ## EXAMPLES
     *
     *     wp smooth employees list
     */
    public function list(): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyWord
        $employees = $this->service->list_employees();

        if ( empty( $employees ) ) {
            log( 'No employees found.' );
            return;
        }

        foreach ( $employees as $employee ) {
            if ( ! $employee instanceof Employee ) {
                continue;
            }

            line( sprintf( '#%d %s <%s>', $employee->get_id(), $employee->get_name(), $employee->get_email() ?? 'n/a' ) );
        }
    }

    /**
     * Create a new employee.
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Full name of the employee.
     *
     * [--email=<email>]
     * : Email address.
     *
     * [--phone=<phone>]
     * : Phone number.
     *
     * [--specialization=<specialization>]
     * : Specialization label.
     *
     * [--available-online]
     * : Mark employee as available for online booking.
     *
     * ## EXAMPLES
     *
     *     wp smooth employees create --name="John Doe" --email=john@example.com
     */
    public function create( array $args, array $assoc_args ): void {
        if ( empty( $assoc_args['name'] ) ) {
            error( 'The --name option is required.' );
        }

        $data = [
            'name'             => $assoc_args['name'],
            'email'            => $assoc_args['email'] ?? '',
            'phone'            => $assoc_args['phone'] ?? '',
            'specialization'   => $assoc_args['specialization'] ?? '',
            'available_online' => isset( $assoc_args['available-online'] ) ? 1 : 0,
        ];

        $result = $this->service->create_employee( $data );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Employee #%d created.', $result->get_id() ) );
    }

    /**
     * Update an existing employee.
     *
     * ## OPTIONS
     *
     * <employee-id>
     * : The employee identifier.
     *
     * [--name=<name>]
     * : Updated name.
     *
     * [--email=<email>]
     * : Updated email.
     *
     * [--phone=<phone>]
     * : Updated phone.
     *
     * [--specialization=<specialization>]
     * : Updated specialization.
     *
     * [--available-online=<on|off>]
     * : Toggle online availability.
     *
     * ## EXAMPLES
     *
     *     wp smooth employees update 12 --name="Jane Doe"
     */
    public function update( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Employee ID is required.' );
        }

        $employee_id = (int) $args[0];
        $employee    = $this->service->get_employee( $employee_id );

        if ( is_wp_error( $employee ) ) {
            error( $employee->get_error_message() );
        }

        $data = [
            'name'             => $assoc_args['name'] ?? $employee->get_name(),
            'email'            => $assoc_args['email'] ?? ( $employee->get_email() ?? '' ),
            'phone'            => $assoc_args['phone'] ?? ( $employee->get_phone() ?? '' ),
            'specialization'   => $assoc_args['specialization'] ?? ( $employee->get_specialization() ?? '' ),
            'available_online' => isset( $assoc_args['available-online'] ) ? ( 'on' === $assoc_args['available-online'] ? 1 : 0 ) : ( $employee->is_available_online() ? 1 : 0 ),
        ];

        $result = $this->service->update_employee( $employee_id, $data );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Employee #%d updated.', $employee_id ) );
    }

    /**
     * Soft delete an employee.
     *
     * ## OPTIONS
     *
     * <employee-id>
     * : The employee identifier.
     */
    public function delete( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Employee ID is required.' );
        }

        $employee_id = (int) $args[0];

        $result = $this->service->delete_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Employee #%d deleted.', $employee_id ) );
    }
}
