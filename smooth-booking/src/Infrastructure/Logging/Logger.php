<?php
/**
 * Lightweight logger using error_log.
 *
 * @package SmoothBooking\Infrastructure\Logging
 */

namespace SmoothBooking\Infrastructure\Logging;

use function apply_filters;
use function do_action;
use function error_log;
use function sprintf;
use function strtoupper;

/**
 * Minimal logger abstraction.
 */
class Logger {
    /**
     * Log channel name.
     */
    private string $channel;

    /**
     * Global logging toggle.
     */
    private static bool $enabled = true;

    /**
     * Constructor.
     */
    public function __construct( string $channel ) {
        $this->channel = $channel;
    }

    /**
     * Enable or disable logging globally.
     */
    public static function set_enabled( bool $enabled ): void {
        self::$enabled = $enabled;
    }

    /**
     * Check whether logging is enabled.
     */
    public static function is_enabled(): bool {
        return self::$enabled;
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
     * Determine if the current message should be logged.
     */
    private function should_log( string $level, string $message ): bool {
        $enabled = self::$enabled;

        /**
         * Filter whether a log message should be recorded.
         *
         * @hook smooth_booking_logger_should_log
         * @since 0.16.4
         *
         * @param bool   $enabled Whether logging is enabled.
         * @param string $level   Log level.
         * @param string $message Message content.
         * @param string $channel Channel identifier.
         */
        return (bool) apply_filters( 'smooth_booking_logger_should_log', $enabled, $level, $message, $this->channel );
    }

    /**
     * Write to error log.
     */
    private function write( string $level, string $message ): void {
        if ( ! $this->should_log( $level, $message ) ) {
            return;
        }

        $formatted = sprintf( '[%s] %s: %s', strtoupper( $this->channel ), $level, $message );

        error_log( $formatted );

        /**
         * Fires after a Smooth Booking log message has been written.
         *
         * @hook smooth_booking_logger_logged
         * @since 0.16.4
         *
         * @param string $formatted Formatted log line.
         * @param string $level     Log level string.
         * @param string $message   Raw log message.
         * @param string $channel   Channel identifier.
         */
        do_action( 'smooth_booking_logger_logged', $formatted, $level, $message, $this->channel );
    }
}
