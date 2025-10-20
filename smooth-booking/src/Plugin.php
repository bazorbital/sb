<?php
/**
 * Main plugin orchestrator.
 *
 * @package SmoothBooking
 */

namespace SmoothBooking;

use SmoothBooking\Admin\SettingsPage;
use SmoothBooking\Cli\Commands\SchemaCommand;
use SmoothBooking\Cron\CleanupScheduler;
use SmoothBooking\Frontend\Blocks\SchemaStatusBlock;
use SmoothBooking\Frontend\Shortcodes\SchemaStatusShortcode;
use SmoothBooking\Infrastructure\Database\SchemaManager;
use SmoothBooking\Rest\SchemaStatusController;
use SmoothBooking\Support\ServiceContainer;
use SmoothBooking\ServiceProvider;

/**
 * Plugin bootstrapper.
 */
class Plugin {
    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Service container.
     *
     * @var ServiceContainer
     */
    private ServiceContainer $container;

    /**
     * Get singleton instance.
     */
    public static function instance(): Plugin {
        if ( null === static::$instance ) {
            $container = new ServiceContainer();
            $provider = new ServiceProvider();
            $provider->register( $container );

            static::$instance = new Plugin( $container );
        }

        return static::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct( ServiceContainer $container ) {
        $this->container = $container;
    }

    /**
     * Retrieve the service container.
     */
    public function getContainer(): ServiceContainer {
        return $this->container;
    }

    /**
     * Register runtime hooks.
     */
    public function run(): void {
        require_once SMOOTH_BOOKING_PLUGIN_DIR . 'src/Frontend/TemplateTags.php';

        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( CleanupScheduler::EVENT_HOOK, [ $this, 'handle_cleanup_cron' ] );

        /** @var CleanupScheduler $scheduler */
        $scheduler = $this->container->get( CleanupScheduler::class );
        $scheduler->register();

        /** @var SchemaManager $schema_manager */
        $schema_manager = $this->container->get( SchemaManager::class );
        $schema_manager->maybe_upgrade();
    }

    /**
     * Register CLI commands.
     */
    public function register_cli(): void {
        if ( ! class_exists( '\\WP_CLI' ) ) {
            return;
        }

        /** @var SchemaCommand $command */
        $command = $this->container->get( SchemaCommand::class );

        \WP_CLI::add_command( 'smooth schema', $command );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'smooth-booking', false, dirname( plugin_basename( SMOOTH_BOOKING_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Register admin menu page.
     */
    public function register_admin_menu(): void {
        /** @var SettingsPage $settings */
        $settings = $this->container->get( SettingsPage::class );
        $settings->register_menu();
    }

    /**
     * Register Settings API configuration.
     */
    public function register_settings(): void {
        /** @var SettingsPage $settings */
        $settings = $this->container->get( SettingsPage::class );
        $settings->register_settings();
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes(): void {
        /** @var SchemaStatusShortcode $shortcode */
        $shortcode = $this->container->get( SchemaStatusShortcode::class );
        $shortcode->register();
    }

    /**
     * Register Gutenberg blocks.
     */
    public function register_blocks(): void {
        /** @var SchemaStatusBlock $block */
        $block = $this->container->get( SchemaStatusBlock::class );
        $block->register();
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes(): void {
        /** @var SchemaStatusController $controller */
        $controller = $this->container->get( SchemaStatusController::class );
        $controller->register_routes();
    }

    /**
     * Handle cron cleanup event.
     */
    public function handle_cleanup_cron(): void {
        /** @var CleanupScheduler $scheduler */
        $scheduler = $this->container->get( CleanupScheduler::class );
        $scheduler->handle_event();
    }
}
