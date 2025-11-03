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

use function array_map;
use function current_user_can;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;
use function rest_sanitize_boolean;

/**
 * Registers REST API endpoints for locations.
 */
class LocationsController {
    /**
     * Namespace for the endpoints.
     *
     * @var string
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Base route for location resources.
     *
     * @var string
     */
    private const ROUTE = '/locations';

    /**
     * Location domain service handling persistence and validation.
     *
     * @var LocationService
     */
    private LocationService $service;

    /**
     * Inject dependencies.
     *
     * @param LocationService $service Domain service used for location operations.
     */
    public function __construct( LocationService $service ) {
        $this->service = $service;
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
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

    /**
     * List locations with optional deleted filters.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Serialised location payload.
     */
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

    /**
     * Retrieve a single location.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Location payload or error message.
     */
    public function get_location( WP_REST_Request $request ): WP_REST_Response {
        $location_id = (int) $request['id'];
        $location    = $this->service->get_location_with_deleted( $location_id );

        if ( is_wp_error( $location ) ) {
            return new WP_REST_Response( [ 'message' => $location->get_error_message() ], 404 );
        }

        return rest_ensure_response( $location->to_array() );
    }

    /**
     * Create a new location.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Newly created location payload or errors.
     */
    public function create_location( WP_REST_Request $request ): WP_REST_Response {
        $payload = $this->extract_payload( $request );

        $result = $this->service->create_location( $payload );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    /**
     * Update an existing location.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Updated location payload or errors.
     */
    public function update_location( WP_REST_Request $request ): WP_REST_Response {
        $location_id = (int) $request['id'];
        $payload     = $this->extract_payload( $request );

        $result = $this->service->update_location( $location_id, $payload );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Soft delete a location.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Confirmation payload or errors.
     */
    public function delete_location( WP_REST_Request $request ): WP_REST_Response {
        $location_id = (int) $request['id'];

        $result = $this->service->delete_location( $location_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Restore a soft-deleted location.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Restored location payload or errors.
     */
    public function restore_location( WP_REST_Request $request ): WP_REST_Response {
        $location_id = (int) $request['id'];

        $result = $this->service->restore_location( $location_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Determine whether the current user can manage locations.
     *
     * @return bool True when the user has access to manage locations.
     */
    public function can_manage_locations(): bool {
        return current_user_can( LocationsPage::CAPABILITY );
    }

    /**
     * Extract request payload with baseline sanitisation.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return array<string, mixed> Normalised location data consumed by the service layer.
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
