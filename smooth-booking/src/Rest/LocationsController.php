<?php
/**
 * REST controller for managing locations.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Admin\LocationsPage;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function current_user_can;
use function is_wp_error;
use function rest_ensure_response;
use function rest_sanitize_boolean;

/**
 * Registers REST API endpoints for locations.
 */
class LocationsController {
    private const NAMESPACE = 'smooth-booking/v1';

    private const ROUTE = '/locations';

    private LocationService $service;

    public function __construct( LocationService $service ) {
        $this->service = $service;
    }

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_locations' ],
                    'permission_callback' => [ $this, 'can_manage_locations' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_location' ],
                    'permission_callback' => [ $this, 'can_manage_locations' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_location' ],
                    'permission_callback' => [ $this, 'can_manage_locations' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_location' ],
                    'permission_callback' => [ $this, 'can_manage_locations' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_location' ],
                    'permission_callback' => [ $this, 'can_manage_locations' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)/restore',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'restore_location' ],
                'permission_callback' => [ $this, 'can_manage_locations' ],
            ]
        );
    }

    public function list_locations( WP_REST_Request $request ): WP_REST_Response {
        $include_deleted = rest_sanitize_boolean( $request->get_param( 'include_deleted' ) );
        $only_deleted    = rest_sanitize_boolean( $request->get_param( 'only_deleted' ) );

        $locations = array_map(
            static function ( Location $location ): array {
                return $location->to_array();
            },
            $this->service->list_locations(
                [
                    'include_deleted' => $include_deleted,
                    'only_deleted'    => $only_deleted,
                ]
            )
        );

        return rest_ensure_response( [ 'data' => $locations ] );
    }

    public function get_location( WP_REST_Request $request ) {
        $location_id = (int) $request['id'];
        $location    = $this->service->get_location_with_deleted( $location_id );

        if ( is_wp_error( $location ) ) {
            return new WP_REST_Response( [ 'message' => $location->get_error_message() ], 404 );
        }

        return rest_ensure_response( $location->to_array() );
    }

    public function create_location( WP_REST_Request $request ) {
        $payload = $this->extract_payload( $request );

        $result = $this->service->create_location( $payload );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    public function update_location( WP_REST_Request $request ) {
        $location_id = (int) $request['id'];
        $payload     = $this->extract_payload( $request );

        $result = $this->service->update_location( $location_id, $payload );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    public function delete_location( WP_REST_Request $request ) {
        $location_id = (int) $request['id'];

        $result = $this->service->delete_location( $location_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    public function restore_location( WP_REST_Request $request ) {
        $location_id = (int) $request['id'];

        $result = $this->service->restore_location( $location_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    public function can_manage_locations(): bool {
        return current_user_can( LocationsPage::CAPABILITY );
    }

    /**
     * @return array<string, mixed>
     */
    private function extract_payload( WP_REST_Request $request ): array {
        return [
            'name'             => $request->get_param( 'name' ),
            'profile_image_id' => $request->get_param( 'profile_image_id' ),
            'address'          => $request->get_param( 'address' ),
            'phone'            => $request->get_param( 'phone' ),
            'base_email'       => $request->get_param( 'base_email' ),
            'website'          => $request->get_param( 'website' ),
            'industry_id'      => $request->get_param( 'industry_id' ),
            'is_event_location'=> $request->get_param( 'is_event_location' ),
            'company_name'     => $request->get_param( 'company_name' ),
            'company_address'  => $request->get_param( 'company_address' ),
            'company_phone'    => $request->get_param( 'company_phone' ),
        ];
    }
}
