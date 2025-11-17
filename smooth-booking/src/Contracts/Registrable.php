<?php
/**
 * Registrable contract.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking\Contracts;

/**
 * Ensures class can hook into WordPress.
 */
interface Registrable {
/**
 * Register hooks.
 */
public function register(): void;
}
