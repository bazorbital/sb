<?php
/**
 * Handles creation and verification of Smooth Booking schema.
 *
 * @package SmoothBooking\Infrastructure\Database
 */

namespace SmoothBooking\Infrastructure\Database;

use SmoothBooking\Infrastructure\Logging\Logger;

/**
 * Manage Smooth Booking database schema using dbDelta.
 */
class SchemaManager {
    /**
     * Option storing schema version.
     */
    public const OPTION_DB_VERSION = 'smooth_booking_db_version';

    /**
     * Schema version.
     */
    private const DB_VERSION = '2024.09.01';

    /**
     * Transient storing schema status.
     */
    public const SCHEMA_STATUS_TRANSIENT = 'smooth_booking_schema_status';

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var SchemaDefinitionBuilder
     */
    private SchemaDefinitionBuilder $definitions;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( \wpdb $wpdb, SchemaDefinitionBuilder $definitions, Logger $logger ) {
        $this->wpdb        = $wpdb;
        $this->definitions = $definitions;
        $this->logger      = $logger;
    }

    /**
     * Ensure schema exists and matches current version.
     */
    public function ensure_schema(): bool {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = $this->wpdb->has_cap( 'collation' ) ? $this->wpdb->get_charset_collate() : '';
        $tables  = $this->definitions->build_tables( $this->wpdb->prefix, $collate );

        dbDelta( implode( "\n", $tables ) );

        $views = $this->definitions->build_views( $this->wpdb->prefix );
        foreach ( $views as $view_name => $sql ) {
            $created = $this->wpdb->query( $sql );
            if ( false === $created ) {
                $this->logger->error( sprintf( 'Failed to create view %s: %s', $view_name, $this->wpdb->last_error ) );
            }
        }

        update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
        delete_transient( self::SCHEMA_STATUS_TRANSIENT );

        return true;
    }

    /**
     * Perform upgrade if stored version differs.
     */
    public function maybe_upgrade(): void {
        $version  = get_option( self::OPTION_DB_VERSION );
        $settings = get_option( 'smooth_booking_settings', [ 'auto_repair_schema' => 1 ] );
        $auto_fix = ! empty( $settings['auto_repair_schema'] );

        if ( self::DB_VERSION !== $version ) {
            $this->ensure_schema();

            return;
        }

        if ( $auto_fix && $this->schema_requires_repair() ) {
            $this->ensure_schema();
        }
    }

    /**
     * Determine if any required tables are missing.
     */
    private function schema_requires_repair(): bool {
        $tables = array_keys( $this->get_table_definitions() );

        foreach ( $tables as $table_key ) {
            if ( ! $this->table_exists( $this->get_table_name( $table_key ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve table definitions array.
     *
     * @return array<string, string>
     */
    public function get_table_definitions(): array {
        $collate = $this->wpdb->has_cap( 'collation' ) ? $this->wpdb->get_charset_collate() : '';

        return $this->definitions->build_tables( $this->wpdb->prefix, $collate );
    }

    /**
     * Retrieve the fully qualified table name for a logical key.
     */
    public function get_table_name( string $key ): string {
        return $this->wpdb->prefix . 'smooth_' . $key;
    }

    /**
     * Determine whether a table exists.
     */
    public function table_exists( string $table ): bool {
        $prepared = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );

        return (bool) $this->wpdb->get_var( $prepared );
    }

    /**
     * Drop all plugin tables and views.
     */
    public function drop_schema(): void {
        $tables = array_keys( $this->get_table_definitions() );

        foreach ( $tables as $key ) {
            $table = $this->get_table_name( $key );
            $table_escaped = esc_sql( $table );
            $this->wpdb->query( "DROP TABLE IF EXISTS `{$table_escaped}`" );
        }

        $views = $this->definitions->build_views( $this->wpdb->prefix );
        foreach ( $views as $view_sql ) {
            if ( preg_match( '/VIEW\s+(\w+)/i', $view_sql, $matches ) ) {
                $view = $matches[1];
                $view_escaped = esc_sql( $view );
                $this->wpdb->query( "DROP VIEW IF EXISTS `{$view_escaped}`" );
            }
        }

        delete_option( self::OPTION_DB_VERSION );
        delete_transient( self::SCHEMA_STATUS_TRANSIENT );
    }
}
