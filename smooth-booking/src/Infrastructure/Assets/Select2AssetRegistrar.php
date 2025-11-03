<?php
/**
 * Ensures Select2-compatible assets are available across Smooth Booking admin screens.
 *
 * @package SmoothBooking\Infrastructure\Assets
 */

namespace SmoothBooking\Infrastructure\Assets;

use SmoothBooking\Infrastructure\Logging\Logger;

use function add_action;
use function file_exists;
use function get_bloginfo;
use function includes_url;
use function plugins_url;
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

        $style_url = $this->resolve_style_url();

        wp_register_style(
            self::STYLE_HANDLE,
            $style_url,
            [],
            $this->determine_version()
        );
    }

    /**
     * Register a Select2-compatible script that aliases SelectWoo when available.
     */
    private function register_script(): void {
        if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
            return;
        }

        $dependencies = [ 'jquery' ];

        if ( $this->ensure_core_selectwoo_script() ) {
            $dependencies[] = 'selectWoo';
        }

        wp_register_script(
            self::SCRIPT_HANDLE,
            '',
            $dependencies,
            $this->determine_version(),
            true
        );

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
    private function ensure_core_selectwoo_script(): bool {
        if ( wp_script_is( 'selectWoo', 'registered' ) ) {
            return true;
        }

        $core_path = ABSPATH . WPINC . '/js/dist/vendor/selectWoo/selectWoo.full.min.js';

        if ( ! file_exists( $core_path ) ) {
            $this->logger->info( 'SelectWoo script not found; falling back to noop Select2 alias.' );
            return false;
        }

        wp_register_script(
            'selectWoo',
            includes_url( 'js/dist/vendor/selectWoo/selectWoo.full.min.js' ),
            [ 'jquery' ],
            $this->determine_version(),
            true
        );

        return true;
    }

    /**
     * Resolve the stylesheet URL, preferring the core SelectWoo styles when available.
     */
    private function resolve_style_url(): string {
        $core_path = ABSPATH . WPINC . '/css/dist/vendor/selectWoo/selectWoo.min.css';

        if ( file_exists( $core_path ) ) {
            return includes_url( 'css/dist/vendor/selectWoo/selectWoo.min.css' );
        }

        $this->logger->info( 'SelectWoo stylesheet not found; using Smooth Booking fallback styles.' );

        return plugins_url( 'assets/css/select2-fallback.css', SMOOTH_BOOKING_PLUGIN_FILE );
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
