<?php
/**
 * REST controller for managing appointments.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use SmoothBooking\Admin\AppointmentsPage;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function current_user_can;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;

/**
 * Registers REST API endpoints for appointments.
 */
class AppointmentsController {
    /**
     * API namespace.
     */
    private const NAMESPACE = 'smooth-booking/v1';

    /**
     * Route base.
     */
    private const ROUTE = '/appointments';

    /**
     * @var AppointmentService
     */
    private AppointmentService $service;

    /**
     * Constructor.
     */
    public function __construct( AppointmentService $service ) {
        $this->service = $service;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_appointments' ],
                    'permission_callback' => [ $this, 'can_manage_appointments' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_appointment' ],
                    'permission_callback' => [ $this, 'can_manage_appointments' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_appointment' ],
                    'permission_callback' => [ $this, 'can_manage_appointments' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_appointment' ],
                    'permission_callback' => [ $this, 'can_manage_appointments' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_appointment' ],
                    'permission_callback' => [ $this, 'can_manage_appointments' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE . '/(?P<id>\d+)/restore',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'restore_appointment' ],
                'permission_callback' => [ $this, 'can_manage_appointments' ],
            ]
        );
    }

    /**
     * List appointments.
     */
    public function list_appointments( WP_REST_Request $request ): WP_REST_Response {
        $args = [
            'status'          => sanitize_key( (string) $request->get_param( 'status' ) ),
            'employee_id'     => (int) $request->get_param( 'employee_id' ),
            'service_id'      => (int) $request->get_param( 'service_id' ),
            'include_deleted' => rest_sanitize_boolean( $request->get_param( 'include_deleted' ) ),
            'only_deleted'    => rest_sanitize_boolean( $request->get_param( 'only_deleted' ) ),
            'paged'           => max( 1, (int) $request->get_param( 'page' ) ),
            'per_page'        => (int) $request->get_param( 'per_page' ) ?: 20,
            'orderby'         => sanitize_key( (string) $request->get_param( 'orderby' ) ) ?: 'scheduled_start',
            'order'           => sanitize_key( (string) $request->get_param( 'order' ) ) ?: 'DESC',
        ];

        $result = $this->service->paginate_appointments( $args );

        return rest_ensure_response(
            [
                'appointments' => array_map(
                    static function ( Appointment $appointment ): array {
                        return $appointment->to_array();
                    },
                    $result['appointments']
                ),
                'total'        => $result['total'],
                'per_page'     => $result['per_page'],
                'paged'        => $result['paged'],
            ]
        );
    }

    /**
     * Retrieve a single appointment.
     */
    public function get_appointment( WP_REST_Request $request ) {
        $appointment_id = (int) $request['id'];
        $appointment    = $this->service->get_appointment( $appointment_id );

        if ( is_wp_error( $appointment ) ) {
            return new WP_REST_Response( [ 'message' => $appointment->get_error_message() ], 404 );
        }

        return rest_ensure_response( $appointment->to_array() );
    }

    /**
     * Create a new appointment.
     */
    public function create_appointment( WP_REST_Request $request ) {
        $data = $this->extract_payload( $request );

        $result = $this->service->create_appointment( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result->to_array(), 201 );
    }

    /**
     * Update an existing appointment.
     */
    public function update_appointment( WP_REST_Request $request ) {
        $appointment_id = (int) $request['id'];
        $data           = $this->extract_payload( $request );

        $result = $this->service->update_appointment( $appointment_id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( $result->to_array() );
    }

    /**
     * Delete an appointment.
     */
    public function delete_appointment( WP_REST_Request $request ) {
        $appointment_id = (int) $request['id'];

        $result = $this->service->delete_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Restore an appointment.
     */
    public function restore_appointment( WP_REST_Request $request ) {
        $appointment_id = (int) $request['id'];

        $result = $this->service->restore_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        $appointment = $this->service->get_appointment( $appointment_id );

        if ( is_wp_error( $appointment ) ) {
            return rest_ensure_response( [ 'restored' => true ] );
        }

        return rest_ensure_response( $appointment->to_array() );
    }

    /**
     * Determine capabilities.
     */
    public function can_manage_appointments(): bool {
        return current_user_can( AppointmentsPage::CAPABILITY );
    }

    /**
     * Extract payload from request.
     *
     * @return array<string, mixed>
     */
    private function extract_payload( WP_REST_Request $request ): array {
        return [
            'provider_id'        => (int) $request->get_param( 'provider_id' ),
            'service_id'         => (int) $request->get_param( 'service_id' ),
            'customer_id'        => (int) $request->get_param( 'customer_id' ),
            'appointment_date'   => sanitize_text_field( (string) $request->get_param( 'appointment_date' ) ),
            'appointment_start'  => sanitize_text_field( (string) $request->get_param( 'appointment_start' ) ),
            'appointment_end'    => sanitize_text_field( (string) $request->get_param( 'appointment_end' ) ),
            'notes'              => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
            'internal_note'      => sanitize_textarea_field( (string) $request->get_param( 'internal_note' ) ),
            'status'             => sanitize_key( (string) $request->get_param( 'status' ) ),
            'payment_status'     => sanitize_key( (string) $request->get_param( 'payment_status' ) ),
            'send_notifications' => rest_sanitize_boolean( $request->get_param( 'send_notifications' ) ),
            'is_recurring'       => rest_sanitize_boolean( $request->get_param( 'is_recurring' ) ),
            'customer_email'     => sanitize_email( (string) $request->get_param( 'customer_email' ) ),
            'customer_phone'     => sanitize_text_field( (string) $request->get_param( 'customer_phone' ) ),
        ];
    }
}
