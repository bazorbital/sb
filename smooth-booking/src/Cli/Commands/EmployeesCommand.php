<?php
/**
 * WP-CLI commands for employee management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeCategory;
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
     * [--profile-image-id=<id>]
     * : Attachment ID for the profile image.
     *
     * [--default-color=<hex>]
     * : Default HEX color (e.g. #2271b1).
     *
     * [--visibility=<public|private|archived>]
     * : Visibility status for the employee.
     *
     * [--category=<id>]
     * : Assign an existing category. Repeat for multiple categories.
     *
     * [--new-categories=<list>]
     * : Comma separated list of categories to create.
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
            'profile_image_id' => $assoc_args['profile-image-id'] ?? 0,
            'default_color'    => $assoc_args['default-color'] ?? '',
            'visibility'       => $assoc_args['visibility'] ?? 'public',
            'category_ids'     => isset( $assoc_args['category'] ) ? (array) $assoc_args['category'] : [],
            'new_categories'   => $assoc_args['new-categories'] ?? '',
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
     * [--profile-image-id=<id>]
     * : Attachment ID for the profile image.
     *
     * [--default-color=<hex>]
     * : Default HEX color.
     *
     * [--visibility=<public|private|archived>]
     * : Updated visibility.
     *
     * [--category=<id>]
     * : Assign existing categories, replacing previous selections.
     *
     * [--new-categories=<list>]
     * : Comma separated list of categories to add.
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
            'profile_image_id' => $assoc_args['profile-image-id'] ?? ( $employee->get_profile_image_id() ?? 0 ),
            'default_color'    => $assoc_args['default-color'] ?? ( $employee->get_default_color() ?? '' ),
            'visibility'       => $assoc_args['visibility'] ?? $employee->get_visibility(),
            'category_ids'     => isset( $assoc_args['category'] ) ? (array) $assoc_args['category'] : array_map(
                static function ( $cat ) {
                    return $cat instanceof \SmoothBooking\Domain\Employees\EmployeeCategory ? $cat->get_id() : $cat;
                },
                $employee->get_categories()
            ),
            'new_categories'   => $assoc_args['new-categories'] ?? '',
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

    /**
     * Restore a soft deleted employee.
     *
     * ## OPTIONS
     *
     * <employee-id>
     * : The employee identifier.
     */
    public function restore( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Employee ID is required.' );
        }

        $employee_id = (int) $args[0];

        $result = $this->service->restore_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Employee #%d restored.', $employee_id ) );
    }
}
