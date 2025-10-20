<?php
/**
 * WP-CLI command registration.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_CLI_Command;

/**
 * Provides schema commands for WP-CLI.
 */
class SchemaCommand extends WP_CLI_Command {
    /**
     * @var SchemaManager
     */
    private SchemaManager $schema_manager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( SchemaManager $schema_manager, Logger $logger ) {
        $this->schema_manager = $schema_manager;
        $this->logger         = $logger;
    }

    /**
     * Display schema status.
     *
     * ## EXAMPLES
     *
     *     wp smooth schema status
     */
    public function status(): void {
        $tables = $this->schema_manager->get_table_definitions();

        foreach ( array_keys( $tables ) as $key ) {
            $name   = $this->schema_manager->get_table_name( $key );
            $exists = $this->schema_manager->table_exists( $name );
            \WP_CLI::log( sprintf( '%s: %s', $name, $exists ? 'OK' : 'MISSING' ) );
        }
    }

    /**
     * Rebuild schema.
     *
     * ## EXAMPLES
     *
     *     wp smooth schema repair
     */
    public function repair(): void {
        $this->logger->info( 'Running manual schema repair via WP-CLI.' );
        $this->schema_manager->ensure_schema();
        \WP_CLI::success( 'Schema repair completed.' );
    }
}
