<?php
/**
 * REST controller for managing employees.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function current_user_can;
use function is_wp_error;
use function rest_ensure_response;

/**
 * Register REST API routes for employees.
 */
class EmployeesController {
    /**
     * Namespace for routes.
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Route base.
     */
    private const ROUTE = '/employees';

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
     * Register REST routes.
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_employees' ],
                    'permission_callback' => [ $this, 'can_manage_employees' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_employee' ],
                    'permission_callback' => [ $this, 'can_manage_employees' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_employee' ],
                    'permission_callback' => [ $this, 'can_manage_employees' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_employee' ],
                    'permission_callback' => [ $this, 'can_manage_employees' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_employee' ],
                    'permission_callback' => [ $this, 'can_manage_employees' ],
                ],
            ]
        );
    }

    /**
     * Retrieve employees.
     */
    public function list_employees(): WP_REST_Response {
        $employees = array_map(
            static function ( Employee $employee ): array {
                return $employee->to_array();
            },
            $this->service->list_employees()
        );

        return rest_ensure_response( [ 'data' => $employees ] );
    }

    /**
     * Retrieve a single employee.
     */
    public function get_employee( WP_REST_Request $request ) {
        $employee_id = (int) $request['id'];
        $employee    = $this->service->get_employee( $employee_id );

        if ( is_wp_error( $employee ) ) {
            return new WP_REST_Response( [ 'message' => $employee->get_error_message() ], 404 );
        }

        return rest_ensure_response( $employee->to_array() );
    }

    /**
     * Create a new employee.
     */
    public function create_employee( WP_REST_Request $request ) {
        $data = $this->extract_employee_data( $request );

        $result = $this->service->create_employee( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    /**
     * Update an existing employee.
     */
    public function update_employee( WP_REST_Request $request ) {
        $employee_id = (int) $request['id'];
        $data        = $this->extract_employee_data( $request );

        $result = $this->service->update_employee( $employee_id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Soft delete an employee.
     */
    public function delete_employee( WP_REST_Request $request ) {
        $employee_id = (int) $request['id'];

        $result = $this->service->delete_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Permission callback.
     */
    public function can_manage_employees(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Extract employee payload from request.
     *
     * @return array<string, mixed>
     */
    private function extract_employee_data( WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_params();
        }

        return [
            'name'             => $params['name'] ?? '',
            'email'            => $params['email'] ?? '',
            'phone'            => $params['phone'] ?? '',
            'specialization'   => $params['specialization'] ?? '',
            'available_online' => $params['available_online'] ?? false,
            'profile_image_id' => $params['profile_image_id'] ?? 0,
            'default_color'    => $params['default_color'] ?? '',
            'visibility'       => $params['visibility'] ?? 'public',
            'category_ids'     => $params['category_ids'] ?? [],
            'new_categories'   => $params['new_categories'] ?? '',
            'locations'        => $params['locations'] ?? [],
            'services'         => $params['services'] ?? [],
            'schedule'         => $params['schedule'] ?? [],
        ];
    }
}
