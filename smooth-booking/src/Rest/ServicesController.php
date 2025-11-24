<?php
/**
 * REST controller for managing services.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Admin\ServicesPage;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function array_map;
use function current_user_can;
use function is_array;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;
use function rest_sanitize_boolean;
use function implode;

/**
 * Registers REST API endpoints for services.
 */
class ServicesController {
    /**
     * API namespace.
     *
     * @var string
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Route base.
     *
     * @var string
     */
    private const ROUTE = '/services';

    /**
     * Service domain service orchestrating persistence and validation.
     *
     * @var ServiceService
     */
    private ServiceService $service;

    /**
     * Inject dependencies.
     *
     * @param ServiceService $service Domain service used for service operations.
     */
    public function __construct( ServiceService $service ) {
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
                    'callback'            => [ $this, 'list_services' ],
                    'permission_callback' => [ $this, 'can_manage_services' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_service' ],
                    'permission_callback' => [ $this, 'can_manage_services' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_service' ],
                    'permission_callback' => [ $this, 'can_manage_services' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_service' ],
                    'permission_callback' => [ $this, 'can_manage_services' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_service' ],
                    'permission_callback' => [ $this, 'can_manage_services' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)/restore',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'restore_service' ],
                'permission_callback' => [ $this, 'can_manage_services' ],
            ]
        );
    }

    /**
     * List services.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Serialised services list.
     */
    public function list_services( WP_REST_Request $request ): WP_REST_Response {
        $include_deleted = rest_sanitize_boolean( $request->get_param( 'include_deleted' ) );
        $only_deleted    = rest_sanitize_boolean( $request->get_param( 'only_deleted' ) );

        $services = array_map(
            static function ( Service $service ): array {
                return $service->to_array();
            },
            $this->service->list_services(
                [
                    'include_deleted' => $include_deleted,
                    'only_deleted'    => $only_deleted,
                ]
            )
        );

        return rest_ensure_response( [ 'data' => $services ] );
    }

    /**
     * Retrieve a single service.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Service payload or error message.
     */
    public function get_service( WP_REST_Request $request ): WP_REST_Response {
        $service_id = (int) $request['id'];
        $service    = $this->service->get_service( $service_id );

        if ( is_wp_error( $service ) ) {
            return new WP_REST_Response( [ 'message' => $service->get_error_message() ], 404 );
        }

        return rest_ensure_response( $service->to_array() );
    }

    /**
     * Create a new service.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Newly created service payload or validation errors.
     */
    public function create_service( WP_REST_Request $request ): WP_REST_Response {
        $data = $this->extract_service_data( $request );

        $result = $this->service->create_service( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    /**
     * Update a service.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Updated service payload or validation errors.
     */
    public function update_service( WP_REST_Request $request ): WP_REST_Response {
        $service_id = (int) $request['id'];
        $data       = $this->extract_service_data( $request );

        $result = $this->service->update_service( $service_id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Soft delete a service.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Confirmation payload or validation errors.
     */
    public function delete_service( WP_REST_Request $request ): WP_REST_Response {
        $service_id = (int) $request['id'];

        $result = $this->service->delete_service( $service_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Restore a soft-deleted service.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Restored service payload or validation errors.
     */
    public function restore_service( WP_REST_Request $request ): WP_REST_Response {
        $service_id = (int) $request['id'];

        $result = $this->service->restore_service( $service_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        $service = $this->service->get_service( $service_id );

        if ( is_wp_error( $service ) ) {
            return rest_ensure_response( [ 'restored' => true ] );
        }

        return rest_ensure_response( $service->to_array() );
    }

    /**
     * Determine whether the current user can manage services.
     *
     * @return bool True when the user has access to manage services.
     */
    public function can_manage_services(): bool {
        return current_user_can( ServicesPage::CAPABILITY );
    }

    /**
     * Extract payload for service operations.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return array<string, mixed> Sanitised service data consumed by the service layer.
     */
    private function extract_service_data( WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_params();
        }

        $new_categories = $params['new_categories'] ?? '';
        $new_tags       = $params['new_tags'] ?? '';

        if ( is_array( $new_categories ) ) {
            $new_categories = implode( ',', $new_categories );
        }

        if ( is_array( $new_tags ) ) {
            $new_tags = implode( ',', $new_tags );
        }

        $providers = [];
        if ( isset( $params['providers'] ) && is_array( $params['providers'] ) ) {
            foreach ( $params['providers'] as $provider ) {
                if ( is_array( $provider ) ) {
                    $providers[] = [
                        'employee_id' => $provider['employee_id'] ?? 0,
                        'order'       => $provider['order'] ?? 0,
                    ];
                } else {
                    $providers[] = $provider;
                }
            }
        }

        return [
            'name'                         => $params['name'] ?? '',
            'profile_image_id'             => $params['profile_image_id'] ?? 0,
            'default_background_color'     => $params['default_background_color'] ?? ( $params['default_color'] ?? '' ),
            'default_text_color'           => $params['default_text_color'] ?? '',
            'visibility'                   => $params['visibility'] ?? 'public',
            'price'                        => $params['price'] ?? '',
            'payment_methods_mode'         => $params['payment_methods_mode'] ?? 'default',
            'info'                         => $params['info'] ?? '',
            'providers_preference'         => $params['providers_preference'] ?? 'specified_order',
            'providers_random_tie'         => $params['providers_random_tie'] ?? false,
            'occupancy_period_before'      => $params['occupancy_period_before'] ?? 0,
            'occupancy_period_after'       => $params['occupancy_period_after'] ?? 0,
            'duration_key'                 => $params['duration_key'] ?? '15_minutes',
            'slot_length_key'              => $params['slot_length_key'] ?? 'default',
            'padding_before_key'           => $params['padding_before_key'] ?? 'off',
            'padding_after_key'            => $params['padding_after_key'] ?? 'off',
            'online_meeting_provider'      => $params['online_meeting_provider'] ?? 'off',
            'limit_per_customer'           => $params['limit_per_customer'] ?? 'off',
            'final_step_url_enabled'       => $params['final_step_url_enabled'] ?? false,
            'final_step_url'               => $params['final_step_url'] ?? '',
            'min_time_prior_booking_key'   => $params['min_time_prior_booking_key'] ?? 'default',
            'min_time_prior_cancel_key'    => $params['min_time_prior_cancel_key'] ?? 'default',
            'category_ids'                 => $params['category_ids'] ?? [],
            'new_categories'               => $new_categories,
            'tag_ids'                      => $params['tag_ids'] ?? [],
            'new_tags'                     => $new_tags,
            'providers'                    => $providers,
        ];
    }
}
