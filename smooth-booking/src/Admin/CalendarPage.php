<?php
/**
 * Daily calendar admin screen using EventCalendar layout.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use SmoothBooking\Domain\Calendar\CalendarService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Support\CalendarEventFormatterTrait;
use function __;
use function absint;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_user_locale;
use function is_wp_error;
use function plugins_url;
use function sanitize_text_field;
use function selected;
use function sprintf;
use function strtolower;
use function rest_url;
use function wp_add_inline_script;
use function wp_date;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_create_nonce;
use function wp_json_encode;
use function wp_timezone;
use function wp_unslash;

/**
 * Renders the Smooth Booking calendar page using a resource-based day view.
 */
class CalendarPage {
    use AdminStylesTrait;
    use CalendarEventFormatterTrait;

    /** Capability required to view the screen. */
    public const CAPABILITY = 'manage_options';

    /** Menu slug used for routing. */
    public const MENU_SLUG = 'smooth-booking-calendar';

    private CalendarService $calendar;

    private LocationService $locations;

    private Logger $logger;

    public function __construct( CalendarService $calendar, LocationService $locations, Logger $logger ) {
        $this->calendar  = $calendar;
        $this->locations = $locations;
        $this->logger    = $logger;
    }

    /**
     * Render the calendar admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'smooth-booking' ) );
        }

        $locations = $this->locations->list_locations();

        if ( empty( $locations ) ) {
            $this->render_empty_state();
            return;
        }

        $location_id     = $this->determine_location_id( $locations );
        $location        = $this->find_location( $locations, $location_id );
        $timezone        = $this->resolve_timezone( $location );
        $selected_date   = $this->determine_date( $timezone );
        $schedule_result = $this->calendar->get_daily_schedule( $location_id, $selected_date );

        if ( is_wp_error( $schedule_result ) ) {
            $this->logger->error(
                sprintf(
                    'Calendar schedule failed for location #%d on %s: %s',
                    $location_id,
                    $selected_date->format( 'Y-m-d' ),
                    $schedule_result->get_error_message()
                )
            );
        }

        $employees         = [];
        $day_events        = [];
        $events            = [];
        $open_time         = $selected_date->setTime( 8, 0 );
        $close_time        = $open_time->add( new DateInterval( 'PT10H' ) );
        $slot_length       = 30;
        $is_closed         = false;
        $has_day_events    = false;
        $range_start       = $selected_date->sub( new DateInterval( 'P7D' ) );
        $range_end         = $selected_date->add( new DateInterval( 'P7D' ) );
        $view_window       = $this->calendar->build_view_window( $open_time, $close_time );

        if ( ! is_wp_error( $schedule_result ) ) {
            $employees      = $this->unique_employees( $schedule_result['employees'] ?? [] );
            $day_events     = $this->build_events( $schedule_result['appointments'] ?? [], $timezone );
            $events         = $this->build_events(
                $schedule_result['window_appointments'] ?? ( $schedule_result['appointments'] ?? [] ),
                $timezone
            );
            $open_time      = isset( $schedule_result['open'] ) && $schedule_result['open'] instanceof DateTimeImmutable
                ? $schedule_result['open']->setTimezone( $timezone )
                : $open_time;
            $close_time     = isset( $schedule_result['close'] ) && $schedule_result['close'] instanceof DateTimeImmutable
                ? $schedule_result['close']->setTimezone( $timezone )
                : $close_time;
            $slot_length    = isset( $schedule_result['slot_length'] ) ? (int) $schedule_result['slot_length'] : $slot_length;
            $is_closed      = ! empty( $schedule_result['is_closed'] );
            $has_day_events = ! empty( $day_events );
            if ( isset( $schedule_result['window_start'] ) && $schedule_result['window_start'] instanceof DateTimeImmutable ) {
                $range_start = $schedule_result['window_start']->setTimezone( $timezone );
            }

            if ( isset( $schedule_result['window_end'] ) && $schedule_result['window_end'] instanceof DateTimeImmutable ) {
                $range_end = $schedule_result['window_end']->setTimezone( $timezone );
            }

            $view_window = $this->calendar->build_view_window( $open_time, $close_time );
        }

        $resources = $this->calendar->build_resources_payload( $employees );

        $locations_payload = array_values(
            array_filter(
                array_map(
                    static function ( $item ) {
                        return $item instanceof Location
                            ? [ 'id' => $item->get_id(), 'name' => $item->get_name() ]
                            : null;
                    },
                    $locations
                )
            )
        );

        $this->inject_calendar_payload(
            [
                'resources'       => $resources,
                'events'          => $events,
                'selectedDate'    => $selected_date->setTimezone( $timezone )->format( 'Y-m-d' ),
                'openTime'        => $open_time->format( 'H:i:s' ),
                'closeTime'       => $close_time instanceof DateTimeImmutable ? $close_time->format( 'H:i:s' ) : null,
                'scrollTime'      => $view_window['scrollTime'] ?? $open_time->format( 'H:i:s' ),
                'slotDuration'    => $this->calendar->format_slot_duration( $slot_length ),
                'slotMinTime'     => $view_window['slotMinTime'] ?? '06:00:00',
                'slotMaxTime'     => $view_window['slotMaxTime'] ?? '22:00:00',
                'locale'          => $this->get_locale_code(),
                'timezone'        => $timezone->getName(),
                'hasEvents'       => ! empty( $events ),
                'hasSelectedDayEvents' => $has_day_events,
                'rangeStart'      => $range_start->setTimezone( $timezone )->format( 'Y-m-d' ),
                'rangeEnd'        => $range_end->setTimezone( $timezone )->format( 'Y-m-d' ),
                'isClosed'        => $is_closed,
                'locationName'    => $location ? $location->get_name() : '',
                'selectedDateIso' => $selected_date->setTimezone( $timezone )->format( DateTimeInterface::ATOM ),
                'endpoint'        => rest_url( 'smooth-booking/v1/calendar/schedule' ),
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'locations'       => $locations_payload,
                'locationId'      => $location_id,
                'services'        => [],
            ]
        );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Calendar', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php echo esc_html__( 'Review today’s appointments per employee. The view defaults to the current day and uses service colours for each booking.', 'smooth-booking' ); ?></p>
                    </div>
                </div>

                <form method="get" action="<?php echo esc_attr( admin_url( 'admin.php' ) ); ?>" class="smooth-booking-calendar-filters">
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                    <label>
                        <span><?php echo esc_html__( 'Location', 'smooth-booking' ); ?></span>
                        <select name="location_id" id="smooth-booking-calendar-location">
                            <?php foreach ( $locations as $location_item ) : ?>
                                <?php if ( ! $location_item instanceof Location ) { continue; } ?>
                                <option value="<?php echo esc_attr( (string) $location_item->get_id() ); ?>" <?php selected( $location_item->get_id(), $location_id ); ?>><?php echo esc_html( $location_item->get_name() ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Date', 'smooth-booking' ); ?></span>
                        <input type="date" name="calendar_date" id="smooth-booking-calendar-date" value="<?php echo esc_attr( $selected_date->setTimezone( $timezone )->format( 'Y-m-d' ) ); ?>" />
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Employees', 'smooth-booking' ); ?></span>
                        <select name="resource_filter[]" id="smooth-booking-resource-filter" multiple="multiple">
                            <?php foreach ( $resources as $resource ) : ?>
                                <?php if ( empty( $resource['id'] ) ) { continue; } ?>
                                <option value="<?php echo esc_attr( (string) $resource['id'] ); ?>"><?php echo esc_html( $resource['title'] ?? '' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php echo esc_html__( 'Services', 'smooth-booking' ); ?></span>
                        <select id="smooth-booking-service-filter" multiple="multiple"></select>
                    </label>
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Show day', 'smooth-booking' ); ?></button>
                </form>

                <?php if ( is_wp_error( $schedule_result ) ) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html( $schedule_result->get_error_message() ); ?></p></div>
                <?php endif; ?>

                <div class="smooth-booking-calendar-board" id="smooth-booking-calendar-container">
                    <div class="smooth-booking-calendar-meta">
                        <span class="smooth-booking-calendar-meta__item">
                            <?php echo esc_html( sprintf( '%s · %s', $location ? $location->get_name() : __( 'Unknown location', 'smooth-booking' ), $timezone->getName() ) ); ?>
                        </span>
                        <span class="smooth-booking-calendar-meta__item">
                            <?php echo esc_html( wp_date( 'F j, Y', $selected_date->getTimestamp(), $timezone ) ); ?>
                        </span>
                    </div>

                    <?php if ( $is_closed ) : ?>
                        <div class="notice notice-info"><p><?php echo esc_html__( 'The selected location is closed on this day.', 'smooth-booking' ); ?></p></div>
                    <?php endif; ?>

                    <div id="smooth-booking-calendar"></div>
                    <div class="smooth-booking-calendar-empty" data-calendar-empty <?php echo ! empty( $events ) ? 'hidden' : ''; ?>>
                        <p><?php echo esc_html__( 'No appointments scheduled for the selected day.', 'smooth-booking' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue assets for the calendar screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-event-calendar',
            'https://cdn.jsdelivr.net/npm/@event-calendar/build@4.7.1/dist/event-calendar.min.css',
            [],
            '4.7.1'
        );

        wp_enqueue_style(
            'smooth-booking-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0-rc.0'
        );

        wp_enqueue_style(
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/css/admin-calendar.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-event-calendar', 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-event-calendar',
            'https://cdn.jsdelivr.net/npm/@event-calendar/build@4.7.1/dist/event-calendar.min.js',
            [],
            '4.7.1',
            true
        );

        wp_enqueue_script(
            'smooth-booking-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.1.0-rc.0',
            true
        );

        wp_enqueue_script(
            'smooth-booking-admin-calendar',
            plugins_url( 'assets/js/admin-calendar.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'smooth-booking-event-calendar', 'smooth-booking-select2' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $localization = [
            'i18n' => [
                'resourceColumn'    => __( 'Employees', 'smooth-booking' ),
                'customerLabel'     => __( 'Customer', 'smooth-booking' ),
                'emailLabel'        => __( 'Email', 'smooth-booking' ),
                'phoneLabel'        => __( 'Phone', 'smooth-booking' ),
                'timeRangeTemplate' => __( '%1$s – %2$s', 'smooth-booking' ),
                'noEvents'          => __( 'No appointments scheduled for this day.', 'smooth-booking' ),
                'loading'           => __( 'Loading calendar…', 'smooth-booking' ),
                'resourcesView'     => __( 'Resources', 'smooth-booking' ),
                'timelineView'      => __( 'Timeline', 'smooth-booking' ),
            ],
        ];

        $encoded = wp_json_encode( $localization );

        if ( $encoded ) {
            wp_add_inline_script(
                'smooth-booking-admin-calendar',
                'window.SmoothBookingCalendar = window.SmoothBookingCalendar || {}; window.SmoothBookingCalendar = Object.assign(window.SmoothBookingCalendar, ' . $encoded . ');',
                'before'
            );
        }
    }

    /**
     * Render the empty state when no locations exist.
     */
    private function render_empty_state(): void {
        ?>
        <div class="wrap smooth-booking-admin smooth-booking-calendar-wrap">
            <div class="smooth-booking-admin__content">
                <div class="notice notice-warning"><p><?php echo esc_html__( 'Add a location before viewing the calendar.', 'smooth-booking' ); ?></p></div>
            </div>
        </div>
        <?php
    }

    /**
     * Determine which location should be displayed.
     *
     * @param Location[] $locations Available locations.
     */
    private function determine_location_id( array $locations ): int {
        $requested = isset( $_GET['location_id'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_GET['location_id'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $requested > 0 && $this->find_location( $locations, $requested ) ) {
            return $requested;
        }

        foreach ( $locations as $location ) {
            if ( $location instanceof Location ) {
                return $location->get_id();
            }
        }

        return 0;
    }

    /**
     * Determine the date to display.
     */
    private function determine_date( DateTimeZone $timezone ): DateTimeImmutable {
        $param = isset( $_GET['calendar_date'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['calendar_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $param ) {
            $candidate = DateTimeImmutable::createFromFormat( 'Y-m-d', $param, $timezone );

            if ( $candidate instanceof DateTimeImmutable ) {
                return $candidate;
            }
        }

        return new DateTimeImmutable( 'now', $timezone );
    }

    /**
     * Find a location by id.
     *
     * @param Location[] $locations Locations collection.
     */
    private function find_location( array $locations, int $location_id ): ?Location {
        foreach ( $locations as $location ) {
            if ( $location instanceof Location && $location->get_id() === $location_id ) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Normalise employees so each id is represented once.
     *
     * @param array<int,Employee> $employees Employees list.
     *
     * @return Employee[]
     */
    private function unique_employees( array $employees ): array {
        $seen = [];
        $unique = [];

        foreach ( $employees as $employee ) {
            if ( ! $employee instanceof Employee ) {
                continue;
            }

            $id = $employee->get_id();

            if ( isset( $seen[ $id ] ) ) {
                continue;
            }

            $seen[ $id ] = true;
            $unique[]   = $employee;
        }

        return $unique;
    }

    /**
     * Inject payload for the JavaScript calendar bootstrapping.
     *
     * @param array<string,mixed> $payload Data passed to the front-end script.
     */
    private function inject_calendar_payload( array $payload ): void {
        $encoded = wp_json_encode( $payload );

        if ( ! $encoded ) {
            return;
        }

        wp_add_inline_script(
            'smooth-booking-admin-calendar',
            'window.SmoothBookingCalendarData = ' . $encoded . '; window.SmoothBookingCalendar = window.SmoothBookingCalendar || {}; window.SmoothBookingCalendar.data = window.SmoothBookingCalendarData;',
            'before'
        );
    }

    /**
     * Resolve the timezone used for the current location.
     */
    private function resolve_timezone( ?Location $location ): DateTimeZone {
        if ( $location && $location->get_timezone() ) {
            try {
                return new DateTimeZone( $location->get_timezone() );
            } catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                $this->logger->warning(
                    sprintf(
                        'Invalid timezone "%s" for location #%d, falling back to site timezone.',
                        $location->get_timezone(),
                        $location->get_id()
                    )
                );
            }
        }

        return wp_timezone();
    }

    /**
     * Derive the locale code used by EventCalendar.
     */
    private function get_locale_code(): string {
        $locale = get_user_locale();

        return strtolower( str_replace( '_', '-', $locale ) );
    }

}
