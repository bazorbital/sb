<?php
/**
 * REST controller for managing customers.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Domain\Customers\CustomerService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function array_map;
use function current_user_can;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;

/**
 * Register REST API routes for customers.
 */
class CustomersController {
    /**
     * Namespace for routes.
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Route base.
     */
    private const ROUTE = '/customers';

    /**
     * @var CustomerService
     */
    private CustomerService $service;

    /**
     * Constructor.
     */
    public function __construct( CustomerService $service ) {
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
                    'callback'            => [ $this, 'list_customers' ],
                    'permission_callback' => [ $this, 'can_manage_customers' ],
                    'args'                => [
                        'search' => [
                            'type' => 'string',
                        ],
                        'page'   => [
                            'type'    => 'integer',
                            'default' => 1,
                        ],
                        'per_page' => [
                            'type'    => 'integer',
                            'default' => 20,
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_customer' ],
                    'permission_callback' => [ $this, 'can_manage_customers' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_customer' ],
                    'permission_callback' => [ $this, 'can_manage_customers' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_customer' ],
                    'permission_callback' => [ $this, 'can_manage_customers' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_customer' ],
                    'permission_callback' => [ $this, 'can_manage_customers' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)/restore',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'restore_customer' ],
                'permission_callback' => [ $this, 'can_manage_customers' ],
            ]
        );
    }

    /**
     * Retrieve customers list.
     */
    public function list_customers( WP_REST_Request $request ): WP_REST_Response {
        $paged    = (int) $request->get_param( 'page' );
        $per_page = (int) $request->get_param( 'per_page' );
        $search   = (string) $request->get_param( 'search' );

        $result = $this->service->paginate_customers(
            [
                'paged'    => max( 1, $paged ),
                'per_page' => max( 1, $per_page ),
                'search'   => $search,
            ]
        );

        $result['customers'] = array_map(
            static function ( $customer ) {
                return $customer instanceof \SmoothBooking\Domain\Customers\Customer ? $customer->to_array() : $customer;
            },
            $result['customers']
        );

        return rest_ensure_response( $result );
    }

    /**
     * Retrieve a single customer.
     */
    public function get_customer( WP_REST_Request $request ) {
        $customer_id = (int) $request['id'];
        $customer    = $this->service->get_customer( $customer_id );

        if ( is_wp_error( $customer ) ) {
            return new WP_REST_Response( [ 'message' => $customer->get_error_message() ], 404 );
        }

        return rest_ensure_response( $customer->to_array() );
    }

    /**
     * Create a new customer.
     */
    public function create_customer( WP_REST_Request $request ) {
        $data = $this->extract_customer_data( $request );

        $result = $this->service->create_customer( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    /**
     * Update an existing customer.
     */
    public function update_customer( WP_REST_Request $request ) {
        $customer_id = (int) $request['id'];
        $data        = $this->extract_customer_data( $request );

        $result = $this->service->update_customer( $customer_id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Soft delete a customer.
     */
    public function delete_customer( WP_REST_Request $request ) {
        $customer_id = (int) $request['id'];

        $result = $this->service->delete_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Restore a customer.
     */
    public function restore_customer( WP_REST_Request $request ) {
        $customer_id = (int) $request['id'];

        $result = $this->service->restore_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Permission callback.
     */
    public function can_manage_customers(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Extract customer payload from request.
     *
     * @return array<string, mixed>
     */
    private function extract_customer_data( WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_params();
        }

        return [
            'name'              => $params['name'] ?? '',
            'first_name'        => $params['first_name'] ?? '',
            'last_name'         => $params['last_name'] ?? '',
            'phone'             => $params['phone'] ?? '',
            'email'             => $params['email'] ?? '',
            'date_of_birth'     => $params['date_of_birth'] ?? '',
            'country'           => $params['country'] ?? '',
            'state_region'      => $params['state_region'] ?? '',
            'postal_code'       => $params['postal_code'] ?? '',
            'city'              => $params['city'] ?? '',
            'street_address'    => $params['street_address'] ?? '',
            'additional_address'=> $params['additional_address'] ?? '',
            'street_number'     => $params['street_number'] ?? '',
            'notes'             => $params['notes'] ?? '',
            'profile_image_id'  => $params['profile_image_id'] ?? 0,
            'user_action'       => $params['user_action'] ?? 'none',
            'existing_user_id'  => $params['existing_user_id'] ?? 0,
            'tag_ids'           => $params['tag_ids'] ?? [],
            'new_tags'          => $params['new_tags'] ?? '',
        ];
    }
}
