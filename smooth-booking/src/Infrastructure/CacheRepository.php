<?php
/**
 * Handles caching metadata.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Infrastructure;

/**
 * Stores transient keys for later invalidation.
 */
class CacheRepository {
/**
 * Saves cache key for later cleanup.
 */
public static function add_key( string $key ): void {
$keys = get_option( 'smooth_booking_cached_calendars', [] );

if ( ! in_array( $key, $keys, true ) ) {
$keys[] = $key;
update_option( 'smooth_booking_cached_calendars', $keys, false );
}
}

/**
 * Returns known cache keys.
 *
 * @return array<int, string>
 */
public static function get_keys(): array {
$keys = get_option( 'smooth_booking_cached_calendars', [] );

return is_array( $keys ) ? $keys : [];
}

/**
 * Flushes all registered caches.
 *
 * @return int Number of deleted transients.
 */
public static function flush(): int {
$keys    = self::get_keys();
$deleted = 0;

foreach ( $keys as $key ) {
if ( delete_transient( $key ) ) {
$deleted++;
}
}

update_option( 'smooth_booking_cached_calendars', [], false );

return $deleted;
}
}
