<?php
/**
 * WP-CLI commands for appointment management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function absint;
use function is_wp_error;
use function sprintf;
use function wp_date;

/**
 * Provides WP-CLI helpers for appointment workflows.
 */
class AppointmentsCommand extends WP_CLI_Command {
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
     * List appointments.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter appointments by status (pending, confirmed, completed, canceled).
     *
     * [--employee=<id>]
     * : Filter by provider (employee ID).
     *
     * [--service=<id>]
     * : Filter by service ID.
     *
     * [--include-deleted]
     * : Include soft-deleted appointments.
     *
     * ## EXAMPLES
     *
     *     wp smooth appointments list
     *     wp smooth appointments list --status=confirmed
     */
    public function list( array $args, array $assoc_args ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyWord
        $filters = [
            'status'          => $assoc_args['status'] ?? '',
            'employee_id'     => isset( $assoc_args['employee'] ) ? absint( $assoc_args['employee'] ) : null,
            'service_id'      => isset( $assoc_args['service'] ) ? absint( $assoc_args['service'] ) : null,
            'include_deleted' => isset( $assoc_args['include-deleted'] ),
            'per_page'        => 50,
            'paged'           => 1,
            'orderby'         => 'scheduled_start',
            'order'           => 'ASC',
        ];

        $result = $this->service->paginate_appointments( $filters );
        $appointments = $result['appointments'];

        if ( empty( $appointments ) ) {
            log( 'No appointments found.' );
            return;
        }

        foreach ( $appointments as $appointment ) {
            if ( ! $appointment instanceof Appointment ) {
                continue;
            }

            $start = wp_date( 'Y-m-d H:i', $appointment->get_scheduled_start()->getTimestamp() );
            $label = sprintf(
                '#%d %s — %s (%s) -> %s',
                $appointment->get_id(),
                $start,
                $appointment->get_service_name() ?? 'n/a',
                $appointment->get_employee_name() ?? 'n/a',
                ucfirst( $appointment->get_status() )
            );

            line( $label );
        }
    }

    /**
     * Soft delete an appointment.
     *
     * ## OPTIONS
     *
     * <appointment-id>
     * : Identifier of the appointment to delete.
     */
    public function delete( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Appointment ID is required.' );
        }

        $appointment_id = absint( $args[0] );

        $result = $this->service->delete_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Appointment #%d deleted.', $appointment_id ) );
    }

    /**
     * Restore a soft deleted appointment.
     *
     * ## OPTIONS
     *
     * <appointment-id>
     * : Identifier of the appointment to restore.
     */
    public function restore( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Appointment ID is required.' );
        }

        $appointment_id = absint( $args[0] );

        $result = $this->service->restore_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Appointment #%d restored.', $appointment_id ) );
    }
}
