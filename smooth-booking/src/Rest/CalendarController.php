<?php
/**
 * REST controller for calendar schedule payloads.
 *
 * @package SmoothBooking\Rest
 */

namespace SmoothBooking\Rest;

use DateTimeImmutable;
use DateTimeZone;
use SmoothBooking\Admin\CalendarPage;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;
use SmoothBooking\Support\CalendarEventFormatterTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function absint;
use function array_filter;
use function array_map;
use function current_user_can;
use function in_array;
use function is_array;
use function is_wp_error;
use function preg_match;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_timezone;
use function wp_unslash;

/**
 * Exposes schedule information for the EventCalendar admin view.
 */
class CalendarController {
    use CalendarEventFormatterTrait;

    private const NAMESPACE = 'smooth-booking/v1';

    private const ROUTE = '/calendar/schedule';

    private CalendarService $calendar;

    private ServiceService $services;

    private LocationService $locations;

    private GeneralSettings $settings;

    public function __construct( CalendarService $calendar, ServiceService $services, LocationService $locations, GeneralSettings $settings ) {
        $this->calendar  = $calendar;
        $this->services  = $services;
        $this->locations = $locations;
        $this->settings  = $settings;
    }

    /**
     * Register the REST API routes.
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_schedule' ],
                    'permission_callback' => [ $this, 'can_view_calendar' ],
                ],
            ]
        );
    }

    /**
     * Provide the calendar schedule for the requested date and location.
     */
    public function get_schedule( WP_REST_Request $request ): WP_REST_Response {
        $location_id = absint( $request->get_param( 'location_id' ) );
        $locations   = $this->locations->list_locations();

        if ( $location_id <= 0 ) {
            $location_id = $this->determine_default_location( $locations );
        }

        $date_param = $request->get_param( 'date' );
        $date_value = $date_param ? sanitize_text_field( wp_unslash( (string) $date_param ) ) : '';
        $fallback   = new DateTimeImmutable( 'now', wp_timezone() instanceof DateTimeZone ? wp_timezone() : new DateTimeZone( 'UTC' ) );
        $selected   = $date_value
            ? DateTimeImmutable::createFromFormat( 'Y-m-d', $date_value, $fallback->getTimezone() ) ?: $fallback
            : $fallback;

        $schedule = $this->calendar->get_daily_schedule( $location_id, $selected );

        if ( is_wp_error( $schedule ) ) {
            return new WP_REST_Response( [ 'message' => $schedule->get_error_message() ], 400 );
        }

        $timezone = $schedule['open'] instanceof DateTimeImmutable ? $schedule['open']->getTimezone() : wp_timezone();
        if ( ! $timezone instanceof DateTimeZone ) {
            $timezone = new DateTimeZone( 'UTC' );
        }

        $resource_ids = $this->sanitize_ids( $request->get_param( 'resource_ids' ) );
        $employees    = $this->filter_employees( $schedule['employees'] ?? [], $resource_ids );
        $resources    = $this->calendar->build_resources_payload( $employees );
        $appointments = $schedule['appointments'] ?? [];

        $events = $this->build_events( $appointments, $timezone );

        if ( ! empty( $resource_ids ) ) {
            $events = array_values(
                array_filter(
                    $events,
                    static function ( array $event ) use ( $resource_ids ): bool {
                        return in_array( (int) $event['resourceId'], $resource_ids, true );
                    }
                )
            );
        }

        $open_time   = $schedule['open'] instanceof DateTimeImmutable ? $schedule['open'] : $selected->setTime( 8, 0 );
        $close_time  = $schedule['close'] instanceof DateTimeImmutable ? $schedule['close'] : $open_time->add( new \DateInterval( 'PT10H' ) );
        $view_window = $this->calendar->build_view_window( $open_time, $close_time );

        $service_templates = $this->build_service_templates( $resources );

        $slot_length = isset( $schedule['slot_length'] )
            ? (int) $schedule['slot_length']
            : $this->settings->get_time_slot_length();

        return rest_ensure_response(
            [
                'date'           => $selected->setTimezone( $timezone )->format( 'Y-m-d' ),
                'timezone'       => $timezone->getName(),
                'location'       => $this->resolve_location_payload( $locations, $location_id ),
                'resources'      => $resources,
                'events'         => $events,
                'openTime'       => $open_time->setTimezone( $timezone )->format( 'H:i:s' ),
                'closeTime'      => $close_time->setTimezone( $timezone )->format( 'H:i:s' ),
                'slotMinTime'    => $view_window['slotMinTime'] ?? '06:00:00',
                'slotMaxTime'    => $view_window['slotMaxTime'] ?? '22:00:00',
                'scrollTime'     => $view_window['scrollTime'] ?? '08:00:00',
                'slotDuration'   => $this->calendar->format_slot_duration( $slot_length ),
                'slotLengthMinutes' => $slot_length,
                'services'       => $service_templates,
                'resourceLookup' => $resource_ids,
            ]
        );
    }

    /**
     * Capability callback for schedule endpoints.
     */
    public function can_view_calendar(): bool {
        return current_user_can( CalendarPage::CAPABILITY );
    }

    /**
     * @param Location[] $locations Locations collection.
     */
    private function determine_default_location( array $locations ): int {
        foreach ( $locations as $location ) {
            if ( $location instanceof Location ) {
                return $location->get_id();
            }
        }

        return 0;
    }

    /**
     * @param Location[] $locations Locations collection.
     */
    private function resolve_location_payload( array $locations, int $location_id ): array {
        foreach ( $locations as $location ) {
            if ( $location instanceof Location && $location->get_id() === $location_id ) {
                return [
                    'id'   => $location->get_id(),
                    'name' => $location->get_name(),
                ];
            }
        }

        return [ 'id' => $location_id, 'name' => '' ];
    }

    /**
     * @param array<int,array<string,mixed>>|null $value Raw request parameter.
     *
     * @return int[]
     */
    private function sanitize_ids( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map( static fn( $id ) => absint( $id ), $value )
            )
        );
    }

    /**
     * Filter employees if a resource selection was provided.
     *
     * @param array<int,mixed> $employees Raw employees list.
     * @param int[]            $resource_ids Selected resource identifiers.
     *
     * @return array<int,mixed>
     */
    private function filter_employees( array $employees, array $resource_ids ): array {
        if ( empty( $resource_ids ) ) {
            return $employees;
        }

        return array_values(
            array_filter(
                $employees,
                static function ( $employee ) use ( $resource_ids ): bool {
                    return isset( $employee ) && method_exists( $employee, 'get_id' )
                        ? in_array( (int) $employee->get_id(), $resource_ids, true )
                        : false;
                }
            )
        );
    }

    /**
     * Build service template collection keyed by service id.
     *
     * @param array<int,array<string,mixed>> $resources Calendar resources payload.
     *
     * @return array<int,array<string,mixed>>
     */
    private function build_service_templates( array $resources ): array {
        $service_ids = [];

        foreach ( $resources as $resource ) {
            if ( empty( $resource['serviceIds'] ) || ! is_array( $resource['serviceIds'] ) ) {
                continue;
            }
            $service_ids = array_merge( $service_ids, $resource['serviceIds'] );
        }

        $service_ids = array_values( array_unique( array_map( 'absint', $service_ids ) ) );

        if ( empty( $service_ids ) ) {
            return [];
        }

        $templates = [];
        $services  = $this->services->list_services();

        foreach ( $services as $service ) {
            if ( ! $service instanceof Service || ! in_array( $service->get_id(), $service_ids, true ) ) {
                continue;
            }

            $templates[ $service->get_id() ] = [
                'id'              => $service->get_id(),
                'name'            => $service->get_name(),
                'durationMinutes' => $this->duration_to_minutes( $service->get_duration_key() ),
                'color'           => $this->normalize_color( $service->get_background_color() ),
                'textColor'       => $this->normalize_color( $service->get_text_color(), '#111827' ),
            ];
        }

        return $templates;
    }

    /**
     * Convert duration keys into minute values.
     */
    private function duration_to_minutes( string $key ): int {
        if ( preg_match( '/^(\d+)_minutes$/', $key, $matches ) ) {
            return (int) $matches[1];
        }

        $map = [
            'one_day'    => 1440,
            'two_days'   => 2880,
            'three_days' => 4320,
            'four_days'  => 5760,
            'five_days'  => 7200,
            'six_days'   => 8640,
            'one_week'   => 10080,
        ];

        return $map[ $key ] ?? 30;
    }
}

