<?php
/**
 * Domain service providing schema status information.
 *
 * @package SmoothBooking\Domain
 */

namespace SmoothBooking\Domain;

use SmoothBooking\Infrastructure\Database\SchemaManager;
use WP_Error;

/**
 * Provides information about Smooth Booking database schema.
 */
class SchemaStatusService {
    /**
     * Cache lifetime for schema status (in seconds).
     */
    private const CACHE_TTL = 300;

    /**
     * @var SchemaManager
     */
    private SchemaManager $schema_manager;

    /**
     * Constructor.
     */
    public function __construct( SchemaManager $schema_manager ) {
        $this->schema_manager = $schema_manager;
    }

    /**
     * Retrieve schema status, optionally bypassing cache.
     *
     * @param bool $force_refresh Whether to skip cache lookup.
     *
     * @return array<string, bool>|WP_Error
     */
    public function get_status( bool $force_refresh = false ) {
        $cache_key = SchemaManager::SCHEMA_STATUS_TRANSIENT;

        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $tables = $this->schema_manager->get_table_definitions();
        $status = [];

        foreach ( array_keys( $tables ) as $table_key ) {
            $name           = $this->schema_manager->get_table_name( $table_key );
            $status[ $name ] = $this->schema_manager->table_exists( $name );
        }

        if ( empty( $status ) ) {
            return new WP_Error( 'smooth_booking_schema_missing', __( 'No Smooth Booking tables are defined.', 'smooth-booking' ) );
        }

        set_transient( $cache_key, $status, self::CACHE_TTL );

        return $status;
    }

    /**
     * Determine if all tables exist.
     */
    public function schema_is_healthy(): bool {
        $status = $this->get_status();

        if ( is_wp_error( $status ) ) {
            return false;
        }

        foreach ( $status as $exists ) {
            if ( true !== $exists ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Trigger schema repair.
     */
    public function repair_schema(): void {
        $this->schema_manager->ensure_schema();
    }
}
