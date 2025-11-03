<?php
/**
 * REST controller for schema status.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Domain\SchemaStatusService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function __;
use function check_ajax_referer;
use function current_user_can;
use function rest_ensure_response;

/**
 * Register REST API routes for schema status.
 */
class SchemaStatusController {
    /**
     * Namespace for routes.
     *
     * @var string
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Route base.
     *
     * @var string
     */
    private const ROUTE = '/schema-status';

    /**
     * Domain service exposing schema health information.
     *
     * @var SchemaStatusService
     */
    private SchemaStatusService $schema_service;

    /**
     * Inject dependencies.
     *
     * @param SchemaStatusService $schema_service Service orchestrating schema status checks.
     */
    public function __construct( SchemaStatusService $schema_service ) {
        $this->schema_service = $schema_service;
    }

    /**
     * Register REST routes.
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
                    'callback'            => [ $this, 'get_status' ],
                    'permission_callback' => [ $this, 'can_view_status' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'repair_schema' ],
                    'permission_callback' => [ $this, 'can_manage_schema' ],
                ],
            ]
        );
    }

    /**
     * Retrieve schema status.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Schema status data or error response.
     */
    public function get_status( WP_REST_Request $request ): WP_REST_Response {
        $force_refresh = (bool) $request->get_param( 'force' );

        $status = $this->schema_service->get_status( $force_refresh );

        if ( is_wp_error( $status ) ) {
            return new WP_REST_Response( [ 'message' => $status->get_error_message() ], 500 );
        }

        return rest_ensure_response( [
            'healthy' => $this->schema_service->schema_is_healthy(),
            'tables'  => $status,
        ] );
    }

    /**
     * Repair schema.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response Response confirming the repair was initiated.
     */
    public function repair_schema( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        $this->schema_service->repair_schema();

        return new WP_REST_Response( [ 'message' => __( 'Schema check initiated.', 'smooth-booking' ) ], 200 );
    }

    /**
     * Permission callback for viewing status.
     *
     * @return bool True when the current user may view schema status.
     */
    public function can_view_status(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Permission callback for managing schema.
     *
     * @return bool True when the current user may repair the schema.
     */
    public function can_manage_schema(): bool {
        return current_user_can( 'manage_options' ) && check_ajax_referer( 'wp_rest', '_wpnonce', false );
    }
}
