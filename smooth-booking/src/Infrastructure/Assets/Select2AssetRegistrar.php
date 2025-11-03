<?php
/**
 * Ensures Select2-compatible assets are available across Smooth Booking admin screens.
 *
 * @package SmoothBooking\Infrastructure\Assets
 */

namespace SmoothBooking\Infrastructure\Assets;

use SmoothBooking\Infrastructure\Logging\Logger;

use function add_action;
use function get_bloginfo;
use function includes_url;
use function sprintf;
use function wp_add_inline_script;
use function wp_register_script;
use function wp_register_style;
use function wp_script_is;
use function wp_style_is;

/**
 * Registers Select2 handles backed by WordPress' bundled SelectWoo implementation.
 */
class Select2AssetRegistrar {
    public const SCRIPT_HANDLE = 'smooth-booking-select2';

    public const STYLE_HANDLE = 'smooth-booking-select2';

    private bool $hooks_added = false;

    private bool $inline_added = false;

    private bool $style_registered = false;

    private bool $script_registered = false;

    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Register WordPress hooks for asset registration.
     */
    public function register(): void {
        if ( $this->hooks_added ) {
            return;
        }

        add_action( 'admin_init', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 0 );

        $this->hooks_added = true;
    }

    /**
     * Ensure Select2 handles are registered before they are enqueued.
     */
    public function register_assets(): void {
        $this->register_style();
        $this->register_script();
    }

    /**
     * Register a Select2-compatible stylesheet.
     */
    private function register_style(): void {
        if ( wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
            return;
        }

        $this->register_core_style();

        if ( $this->style_registered ) {
            return;
        }

        wp_register_style(
            self::STYLE_HANDLE,
            false,
            [ 'selectWoo' ],
            $this->determine_version()
        );

        $this->style_registered = true;
    }

    /**
     * Register a Select2-compatible script that aliases SelectWoo when available.
     */
    private function register_script(): void {
        if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
            return;
        }

        $this->register_core_script();

        if ( $this->script_registered ) {
            return;
        }

        wp_register_script(
            self::SCRIPT_HANDLE,
            false,
            [ 'selectWoo' ],
            $this->determine_version(),
            true
        );

        $this->script_registered = true;

        $this->add_inline_alias();
    }

    /**
     * Add the Select2 alias inline script only once.
     */
    private function add_inline_alias(): void {
        if ( $this->inline_added ) {
            return;
        }

        $script = <<<JS
(function( $ ) {
    if ( ! $ || ! $.fn ) {
        return;
    }

    if ( typeof $.fn.select2 === 'function' ) {
        return;
    }

    if ( typeof $.fn.selectWoo === 'function' ) {
        $.fn.select2 = $.fn.selectWoo;
        return;
    }

    $.fn.select2 = function() {
        return this;
    };
})( window.jQuery );
JS;

        wp_add_inline_script( self::SCRIPT_HANDLE, $script );
        $this->inline_added = true;
    }

    /**
     * Ensure the SelectWoo script shipped with WordPress is registered.
     */
    private function register_core_script(): void {
        if ( wp_script_is( 'selectWoo', 'registered' ) ) {
            return;
        }

        $relative_path = $this->get_script_relative_path();

        wp_register_script(
            'selectWoo',
            includes_url( $relative_path ),
            [ 'jquery' ],
            $this->determine_version(),
            true
        );

        $this->logger->info( sprintf( 'Registered SelectWoo script handle using core asset: %s', $relative_path ) );
    }

    /**
     * Resolve the stylesheet URL, preferring the core SelectWoo styles when available.
     */
    private function resolve_style_url(): string {
        return includes_url( $this->get_style_relative_path() );
    }

    /**
     * Register the core SelectWoo stylesheet handle.
     */
    private function register_core_style(): void {
        if ( wp_style_is( 'selectWoo', 'registered' ) ) {
            return;
        }

        wp_register_style(
            'selectWoo',
            $this->resolve_style_url(),
            [],
            $this->determine_version()
        );

        $this->logger->info( sprintf( 'Registered SelectWoo stylesheet using core asset: %s', $this->get_style_relative_path() ) );
    }

    /**
     * Determine the appropriate SelectWoo script relative path.
     */
    private function get_script_relative_path(): string {
        $use_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

        return $use_debug
            ? 'js/dist/vendor/selectWoo/selectWoo.full.js'
            : 'js/dist/vendor/selectWoo/selectWoo.full.min.js';
    }

    /**
     * Determine the appropriate SelectWoo style relative path.
     */
    private function get_style_relative_path(): string {
        $use_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

        return $use_debug
            ? 'css/dist/vendor/selectWoo/selectWoo.css'
            : 'css/dist/vendor/selectWoo/selectWoo.min.css';
    }

    /**
     * Determine an appropriate version string for registered assets.
     */
    private function determine_version(): string {
        $version = get_bloginfo( 'version' );

        if ( empty( $version ) ) {
            return SMOOTH_BOOKING_VERSION;
        }

        return $version;
    }
}
