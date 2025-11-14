<?php
/**
 * Ensures Select2 assets are available across Smooth Booking admin screens.
 *
 * @package SmoothBooking\Infrastructure\Assets
 */

namespace SmoothBooking\Infrastructure\Assets;

use SmoothBooking\Infrastructure\Logging\Logger;

use function add_action;
use function wp_register_script;
use function wp_register_style;
use function wp_script_is;
use function wp_style_is;
use function wp_get_theme;

/**
 * Registers Select2 assets for the Smooth Booking admin screens.
 */
class Select2AssetRegistrar {
    private const SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js';

    private const STYLE_URL = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';

    private const VERSION = '4.1.0-rc.0';

    public const SCRIPT_HANDLE = 'smooth-booking-select2';

    public const STYLE_HANDLE = 'smooth-booking-select2';

    private bool $hooks_added = false;

    private bool $style_registered = false;

    private bool $script_registered = false;

    /**
     * Constructor.
     */
    public function __construct( Logger $logger ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
     * Register the Select2 stylesheet.
     */
    private function register_style(): void {
        if ( $this->style_registered || wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
            return;
        }

        wp_register_style(
            self::STYLE_HANDLE,
            self::STYLE_URL,
            [],
            $this->determine_version()
        );

        $this->style_registered = true;
    }

    /**
     * Register the Select2 script.
     */
    private function register_script(): void {
        if ( $this->script_registered || wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
            return;
        }

        wp_register_script(
            self::SCRIPT_HANDLE,
            self::SCRIPT_URL,
            [ 'jquery' ],
            $this->determine_version(),
            true
        );

        $this->script_registered = true;
    }

    /**
     * Determine the appropriate version for cache busting.
     */
    private function determine_version(): string {
        $theme = wp_get_theme();

        if ( $theme && $theme->exists() && $theme->get( 'Version' ) ) {
            return $theme->get( 'Version' );
        }

        return self::VERSION;
    }
}
