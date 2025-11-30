<?php
/**
 * Shared helpers for formatting calendar event payloads.
 *
 * @package SmoothBooking\Support
 */

namespace SmoothBooking\Support;

use SmoothBooking\Domain\Appointments\Appointment;
use DateTimeZone;
use function __;
use function array_filter;
use function implode;
use function sanitize_hex_color;

/**
 * Provides reusable transformations for EventCalendar payloads.
 */
trait CalendarEventFormatterTrait {
    /**
     * Convert appointments into EventCalendar event payloads.
     *
     * @param Appointment[] $appointments Appointments scheduled for the day.
     * @param DateTimeZone   $timezone    Display timezone.
     *
     * @return array<int,array<string,mixed>>
     */
    private function build_events( array $appointments, DateTimeZone $timezone ): array {
        $events = [];

        foreach ( $appointments as $appointment ) {
            if ( ! $appointment instanceof Appointment ) {
                continue;
            }

            $employee_id = $appointment->get_employee_id();

            if ( null === $employee_id ) {
                continue;
            }

            $start_time = $appointment->get_scheduled_start()->setTimezone( $timezone );
            $end_time   = $appointment->get_scheduled_end()->setTimezone( $timezone );

            $start = $start_time->format( 'Y-m-d H:i:s' );
            $end   = $end_time->format( 'Y-m-d H:i:s' );

            $events[] = [
                'id'            => $appointment->get_id(),
                'resourceId'    => $employee_id,
                'title'         => $appointment->get_service_name() ?: __( 'Appointment', 'smooth-booking' ),
                'start'         => $start,
                'end'           => $end,
                'color'         => $this->normalize_color( $appointment->get_service_background_color() ),
                'textColor'     => $this->normalize_color( $appointment->get_service_text_color(), '#ffffff' ),
                'extendedProps' => [
                    'customer'      => $this->format_customer_name( $appointment ),
                    'timeRange'     => sprintf( '%s – %s', $start_time->format( 'H:i' ), $end_time->format( 'H:i' ) ),
                    'service'       => $appointment->get_service_name(),
                    'serviceId'     => $appointment->get_service_id(),
                    'employee'      => $appointment->get_employee_name(),
                    'status'        => $appointment->get_status(),
                    'customerEmail' => $appointment->get_customer_email(),
                    'customerPhone' => $appointment->get_customer_phone(),
                    'appointmentId' => $appointment->get_id(),
                ],
            ];
        }

        return $events;
    }

    /**
     * Normalise a colour value into a valid hex code.
     */
    private function normalize_color( ?string $color, string $fallback = '#1d4ed8' ): string {
        if ( empty( $color ) ) {
            return $fallback;
        }

        $sanitized = sanitize_hex_color( $color );

        return $sanitized ?: $fallback;
    }

    /**
     * Format the displayed customer name.
     */
    private function format_customer_name( Appointment $appointment ): string {
        $parts = array_filter(
            [
                $appointment->get_customer_first_name(),
                $appointment->get_customer_last_name(),
            ]
        );

        if ( ! empty( $parts ) ) {
            return implode( ' ', $parts );
        }

        if ( $appointment->get_customer_account_name() ) {
            return $appointment->get_customer_account_name();
        }

        return '';
    }
}

