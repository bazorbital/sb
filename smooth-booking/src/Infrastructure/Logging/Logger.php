<?php
/**
 * Lightweight logger using error_log.
 *
 * @package SmoothBooking\Infrastructure\Logging
 */

namespace SmoothBooking\Infrastructure\Logging;

/**
 * Minimal logger abstraction.
 */
class Logger {
    /**
     * Log channel name.
     */
    private string $channel;

    /**
     * Constructor.
     */
    public function __construct( string $channel ) {
        $this->channel = $channel;
    }

    /**
     * Log informational message.
     */
    public function info( string $message ): void {
        $this->write( 'INFO', $message );
    }

    /**
     * Log error message.
     */
    public function error( string $message ): void {
        $this->write( 'ERROR', $message );
    }

    /**
     * Write to error log.
     */
    private function write( string $level, string $message ): void {
        error_log( sprintf( '[%s] %s: %s', strtoupper( $this->channel ), $level, $message ) );
    }
}
