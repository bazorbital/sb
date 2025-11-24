<?php
/**
 * Appointment repository implementation using wpdb.
 *
 * @package SmoothBooking\Infrastructure\Repository
 */

namespace SmoothBooking\Infrastructure\Repository;

use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use wpdb;
use WP_Error;

use function __;
use function absint;
use function array_map;
use function implode;
use function is_array;
use function in_array;
use function md5;
use function sprintf;
use function current_time;
use function wp_cache_get;
use function wp_cache_set;
use function wp_parse_args;
use function wp_json_encode;
use const ARRAY_A;

/**
 * Provides persistence layer for appointments.
 */
class AppointmentRepository implements AppointmentRepositoryInterface {
    /**
     * Cache group name.
     */
    private const CACHE_GROUP = 'smooth-booking';

    /**
     * Cache ttl.
     */
    private const CACHE_TTL = 300;

    /**
     * Base cache key for pagination queries.
     */
    private const CACHE_KEY_TEMPLATE = 'appointments_%s';

    /**
     * Database handle.
     */
    private wpdb $wpdb;

    /**
     * Logger instance.
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( wpdb $wpdb, Logger $logger ) {
        $this->wpdb   = $wpdb;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function paginate( array $args ): array {
        $defaults = [
            'paged'            => 1,
            'per_page'         => 20,
            'orderby'          => 'scheduled_start',
            'order'            => 'DESC',
            'include_deleted'  => false,
            'only_deleted'     => false,
            'appointment_id'   => null,
            'appointment_from' => null,
            'appointment_to'   => null,
            'created_from'     => null,
            'created_to'       => null,
            'customer_search'  => null,
            'employee_id'      => null,
            'service_id'       => null,
            'status'           => null,
        ];

        $args = wp_parse_args( $args, $defaults );
        $cache_key = $this->get_cache_key( $args );

        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $table      = $this->get_table_name();
        $customers  = $this->get_customer_table();
        $employees  = $this->get_employee_table();
        $services   = $this->get_service_table();
        $conditions = [ "b.booking_type = 'appointment'" ];
        $params     = [];

        if ( ! empty( $args['only_deleted'] ) ) {
            $conditions[] = 'b.is_deleted = %d';
            $params[]     = 1;
        } elseif ( empty( $args['include_deleted'] ) ) {
            $conditions[] = 'b.is_deleted = %d';
            $params[]     = 0;
        }

        if ( ! empty( $args['appointment_id'] ) ) {
            $conditions[] = 'b.booking_id = %d';
            $params[]     = absint( $args['appointment_id'] );
        }

        if ( ! empty( $args['employee_id'] ) ) {
            $conditions[] = 'b.employee_id = %d';
            $params[]     = absint( $args['employee_id'] );
        }

        if ( ! empty( $args['service_id'] ) ) {
            $conditions[] = 'b.service_id = %d';
            $params[]     = absint( $args['service_id'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $conditions[] = 'b.status = %s';
            $params[]     = $args['status'];
        }

        if ( ! empty( $args['customer_search'] ) ) {
            $search          = '%' . $this->wpdb->esc_like( $args['customer_search'] ) . '%';
            $conditions[]    = '(c.name LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)';
            $params[]        = $search;
            $params[]        = $search;
            $params[]        = $search;
        }

        if ( ! empty( $args['appointment_from'] ) ) {
            $conditions[] = 'b.scheduled_start >= %s';
            $params[]     = $args['appointment_from'];
        }

        if ( ! empty( $args['appointment_to'] ) ) {
            $conditions[] = 'b.scheduled_end <= %s';
            $params[]     = $args['appointment_to'];
        }

        if ( ! empty( $args['created_from'] ) ) {
            $conditions[] = 'b.created_at >= %s';
            $params[]     = $args['created_from'];
        }

        if ( ! empty( $args['created_to'] ) ) {
            $conditions[] = 'b.created_at <= %s';
            $params[]     = $args['created_to'];
        }

        $where = '';
        if ( ! empty( $conditions ) ) {
            $where = 'WHERE ' . implode( ' AND ', $conditions );
        }

        $orderby = $this->sanitize_orderby( $args['orderby'] );
        $order   = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $offset  = max( 0, ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit   = absint( $args['per_page'] );

        $sql = "SELECT SQL_CALC_FOUND_ROWS b.*, 
                    c.name AS customer_account_name,
                    c.first_name AS customer_first_name,
                    c.last_name AS customer_last_name,
                    c.phone AS customer_phone,
                    c.email AS customer_email,
                    e.name AS employee_name,
                    s.name AS service_name,
                    s.default_color AS service_background_color,
                    s.default_text_color AS service_text_color
                FROM {$table} AS b
                LEFT JOIN {$customers} AS c ON b.customer_id = c.customer_id
                LEFT JOIN {$employees} AS e ON b.employee_id = e.employee_id
                LEFT JOIN {$services} AS s ON b.service_id = s.service_id
                {$where}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        if ( ! empty( $params ) ) {
            $prepared = $this->wpdb->prepare( $sql, $params );
        } else {
            $prepared = $sql;
        }

        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            $rows = [];
        }

        $appointments = array_map(
            static function ( array $row ): Appointment {
                return Appointment::from_row( $row );
            },
            $rows
        );

        $total = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );

        $result = [
            'appointments' => $appointments,
            'total'        => $total,
        ];

        wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function find_with_deleted( int $appointment_id ): ?Appointment {
        if ( $appointment_id <= 0 ) {
            return null;
        }

        $table     = $this->get_table_name();
        $customers = $this->get_customer_table();
        $employees = $this->get_employee_table();
        $services  = $this->get_service_table();

        $sql = $this->wpdb->prepare(
            "SELECT b.*, 
                    c.name AS customer_account_name,
                    c.first_name AS customer_first_name,
                    c.last_name AS customer_last_name,
                    c.phone AS customer_phone,
                    c.email AS customer_email,
                    e.name AS employee_name,
                    s.name AS service_name,
                    s.default_color AS service_background_color,
                    s.default_text_color AS service_text_color
             FROM {$table} AS b
             LEFT JOIN {$customers} AS c ON b.customer_id = c.customer_id
             LEFT JOIN {$employees} AS e ON b.employee_id = e.employee_id
             LEFT JOIN {$services} AS s ON b.service_id = s.service_id
             WHERE b.booking_id = %d
               AND b.booking_type = %s",
            $appointment_id,
            'appointment'
        );

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        return Appointment::from_row( $row );
    }

    /**
     * {@inheritDoc}
     */
    public function create( array $data ) {
        $table = $this->get_table_name();

        $inserted = $this->wpdb->insert(
            $table,
            [
                'booking_type'     => 'appointment',
                'offering_id'      => $data['service_id'],
                'service_id'       => $data['service_id'],
                'employee_id'      => $data['provider_id'],
                'customer_id'      => $data['customer_id'],
                'scheduled_start'  => $data['scheduled_start']->format( 'Y-m-d H:i:s' ),
                'scheduled_end'    => $data['scheduled_end']->format( 'Y-m-d H:i:s' ),
                'status'           => $data['status'],
                'payment_status'   => $data['payment_status'],
                'notes'            => $data['notes'],
                'internal_note'    => $data['internal_note'],
                'total_amount'     => null === $data['total_amount'] ? null : (float) $data['total_amount'],
                'currency'         => $data['currency'],
                'should_notify'    => $data['should_notify'] ? 1 : 0,
                'is_recurring'     => $data['is_recurring'] ? 1 : 0,
                'customer_email'   => $data['customer_email'],
                'customer_phone'   => $data['customer_phone'],
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ],
            [
                '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s', '%s', '%s',
            ]
        );

        if ( false === $inserted ) {
            $this->logger->error( sprintf( 'Failed to insert appointment: %s', $this->wpdb->last_error ) );

            return new WP_Error(
                'smooth_booking_appointment_insert_failed',
                __( 'Unable to create appointment.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return $this->find_with_deleted( (int) $this->wpdb->insert_id );
    }

    /**
     * {@inheritDoc}
     */
    public function update( int $appointment_id, array $data ) {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'offering_id'     => $data['service_id'],
                'service_id'      => $data['service_id'],
                'employee_id'     => $data['provider_id'],
                'customer_id'     => $data['customer_id'],
                'scheduled_start' => $data['scheduled_start']->format( 'Y-m-d H:i:s' ),
                'scheduled_end'   => $data['scheduled_end']->format( 'Y-m-d H:i:s' ),
                'status'          => $data['status'],
                'payment_status'  => $data['payment_status'],
                'notes'           => $data['notes'],
                'internal_note'   => $data['internal_note'],
                'total_amount'    => null === $data['total_amount'] ? null : (float) $data['total_amount'],
                'currency'        => $data['currency'],
                'should_notify'   => $data['should_notify'] ? 1 : 0,
                'is_recurring'    => $data['is_recurring'] ? 1 : 0,
                'customer_email'  => $data['customer_email'],
                'customer_phone'  => $data['customer_phone'],
                'updated_at'      => current_time( 'mysql' ),
            ],
            [
                'booking_id'   => $appointment_id,
                'booking_type' => 'appointment',
            ],
            [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s', '%s' ],
            [ '%d', '%s' ]
        );

        if ( false === $updated ) {
            $this->logger->error( sprintf( 'Failed to update appointment #%d: %s', $appointment_id, $this->wpdb->last_error ) );

            return new WP_Error(
                'smooth_booking_appointment_update_failed',
                __( 'Unable to update appointment.', 'smooth-booking' )
            );
        }

        $this->flush_cache();

        return $this->find_with_deleted( $appointment_id );
    }

    /**
     * {@inheritDoc}
     */
    public function soft_delete( int $appointment_id ): bool {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 1,
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'booking_id'   => $appointment_id,
                'booking_type' => 'appointment',
            ],
            [ '%d', '%s' ],
            [ '%d', '%s' ]
        );

        if ( false === $updated ) {
            return false;
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function restore( int $appointment_id ): bool {
        $table = $this->get_table_name();

        $updated = $this->wpdb->update(
            $table,
            [
                'is_deleted' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'booking_id'   => $appointment_id,
                'booking_type' => 'appointment',
            ],
            [ '%d', '%s' ],
            [ '%d', '%s' ]
        );

        if ( false === $updated ) {
            return false;
        }

        $this->flush_cache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get_for_employees_in_range( array $employee_ids, string $from, string $to ): array {
        $ids = array_values(
            array_filter(
                array_map( 'absint', $employee_ids ),
                static function ( int $id ): bool {
                    return $id > 0;
                }
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $cache_key = sprintf(
            self::CACHE_KEY_TEMPLATE,
            'calendar_' . md5( wp_json_encode( [ $ids, $from, $to ] ) )
        );

        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $table     = $this->get_table_name();
        $customers = $this->get_customer_table();
        $employees = $this->get_employee_table();
        $services  = $this->get_service_table();

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $sql = "SELECT b.*,\n                    c.name AS customer_account_name,\n                    c.first_name AS customer_first_name,\n                    c.last_name AS customer_last_name,\n                    c.phone AS customer_phone,\n                    c.email AS customer_email,\n                    e.name AS employee_name,\n                    s.name AS service_name,\n                    s.default_color AS service_background_color,\n                    s.default_text_color AS service_text_color\n                FROM {$table} AS b\n                LEFT JOIN {$customers} AS c ON b.customer_id = c.customer_id\n                LEFT JOIN {$employees} AS e ON b.employee_id = e.employee_id\n                LEFT JOIN {$services} AS s ON b.service_id = s.service_id\n                WHERE b.booking_type = 'appointment'\n                    AND b.is_deleted = %d\n                    AND b.employee_id IN ({$placeholders})\n                    AND b.scheduled_start >= %s\n                    AND b.scheduled_end <= %s\n                ORDER BY b.scheduled_start ASC";

        $params = array_merge( [ 0 ], $ids, [ $from, $to ] );

        $prepared = $this->wpdb->prepare( $sql, $params );

        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            $rows = [];
        }

        $appointments = array_map(
            static function ( array $row ): Appointment {
                return Appointment::from_row( $row );
            },
            $rows
        );

        wp_cache_set( $cache_key, $appointments, self::CACHE_GROUP, self::CACHE_TTL );

        return $appointments;
    }

    /**
     * Generate cache key based on args. based on args.
     *
     * @param array<string, mixed> $args Query args.
     */
    private function get_cache_key( array $args ): string {
        $version = wp_cache_get( 'appointments_version', self::CACHE_GROUP );

        if ( false === $version ) {
            $version = time();
            wp_cache_set( 'appointments_version', $version, self::CACHE_GROUP, self::CACHE_TTL );
        }

        $hash = md5( wp_json_encode( $args ) );

        return sprintf( self::CACHE_KEY_TEMPLATE, $version . '_' . $hash );
    }

    /**
     * Flush cache entries for appointments.
     */
    private function flush_cache(): void {
        wp_cache_set( 'appointments_version', time(), self::CACHE_GROUP, self::CACHE_TTL );
    }

    /**
     * Normalize order by value.
     */
    private function sanitize_orderby( string $orderby ): string {
        $allowed = [ 'scheduled_start', 'scheduled_end', 'created_at', 'status', 'payment_status', 'booking_id' ];

        if ( in_array( $orderby, $allowed, true ) ) {
            return 'b.' . $orderby;
        }

        return 'b.scheduled_start';
    }

    /**
     * Retrieve bookings table name.
     */
    private function get_table_name(): string {
        return $this->wpdb->prefix . 'smooth_bookings';
    }

    /**
     * Retrieve customer table.
     */
    private function get_customer_table(): string {
        return $this->wpdb->prefix . 'smooth_customers';
    }

    /**
     * Retrieve employee table name.
     */
    private function get_employee_table(): string {
        return $this->wpdb->prefix . 'smooth_employees';
    }

    /**
     * Retrieve service table name.
     */
    private function get_service_table(): string {
        return $this->wpdb->prefix . 'smooth_services';
    }
}
