<?php
/**
 * Services administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceCategory;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Domain\Services\ServiceTag;
use WP_Error;

use function __;
use function absint;
use function add_query_arg;
use function admin_url;
use function array_key_exists;
use function array_map;
use function check_admin_referer;
use function checked;
use function current_user_can;
use function date_i18n;
use function delete_transient;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function get_current_user_id;
use function get_option;
use function get_transient;
use function in_array;
use function is_wp_error;
use function number_format;
use function number_format_i18n;
use function plugins_url;
use function sanitize_key;
use function selected;
use function set_transient;
use function strtotime;
use function submit_button;
use function wp_die;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_get_attachment_image;

/**
 * Renders and handles the services management interface.
 */
class ServicesPage {
    /**
     * Capability required to manage services.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Menu slug used for the services screen.
     */
    public const MENU_SLUG = 'smooth-booking';

    /**
     * Transient key template for notices.
     */
    private const NOTICE_TRANSIENT_TEMPLATE = 'smooth_booking_service_notice_%d';

    /**
     * @var ServiceService
     */
    private ServiceService $services;

    /**
     * @var EmployeeService
     */
    private EmployeeService $employees;

    /**
     * Constructor.
     */
    public function __construct( ServiceService $services, EmployeeService $employees ) {
        $this->services  = $services;
        $this->employees = $employees;
    }

    /**
     * Render the services admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage services.', 'smooth-booking' ) );
        }

        $notice       = $this->consume_notice();
        $action       = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $service_id   = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0;
        $view         = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : 'active';
        $show_deleted = 'deleted' === $view;

        $editing_service = null;
        $editing_error   = null;

        if ( 'edit' === $action && $service_id > 0 ) {
            $service = $this->services->get_service( $service_id );

            if ( is_wp_error( $service ) ) {
                $editing_error = $service->get_error_message();
            } else {
                $editing_service = $service;
            }
        }

        $services = $this->services->list_services(
            [
                'only_deleted' => $show_deleted,
            ]
        );

        $categories = $this->services->list_categories();
        $tags       = $this->services->list_tags();
        $employees  = $this->employees->list_employees();

        $should_open_form   = $editing_service instanceof Service;
        $form_container_id  = 'smooth-booking-service-form-panel';
        $open_label         = __( 'Add new service', 'smooth-booking' );
        $close_label        = __( 'Close form', 'smooth-booking' );

        ?>
        <div class="wrap smooth-booking-services-wrap">
            <div class="smooth-booking-admin-header">
                <div class="smooth-booking-admin-header__content">
                    <h1><?php echo esc_html__( 'Services', 'smooth-booking' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'Manage services, their availability, and provider preferences.', 'smooth-booking' ); ?></p>
                </div>
                <div class="smooth-booking-admin-header__actions">
                    <button type="button" class="button button-primary smooth-booking-open-form" data-target="service-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <span class="smooth-booking-open-form__label"><?php echo esc_html( $should_open_form ? $close_label : $open_label ); ?></span>
                    </button>
                </div>
            </div>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $editing_error ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $editing_error ); ?></p>
                </div>
            <?php endif; ?>

            <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-service-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="service-form" data-focus-selector="#smooth-booking-service-name"<?php echo $should_open_form ? '' : ' hidden'; ?>>
                <?php $this->render_service_form( $editing_service, $categories, $tags, $employees ); ?>
            </div>

            <h2><?php echo esc_html__( 'Service list', 'smooth-booking' ); ?></h2>

            <div class="smooth-booking-toolbar">
                <?php if ( $show_deleted ) : ?>
                    <a class="button" href="<?php echo esc_url( $this->get_view_link( 'active' ) ); ?>">
                        <?php esc_html_e( 'Back to active services', 'smooth-booking' ); ?>
                    </a>
                <?php else : ?>
                    <a class="button" href="<?php echo esc_url( $this->get_view_link( 'deleted' ) ); ?>">
                        <?php esc_html_e( 'Show deleted services', 'smooth-booking' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( $show_deleted ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Deleted services can be restored from this view.', 'smooth-booking' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="smooth-booking-services-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Service', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Visibility', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Price', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Duration', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Providers', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Categories', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Tags', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Updated', 'smooth-booking' ); ?></th>
                            <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $services ) ) : ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e( 'No services have been added yet.', 'smooth-booking' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php
                        $employee_map = [];
                        foreach ( $employees as $employee ) {
                            if ( $employee instanceof Employee ) {
                                $employee_map[ $employee->get_id() ] = $employee->get_name();
                            }
                        }
                        ?>
                        <?php foreach ( $services as $service ) : ?>
                            <tr>
                                <td>
                                    <div class="smooth-booking-service-name-cell">
                                        <?php echo $this->get_service_image_html( $service ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <span class="smooth-booking-service-name-text"><?php echo esc_html( $service->get_name() ); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html( ucfirst( $service->get_visibility() ) ); ?></td>
                                <td><?php echo esc_html( $this->format_price( $service->get_price() ) ); ?></td>
                                <td><?php echo esc_html( $this->get_duration_label( $service->get_duration_key() ) ); ?></td>
                                <td>
                                    <?php
                                    $provider_labels = [];
                                    foreach ( $service->get_providers() as $provider ) {
                                        $provider_labels[] = $employee_map[ $provider['employee_id'] ] ?? sprintf( __( 'Employee #%d', 'smooth-booking' ), $provider['employee_id'] );
                                    }
                                    echo $provider_labels ? esc_html( implode( ', ', $provider_labels ) ) : esc_html__( 'Not assigned', 'smooth-booking' );
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $service_categories = $service->get_categories();
                                    if ( empty( $service_categories ) ) {
                                        esc_html_e( '—', 'smooth-booking' );
                                    } else {
                                        echo esc_html( implode( ', ', array_map(
                                            static function ( ServiceCategory $category ): string {
                                                return $category->get_name();
                                            },
                                            $service_categories
                                        ) ) );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $service_tags = $service->get_tags();
                                    if ( empty( $service_tags ) ) {
                                        esc_html_e( '—', 'smooth-booking' );
                                    } else {
                                        echo esc_html( implode( ', ', array_map(
                                            static function ( ServiceTag $tag ): string {
                                                return $tag->get_name();
                                            },
                                            $service_tags
                                        ) ) );
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( $this->format_datetime( $service->get_updated_at()->format( 'Y-m-d H:i:s' ) ) ); ?></td>
                                <td class="smooth-booking-actions-cell">
                                    <div class="smooth-booking-actions-menu" data-service-id="<?php echo esc_attr( (string) $service->get_id() ); ?>">
                                        <button type="button" class="button button-link smooth-booking-actions-toggle" aria-haspopup="true" aria-expanded="false">
                                            <span class="dashicons dashicons-ellipsis"></span>
                                            <span class="screen-reader-text"><?php esc_html_e( 'Open actions menu', 'smooth-booking' ); ?></span>
                                        </button>
                                        <ul class="smooth-booking-actions-list" hidden>
                                            <?php if ( ! $show_deleted ) : ?>
                                                <li>
                                                    <a class="smooth-booking-actions-link" href="<?php echo esc_url( $this->get_edit_link( $service->get_id() ) ); ?>">
                                                        <?php esc_html_e( 'Edit', 'smooth-booking' ); ?>
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                        <?php wp_nonce_field( 'smooth_booking_delete_service', '_smooth_booking_delete_service_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_delete_service" />
                                                        <input type="hidden" name="service_id" value="<?php echo esc_attr( (string) $service->get_id() ); ?>" />
                                                        <input type="hidden" name="current_view" value="<?php echo esc_attr( $show_deleted ? 'deleted' : 'active' ); ?>" />
                                                        <button type="submit" class="smooth-booking-actions-link delete-link" data-confirm-message="<?php echo esc_attr( __( 'Are you sure you want to delete this service?', 'smooth-booking' ) ); ?>">
                                                            <?php esc_html_e( 'Delete', 'smooth-booking' ); ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php else : ?>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-restore-form">
                                                        <?php wp_nonce_field( 'smooth_booking_restore_service', '_smooth_booking_restore_service_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_restore_service" />
                                                        <input type="hidden" name="service_id" value="<?php echo esc_attr( (string) $service->get_id() ); ?>" />
                                                        <input type="hidden" name="current_view" value="deleted" />
                                                        <button type="submit" class="smooth-booking-actions-link">
                                                            <?php esc_html_e( 'Restore', 'smooth-booking' ); ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }


    /**
     * Handle service creation and updates.
     */
    public function handle_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage services.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_service', '_smooth_booking_save_service_nonce' );

        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

        $providers = [];
        if ( isset( $_POST['service_providers'] ) && is_array( $_POST['service_providers'] ) ) {
            foreach ( $_POST['service_providers'] as $provider ) {
                $provider = (array) $provider;
                $selected = isset( $provider['selected'] ) ? (int) $provider['selected'] : 0;
                if ( ! $selected ) {
                    continue;
                }
                $providers[] = [
                    'employee_id' => isset( $provider['employee_id'] ) ? absint( $provider['employee_id'] ) : 0,
                    'order'       => isset( $provider['order'] ) ? (int) $provider['order'] : 0,
                ];
            }
        }

        $data = [
            'name'                         => isset( $_POST['service_name'] ) ? wp_unslash( (string) $_POST['service_name'] ) : '',
            'profile_image_id'             => isset( $_POST['service_profile_image_id'] ) ? wp_unslash( (string) $_POST['service_profile_image_id'] ) : '0',
            'default_color'                => isset( $_POST['service_default_color'] ) ? wp_unslash( (string) $_POST['service_default_color'] ) : '',
            'visibility'                   => isset( $_POST['service_visibility'] ) ? wp_unslash( (string) $_POST['service_visibility'] ) : 'public',
            'price'                        => isset( $_POST['service_price'] ) ? wp_unslash( (string) $_POST['service_price'] ) : '',
            'payment_methods_mode'         => isset( $_POST['service_payment_methods_mode'] ) ? wp_unslash( (string) $_POST['service_payment_methods_mode'] ) : 'default',
            'info'                         => isset( $_POST['service_info'] ) ? wp_unslash( (string) $_POST['service_info'] ) : '',
            'providers_preference'         => isset( $_POST['service_providers_preference'] ) ? wp_unslash( (string) $_POST['service_providers_preference'] ) : 'specified_order',
            'providers_random_tie'         => isset( $_POST['service_providers_random_tie'] ) ? wp_unslash( (string) $_POST['service_providers_random_tie'] ) : 'disabled',
            'occupancy_period_before'      => isset( $_POST['service_occupancy_period_before'] ) ? wp_unslash( (string) $_POST['service_occupancy_period_before'] ) : '0',
            'occupancy_period_after'       => isset( $_POST['service_occupancy_period_after'] ) ? wp_unslash( (string) $_POST['service_occupancy_period_after'] ) : '0',
            'duration_key'                 => isset( $_POST['service_duration'] ) ? wp_unslash( (string) $_POST['service_duration'] ) : '15_minutes',
            'slot_length_key'              => isset( $_POST['service_slot_length'] ) ? wp_unslash( (string) $_POST['service_slot_length'] ) : 'default',
            'padding_before_key'           => isset( $_POST['service_padding_before'] ) ? wp_unslash( (string) $_POST['service_padding_before'] ) : 'off',
            'padding_after_key'            => isset( $_POST['service_padding_after'] ) ? wp_unslash( (string) $_POST['service_padding_after'] ) : 'off',
            'online_meeting_provider'      => isset( $_POST['service_online_meeting'] ) ? wp_unslash( (string) $_POST['service_online_meeting'] ) : 'off',
            'limit_per_customer'           => isset( $_POST['service_limit_per_customer'] ) ? wp_unslash( (string) $_POST['service_limit_per_customer'] ) : 'off',
            'final_step_url_enabled'       => isset( $_POST['service_final_step_url_mode'] ) ? wp_unslash( (string) $_POST['service_final_step_url_mode'] ) : 'disabled',
            'final_step_url'               => isset( $_POST['service_final_step_url'] ) ? wp_unslash( (string) $_POST['service_final_step_url'] ) : '',
            'min_time_prior_booking_key'   => isset( $_POST['service_min_time_booking'] ) ? wp_unslash( (string) $_POST['service_min_time_booking'] ) : 'default',
            'min_time_prior_cancel_key'    => isset( $_POST['service_min_time_cancel'] ) ? wp_unslash( (string) $_POST['service_min_time_cancel'] ) : 'default',
            'category_ids'                 => isset( $_POST['service_category_ids'] ) ? array_map( 'wp_unslash', (array) $_POST['service_category_ids'] ) : [],
            'new_categories'               => isset( $_POST['service_new_categories'] ) ? wp_unslash( (string) $_POST['service_new_categories'] ) : '',
            'tag_ids'                      => isset( $_POST['service_tag_ids'] ) ? array_map( 'wp_unslash', (array) $_POST['service_tag_ids'] ) : [],
            'new_tags'                     => isset( $_POST['service_new_tags'] ) ? wp_unslash( (string) $_POST['service_new_tags'] ) : '',
            'providers'                    => $providers,
        ];

        $result = $service_id > 0
            ? $this->services->update_service( $service_id, $data )
            : $this->services->create_service( $data );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );

            $redirect = $service_id > 0
                ? $this->get_edit_link( $service_id )
                : $this->get_base_page();

            wp_safe_redirect( $redirect );
            exit;
        }

        $message = $service_id > 0
            ? __( 'Service updated successfully.', 'smooth-booking' )
            : __( 'Service created successfully.', 'smooth-booking' );

        $this->add_notice( 'success', $message );

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Handle delete requests.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage services.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_service', '_smooth_booking_delete_service_nonce' );

        $service_id    = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'active';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $service_id ) {
            $this->add_notice( 'error', __( 'Missing service identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->services->delete_service( $service_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Service deleted.', 'smooth-booking' ) );
        wp_safe_redirect( $redirect_link );
        exit;
    }

    /**
     * Handle service restoration.
     */
    public function handle_restore(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage services.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_restore_service', '_smooth_booking_restore_service_nonce' );

        $service_id    = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'deleted';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $service_id ) {
            $this->add_notice( 'error', __( 'Missing service identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->services->restore_service( $service_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Service restored.', 'smooth-booking' ) );
        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( string $hook ): void {
        $allowed_hooks = [
            'toplevel_page_' . self::MENU_SLUG,
            'smooth-booking_page_' . self::MENU_SLUG,
        ];

        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_media();

        wp_enqueue_style(
            'smooth-booking-admin-services',
            plugins_url( 'assets/css/admin-services.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-admin-services',
            plugins_url( 'assets/js/admin-services.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'wp-color-picker', 'media-editor' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $settings = [
            'confirmDelete'   => __( 'Are you sure you want to delete this service?', 'smooth-booking' ),
            'chooseImage'     => __( 'Select service image', 'smooth-booking' ),
            'useImage'        => __( 'Use image', 'smooth-booking' ),
            'removeImage'     => __( 'Remove image', 'smooth-booking' ),
            'selectAllLabel'  => __( 'Select all providers', 'smooth-booking' ),
            'placeholderHtml' => $this->get_service_image_html( null ),
        ];

        wp_localize_script( 'smooth-booking-admin-services', 'SmoothBookingServices', $settings );
    }


    /**
     * Render the service form.
     *
     * @param Service|null      $service    Service being edited.
     * @param ServiceCategory[] $categories Available categories.
     * @param ServiceTag[]      $tags       Available tags.
     * @param Employee[]        $employees  Available employees.
     */
    private function render_service_form( ?Service $service, array $categories, array $tags, array $employees ): void {
        $is_edit = $service instanceof Service;

        $selected_categories = $is_edit ? array_map(
            static function ( ServiceCategory $category ): int {
                return $category->get_id();
            },
            $service->get_categories()
        ) : [];

        $selected_tags = $is_edit ? array_map(
            static function ( ServiceTag $tag ): int {
                return $tag->get_id();
            },
            $service->get_tags()
        ) : [];

        $provider_map = [];
        if ( $is_edit ) {
            foreach ( $service->get_providers() as $provider ) {
                $provider_map[ $provider['employee_id'] ] = $provider['order'];
            }
        } else {
            $index = 1;
            foreach ( $employees as $employee ) {
                if ( $employee instanceof Employee ) {
                    $provider_map[ $employee->get_id() ] = $index++;
                }
            }
        }

        $profile_image_id    = $is_edit ? $service->get_image_id() : null;
        $providers_preference = $is_edit ? $service->get_providers_preference() : 'specified_order';
        $random_tie           = $is_edit ? ( $service->is_providers_random_tie() ? 'enabled' : 'disabled' ) : 'disabled';
        $occupancy_before     = $is_edit ? $service->get_occupancy_period_before() : 0;
        $occupancy_after      = $is_edit ? $service->get_occupancy_period_after() : 0;

        $visibility   = $is_edit ? $service->get_visibility() : 'public';
        $price        = $is_edit && null !== $service->get_price() ? number_format( (float) $service->get_price(), 2, '.', '' ) : '';
        $color        = $is_edit ? ( $service->get_color() ?? '' ) : '';
        $info         = $is_edit ? ( $service->get_info() ?? '' ) : '';
        $payment_mode = $is_edit ? $service->get_payment_methods_mode() : 'default';
        $duration     = $is_edit ? $service->get_duration_key() : '15_minutes';
        $slot_length  = $is_edit ? $service->get_slot_length_key() : 'default';
        $padding_before = $is_edit ? $service->get_padding_before_key() : 'off';
        $padding_after  = $is_edit ? $service->get_padding_after_key() : 'off';
        $online_meeting = $is_edit ? $service->get_online_meeting_provider() : 'off';
        $limit_customer = $is_edit ? $service->get_limit_per_customer() : 'off';
        $final_step_enabled = $is_edit ? ( $service->is_final_step_url_enabled() ? 'enabled' : 'disabled' ) : 'disabled';
        $final_step_url     = $is_edit ? ( $service->get_final_step_url() ?? '' ) : '';
        $min_time_booking   = $is_edit ? $service->get_min_time_prior_booking_key() : 'default';
        $min_time_cancel    = $is_edit ? $service->get_min_time_prior_cancel_key() : 'default';
        $show_random        = in_array( $providers_preference, [ 'most_expensive', 'least_expensive', 'least_occupied_day', 'most_occupied_day' ], true );
        $show_occupancy     = in_array( $providers_preference, [ 'least_occupied_day', 'most_occupied_day' ], true );

        ?>
        <div class="smooth-booking-service-form-card">
            <div class="smooth-booking-form-header">
                <h2><?php echo $is_edit ? esc_html__( 'Edit service', 'smooth-booking' ) : esc_html__( 'Add new service', 'smooth-booking' ); ?></h2>
                <div class="smooth-booking-form-header__actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="button-link smooth-booking-form-cancel">
                            <?php esc_html_e( 'Back to list', 'smooth-booking' ); ?>
                        </a>
                    <?php else : ?>
                        <button type="button" class="button-link smooth-booking-form-dismiss" data-target="service-form">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <form class="smooth-booking-service-form" method='post' action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>'>
                <?php wp_nonce_field( 'smooth_booking_save_service', '_smooth_booking_save_service_nonce' ); ?>
                <input type='hidden' name='action' value='smooth_booking_save_service' />
                <input type='hidden' name='service_id' value='<?php echo esc_attr( $is_edit ? (string) $service->get_id() : '0' ); ?>' />

                <div class='smooth-booking-service-tabs'>
                    <nav class='nav-tab-wrapper'>
                        <a href='#smooth-booking-service-tab-general' class='nav-tab nav-tab-active' data-tab='general'><?php esc_html_e( 'General', 'smooth-booking' ); ?></a>
                        <a href='#smooth-booking-service-tab-time' class='nav-tab' data-tab='time'><?php esc_html_e( 'Time', 'smooth-booking' ); ?></a>
                        <a href='#smooth-booking-service-tab-additional' class='nav-tab' data-tab='additional'><?php esc_html_e( 'Additional', 'smooth-booking' ); ?></a>
                    </nav>

                    <div id='smooth-booking-service-tab-general' class='smooth-booking-service-tab-panel is-active'>
                        <table class='form-table'>
                            <tbody>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-name'><?php esc_html_e( 'Service name', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <input type='text' class='regular-text' id='smooth-booking-service-name' name='service_name' value='<?php echo esc_attr( $is_edit ? $service->get_name() : '' ); ?>' required />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><?php esc_html_e( 'Service image', 'smooth-booking' ); ?></th>
                                    <td>
                                        <div class='smooth-booking-service-avatar-field' data-placeholder='<?php echo esc_attr( $this->get_service_image_html( null ) ); ?>'>
                                            <div class='smooth-booking-avatar-preview'>
                                                <?php echo $this->get_service_image_html( $service ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </div>
                                            <div class='smooth-booking-avatar-actions'>
                                                <button type='button' class='button smooth-booking-service-avatar-select'><?php esc_html_e( 'Select image', 'smooth-booking' ); ?></button>
                                                <button type='button' class='button-link smooth-booking-service-avatar-remove' <?php if ( ! $profile_image_id ) : ?>style='display:none'<?php endif; ?>><?php esc_html_e( 'Remove image', 'smooth-booking' ); ?></button>
                                            </div>
                                            <input type='hidden' name='service_profile_image_id' value='<?php echo esc_attr( (string) ( $profile_image_id ?? 0 ) ); ?>' />
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-categories'><?php esc_html_e( 'Categories', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select multiple id='smooth-booking-service-categories' name='service_category_ids[]' class='smooth-booking-service-multiselect'>
                                            <?php if ( empty( $categories ) ) : ?>
                                                <option value='' disabled><?php esc_html_e( 'No categories available yet.', 'smooth-booking' ); ?></option>
                                            <?php else : ?>
                                                <?php foreach ( $categories as $category ) : ?>
                                                    <option value='<?php echo esc_attr( (string) $category->get_id() ); ?>' <?php selected( in_array( $category->get_id(), $selected_categories, true ), true ); ?>><?php echo esc_html( $category->get_name() ); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'Select existing categories or create new ones below.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-new-categories'><?php esc_html_e( 'Add new categories', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <textarea id='smooth-booking-service-new-categories' name='service_new_categories' class='large-text' rows='2' placeholder='<?php esc_attr_e( 'e.g. Massage; Online', 'smooth-booking' ); ?>'></textarea>
                                        <p class='description'><?php esc_html_e( 'Separate multiple categories with commas, semicolons, or new lines.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-tags'><?php esc_html_e( 'Tags', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select multiple id='smooth-booking-service-tags' name='service_tag_ids[]' class='smooth-booking-service-multiselect'>
                                            <?php if ( empty( $tags ) ) : ?>
                                                <option value='' disabled><?php esc_html_e( 'No tags available yet.', 'smooth-booking' ); ?></option>
                                            <?php else : ?>
                                                <?php foreach ( $tags as $tag ) : ?>
                                                    <option value='<?php echo esc_attr( (string) $tag->get_id() ); ?>' <?php selected( in_array( $tag->get_id(), $selected_tags, true ), true ); ?>><?php echo esc_html( $tag->get_name() ); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-new-tags'><?php esc_html_e( 'Add new tags', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <textarea id='smooth-booking-service-new-tags' name='service_new_tags' class='large-text' rows='2' placeholder='<?php esc_attr_e( 'e.g. Haircut, Premium', 'smooth-booking' ); ?>'></textarea>
                                        <p class='description'><?php esc_html_e( 'Separate multiple tags with commas, semicolons, or new lines.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-default-color'><?php esc_html_e( 'Color', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <input type='text' id='smooth-booking-service-default-color' name='service_default_color' value='<?php echo esc_attr( $color ); ?>' class='smooth-booking-color-field' />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-visibility'><?php esc_html_e( 'Visibility', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-visibility' name='service_visibility'>
                                            <option value='public' <?php selected( $visibility, 'public' ); ?>><?php esc_html_e( 'Public', 'smooth-booking' ); ?></option>
                                            <option value='private' <?php selected( $visibility, 'private' ); ?>><?php esc_html_e( 'Private', 'smooth-booking' ); ?></option>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'To make service invisible to your customers set the visibility to "Private".', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-price'><?php esc_html_e( 'Price', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <input type='number' id='smooth-booking-service-price' name='service_price' step='0.01' min='0' value='<?php echo esc_attr( $price ); ?>' />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><?php esc_html_e( 'Providers', 'smooth-booking' ); ?></th>
                                    <td>
                                        <div class='smooth-booking-providers'>
                                            <label class='smooth-booking-providers-select-all'>
                                                <input type='checkbox' class='smooth-booking-providers-toggle-all' <?php checked( count( $provider_map ) >= count( $employees ) && ! empty( $employees ) ); ?> />
                                                <?php esc_html_e( 'Select all providers', 'smooth-booking' ); ?>
                                            </label>
                                            <ul class='smooth-booking-providers-list'>
                                                <?php foreach ( $employees as $employee ) : ?>
                                                    <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                                                    <?php $is_selected = array_key_exists( $employee->get_id(), $provider_map ); ?>
                                                    <li>
                                                        <label>
                                                            <input type='checkbox' name='service_providers[<?php echo esc_attr( (string) $employee->get_id() ); ?>][selected]' value='1' <?php checked( $is_selected ); ?> class='smooth-booking-provider-checkbox' />
                                                            <?php echo esc_html( $employee->get_name() ); ?>
                                                        </label>
                                                        <input type='hidden' name='service_providers[<?php echo esc_attr( (string) $employee->get_id() ); ?>][employee_id]' value='<?php echo esc_attr( (string) $employee->get_id() ); ?>' />
                                                        <label class='screen-reader-text' for='smooth-booking-provider-order-<?php echo esc_attr( (string) $employee->get_id() ); ?>'><?php esc_html_e( 'Provider order', 'smooth-booking' ); ?></label>
                                                        <input type='number' id='smooth-booking-provider-order-<?php echo esc_attr( (string) $employee->get_id() ); ?>' name='service_providers[<?php echo esc_attr( (string) $employee->get_id() ); ?>][order]' class='small-text smooth-booking-provider-order' value='<?php echo esc_attr( (string) ( $provider_map[ $employee->get_id() ] ?? 0 ) ); ?>' min='0' />
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <p class='description'><?php esc_html_e( 'Deselect a provider to remove the service from their availability.', 'smooth-booking' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-providers-preference'><?php esc_html_e( 'Providers preference for ANY', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-providers-preference' name='service_providers_preference' class='smooth-booking-service-providers-preference'>
                                            <?php foreach ( $this->get_provider_preferences() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $providers_preference, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class='smooth-booking-providers-random' data-preference='<?php echo esc_attr( $providers_preference ); ?>'<?php echo $show_random ? '' : ' hidden'; ?>>
                                            <label>
                                                <span class='screen-reader-text'><?php esc_html_e( 'Pick random staff member in case of uncertainty', 'smooth-booking' ); ?></span>
                                                <select name='service_providers_random_tie' class='smooth-booking-providers-random-select'>
                                                    <option value='disabled' <?php selected( $random_tie, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'smooth-booking' ); ?></option>
                                                    <option value='enabled' <?php selected( $random_tie, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'smooth-booking' ); ?></option>
                                                </select>
                                            </label>
                                            <p class='description smooth-booking-random-hint'><?php esc_html_e( 'Enable this option to pick a random staff member if both meet the criteria chosen in "Providers preference for ANY". Otherwise the selection order is unknown.', 'smooth-booking' ); ?></p>
                                        </div>
                                        <div class='smooth-booking-providers-occupancy' data-preference='<?php echo esc_attr( $providers_preference ); ?>'<?php echo $show_occupancy ? '' : ' hidden'; ?>>
                                            <label><?php esc_html_e( 'Period (before and after)', 'smooth-booking' ); ?></label>
                                            <div class='smooth-booking-providers-period'>
                                                <input type='number' name='service_occupancy_period_before' value='<?php echo esc_attr( (string) $occupancy_before ); ?>' min='0' class='small-text' />
                                                <span class='smooth-booking-period-separator'>/</span>
                                                <input type='number' name='service_occupancy_period_after' value='<?php echo esc_attr( (string) $occupancy_after ); ?>' min='0' class='small-text' />
                                            </div>
                                            <p class='description'><?php esc_html_e( 'Set number of days before and after appointment that will be taken into account when calculating provider\'s occupancy. 0 means the day of booking.', 'smooth-booking' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-payment-methods'><?php esc_html_e( 'Available payment methods', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-payment-methods' name='service_payment_methods_mode'>
                                            <option value='default' <?php selected( $payment_mode, 'default' ); ?>><?php esc_html_e( 'Default', 'smooth-booking' ); ?></option>
                                            <option value='custom' <?php selected( $payment_mode, 'custom' ); ?>><?php esc_html_e( 'Custom', 'smooth-booking' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-info'><?php esc_html_e( 'Info', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <textarea id='smooth-booking-service-info' name='service_info' rows='4' class='large-text'><?php echo esc_textarea( (string) $info ); ?></textarea>
                                        <p class='description'><?php esc_html_e( 'This text can be inserted into notifications with {smooth_service_info} code', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id='smooth-booking-service-tab-time' class='smooth-booking-service-tab-panel'>
                        <table class='form-table'>
                            <tbody>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-duration'><?php esc_html_e( 'Duration', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-duration' name='service_duration'>
                                            <?php foreach ( $this->get_duration_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $duration, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-slot-length'><?php esc_html_e( 'Time slot length', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-slot-length' name='service_slot_length'>
                                            <?php foreach ( $this->get_slot_length_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $slot_length, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'The time interval which is used as a step when building all time slots for the service at the Time step. The setting overrides global settings in Settings > General. Use Default to apply global settings.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><?php esc_html_e( 'Padding time (before and after)', 'smooth-booking' ); ?></th>
                                    <td>
                                        <div class='smooth-booking-padding-fields'>
                                            <select name='service_padding_before'>
                                                <?php foreach ( $this->get_padding_options() as $value => $label ) : ?>
                                                    <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $padding_before, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class='smooth-booking-padding-separator'>/</span>
                                            <select name='service_padding_after'>
                                                <?php foreach ( $this->get_padding_options() as $value => $label ) : ?>
                                                    <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $padding_after, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class='description'><?php esc_html_e( 'Set padding time before and/or after an appointment. For example, if you require 15 minutes to prepare for the next appointment then you should set "padding before" to 15 min. If there is an appointment from 8:00 to 9:00 then the next available time slot will be 9:15 rather than 9:00.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id='smooth-booking-service-tab-additional' class='smooth-booking-service-tab-panel'>
                        <table class='form-table'>
                            <tbody>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-online-meeting'><?php esc_html_e( 'Create online meetings', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-online-meeting' name='service_online_meeting'>
                                            <?php foreach ( $this->get_online_meeting_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $online_meeting, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'If this setting is enabled then online meetings will be created for new appointments with the selected online meeting provider. Make sure that the provider is configured properly in Settings > Online Meetings. If you choose Google Meet then meetings will be created for those staff members who have Google Calendar configured.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-limit-per-customer'><?php esc_html_e( 'Limit appointments per customer', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-limit-per-customer' name='service_limit_per_customer'>
                                            <?php foreach ( $this->get_limit_per_customer_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $limit_customer, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'This setting allows you to limit the number of appointments that can be booked by a customer in any given period. Restriction may end after a fixed period or with the beginning of the next calendar period - new day, week, month, etc.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><?php esc_html_e( 'Final step URL', 'smooth-booking' ); ?></th>
                                    <td>
                                        <select name='service_final_step_url_mode' class='smooth-booking-final-step-toggle'>
                                            <option value='disabled' <?php selected( 'disabled' === $final_step_enabled ); ?>><?php esc_html_e( 'Disabled', 'smooth-booking' ); ?></option>
                                            <option value='enabled' <?php selected( 'enabled' === $final_step_enabled ); ?>><?php esc_html_e( 'Enabled', 'smooth-booking' ); ?></option>
                                        </select>
                                        <input type='url' name='service_final_step_url' class='regular-text smooth-booking-final-step-input' value='<?php echo esc_attr( $final_step_url ); ?>' <?php if ( 'disabled' === $final_step_enabled ) : ?>style='display:none'<?php endif; ?> placeholder='https://example.com/thank-you' />
                                        <p class='description'><?php esc_html_e( 'Set the URL of a page that the user will be forwarded to after successful booking. If disabled then the default Done step is displayed.', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-min-time-booking'><?php esc_html_e( 'Minimum time requirement prior to booking', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-min-time-booking' name='service_min_time_booking'>
                                            <?php foreach ( $this->get_min_time_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $min_time_booking, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'Set how late appointments can be booked (for example, require customers to book at least 1 hour before the appointment time).', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'><label for='smooth-booking-service-min-time-cancel'><?php esc_html_e( 'Minimum time requirement prior to canceling', 'smooth-booking' ); ?></label></th>
                                    <td>
                                        <select id='smooth-booking-service-min-time-cancel' name='service_min_time_cancel'>
                                            <?php foreach ( $this->get_min_time_options() as $value => $label ) : ?>
                                                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $min_time_cancel, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class='description'><?php esc_html_e( 'Set how late appointments can be cancelled (for example, require customers to cancel at least 1 hour before the appointment time).', 'smooth-booking' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php submit_button( $is_edit ? __( 'Update service', 'smooth-booking' ) : __( 'Add service', 'smooth-booking' ) ); ?>
            </form>
        </div>
        <?php
    }


    /**
     * Format datetime for display.
     */
    private function format_datetime( ?string $datetime ): string {
        if ( empty( $datetime ) ) {
            return '';
        }

        $timestamp = strtotime( $datetime );
        if ( false === $timestamp ) {
            return $datetime;
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }

    /**
     * Format price for display.
     */
    private function format_price( ?float $price ): string {
        if ( null === $price ) {
            return '—';
        }

        return number_format_i18n( $price, 2 );
    }

    /**
     * Retrieve duration label.
     */
    private function get_duration_label( string $key ): string {
        $options = $this->get_duration_options();

        return $options[ $key ] ?? $key;
    }

    /**
     * Provider preference options.
     *
     * @return array<string, string>
     */
    private function get_provider_preferences(): array {
        return [
            'most_expensive'        => __( 'Most expensive', 'smooth-booking' ),
            'least_expensive'       => __( 'Least expensive', 'smooth-booking' ),
            'specified_order'       => __( 'Specified order', 'smooth-booking' ),
            'least_occupied_day'    => __( 'Least occupied that day', 'smooth-booking' ),
            'most_occupied_day'     => __( 'Most occupied that day', 'smooth-booking' ),
            'least_occupied_period' => __( 'Least occupied for period', 'smooth-booking' ),
            'most_occupied_period'  => __( 'Most occupied for period', 'smooth-booking' ),
        ];
    }

    /**
     * Duration options.
     *
     * @return array<string, string>
     */
    private function get_duration_options(): array {
        return [
            '15_minutes'  => __( '15 minutes', 'smooth-booking' ),
            '30_minutes'  => __( '30 minutes', 'smooth-booking' ),
            '45_minutes'  => __( '45 minutes', 'smooth-booking' ),
            '60_minutes'  => __( '1 hour', 'smooth-booking' ),
            '75_minutes'  => __( '1 hour 15 minutes', 'smooth-booking' ),
            '90_minutes'  => __( '1 hour 30 minutes', 'smooth-booking' ),
            '105_minutes' => __( '1 hour 45 minutes', 'smooth-booking' ),
            '120_minutes' => __( '2 hours', 'smooth-booking' ),
            '135_minutes' => __( '2 hours 15 minutes', 'smooth-booking' ),
            '150_minutes' => __( '2 hours 30 minutes', 'smooth-booking' ),
            '165_minutes' => __( '2 hours 45 minutes', 'smooth-booking' ),
            '180_minutes' => __( '3 hours', 'smooth-booking' ),
            '195_minutes' => __( '3 hours 15 minutes', 'smooth-booking' ),
            '210_minutes' => __( '3 hours 30 minutes', 'smooth-booking' ),
            '225_minutes' => __( '3 hours 45 minutes', 'smooth-booking' ),
            '240_minutes' => __( '4 hours', 'smooth-booking' ),
            '255_minutes' => __( '4 hours 15 minutes', 'smooth-booking' ),
            '270_minutes' => __( '4 hours 30 minutes', 'smooth-booking' ),
            '285_minutes' => __( '4 hours 45 minutes', 'smooth-booking' ),
            '300_minutes' => __( '5 hours', 'smooth-booking' ),
            '360_minutes' => __( '6 hours', 'smooth-booking' ),
            '420_minutes' => __( '7 hours', 'smooth-booking' ),
            '480_minutes' => __( '8 hours', 'smooth-booking' ),
            '540_minutes' => __( '9 hours', 'smooth-booking' ),
            '600_minutes' => __( '10 hours', 'smooth-booking' ),
            '660_minutes' => __( '11 hours', 'smooth-booking' ),
            '720_minutes' => __( '12 hours', 'smooth-booking' ),
            'one_day'     => __( '1 day', 'smooth-booking' ),
            'two_days'    => __( '2 days', 'smooth-booking' ),
            'three_days'  => __( '3 days', 'smooth-booking' ),
            'four_days'   => __( '4 days', 'smooth-booking' ),
            'five_days'   => __( '5 days', 'smooth-booking' ),
            'six_days'    => __( '6 days', 'smooth-booking' ),
            'one_week'    => __( '1 week', 'smooth-booking' ),
        ];
    }

    /**
     * Slot length options.
     *
     * @return array<string, string>
     */
    private function get_slot_length_options(): array {
        return [
            'default'          => __( 'Default', 'smooth-booking' ),
            'service_duration' => __( 'Slot length as a service duration', 'smooth-booking' ),
            '2_minutes'        => __( '2 minutes', 'smooth-booking' ),
            '4_minutes'        => __( '4 minutes', 'smooth-booking' ),
            '5_minutes'        => __( '5 minutes', 'smooth-booking' ),
            '10_minutes'       => __( '10 minutes', 'smooth-booking' ),
            '12_minutes'       => __( '12 minutes', 'smooth-booking' ),
            '15_minutes'       => __( '15 minutes', 'smooth-booking' ),
            '20_minutes'       => __( '20 minutes', 'smooth-booking' ),
            '30_minutes'       => __( '30 minutes', 'smooth-booking' ),
            '45_minutes'       => __( '45 minutes', 'smooth-booking' ),
            '60_minutes'       => __( '1 hour', 'smooth-booking' ),
            '90_minutes'       => __( '1 hour 30 minutes', 'smooth-booking' ),
            '120_minutes'      => __( '2 hours', 'smooth-booking' ),
            '180_minutes'      => __( '3 hours', 'smooth-booking' ),
            '240_minutes'      => __( '4 hours', 'smooth-booking' ),
            '360_minutes'      => __( '6 hours', 'smooth-booking' ),
        ];
    }

    /**
     * Padding options.
     *
     * @return array<string, string>
     */
    private function get_padding_options(): array {
        return [
            'off'         => __( 'Off', 'smooth-booking' ),
            '15_minutes'  => __( '15 minutes', 'smooth-booking' ),
            '30_minutes'  => __( '30 minutes', 'smooth-booking' ),
            '45_minutes'  => __( '45 minutes', 'smooth-booking' ),
            '60_minutes'  => __( '1 hour', 'smooth-booking' ),
            '75_minutes'  => __( '1 hour 15 minutes', 'smooth-booking' ),
            '90_minutes'  => __( '1 hour 30 minutes', 'smooth-booking' ),
            '105_minutes' => __( '1 hour 45 minutes', 'smooth-booking' ),
            '120_minutes' => __( '2 hours', 'smooth-booking' ),
            '135_minutes' => __( '2 hours 15 minutes', 'smooth-booking' ),
            '150_minutes' => __( '2 hours 30 minutes', 'smooth-booking' ),
            '165_minutes' => __( '2 hours 45 minutes', 'smooth-booking' ),
            '180_minutes' => __( '3 hours', 'smooth-booking' ),
            '195_minutes' => __( '3 hours 15 minutes', 'smooth-booking' ),
            '210_minutes' => __( '3 hours 30 minutes', 'smooth-booking' ),
            '225_minutes' => __( '3 hours 45 minutes', 'smooth-booking' ),
            '240_minutes' => __( '4 hours', 'smooth-booking' ),
            '255_minutes' => __( '4 hours 15 minutes', 'smooth-booking' ),
            '270_minutes' => __( '4 hours 30 minutes', 'smooth-booking' ),
            '285_minutes' => __( '4 hours 45 minutes', 'smooth-booking' ),
            '300_minutes' => __( '5 hours', 'smooth-booking' ),
            '360_minutes' => __( '6 hours', 'smooth-booking' ),
            '420_minutes' => __( '7 hours', 'smooth-booking' ),
            '480_minutes' => __( '8 hours', 'smooth-booking' ),
            '540_minutes' => __( '9 hours', 'smooth-booking' ),
            '600_minutes' => __( '10 hours', 'smooth-booking' ),
            '660_minutes' => __( '11 hours', 'smooth-booking' ),
            '720_minutes' => __( '12 hours', 'smooth-booking' ),
            'one_day'     => __( '1 day', 'smooth-booking' ),
        ];
    }

    /**
     * Online meeting options.
     *
     * @return array<string, string>
     */
    private function get_online_meeting_options(): array {
        return [
            'off'        => __( 'Off', 'smooth-booking' ),
            'zoom'       => __( 'Zoom', 'smooth-booking' ),
            'google_meet'=> __( 'Google Meet', 'smooth-booking' ),
        ];
    }

    /**
     * Limit per customer options.
     *
     * @return array<string, string>
     */
    private function get_limit_per_customer_options(): array {
        return [
            'off'          => __( 'Off', 'smooth-booking' ),
            'upcoming'     => __( 'Upcoming', 'smooth-booking' ),
            'per_24_hours' => __( 'Per 24 hours', 'smooth-booking' ),
            'per_day'      => __( 'Per day', 'smooth-booking' ),
            'per_7_days'   => __( 'Per 7 days', 'smooth-booking' ),
            'per_week'     => __( 'Per week', 'smooth-booking' ),
            'per_30_days'  => __( 'Per 30 days', 'smooth-booking' ),
            'per_month'    => __( 'Per month', 'smooth-booking' ),
            'per_365_days' => __( 'Per 365 days', 'smooth-booking' ),
            'per_year'     => __( 'Per year', 'smooth-booking' ),
        ];
    }

    /**
     * Minimum time options.
     *
     * @return array<string, string>
     */
    private function get_min_time_options(): array {
        return [
            'default'   => __( 'Default', 'smooth-booking' ),
            'disabled'  => __( 'Disabled', 'smooth-booking' ),
            '30_minutes'=> __( '30 minutes', 'smooth-booking' ),
            '1_hour'    => __( '1 hour', 'smooth-booking' ),
            '2_hours'   => __( '2 hours', 'smooth-booking' ),
            '3_hours'   => __( '3 hours', 'smooth-booking' ),
            '4_hours'   => __( '4 hours', 'smooth-booking' ),
            '5_hours'   => __( '5 hours', 'smooth-booking' ),
            '6_hours'   => __( '6 hours', 'smooth-booking' ),
            '7_hours'   => __( '7 hours', 'smooth-booking' ),
            '8_hours'   => __( '8 hours', 'smooth-booking' ),
            '9_hours'   => __( '9 hours', 'smooth-booking' ),
            '10_hours'  => __( '10 hours', 'smooth-booking' ),
            '11_hours'  => __( '11 hours', 'smooth-booking' ),
            '12_hours'  => __( '12 hours', 'smooth-booking' ),
            '1_day'     => __( '1 day', 'smooth-booking' ),
            '2_days'    => __( '2 days', 'smooth-booking' ),
            '3_days'    => __( '3 days', 'smooth-booking' ),
            '4_days'    => __( '4 days', 'smooth-booking' ),
            '5_days'    => __( '5 days', 'smooth-booking' ),
            '6_days'    => __( '6 days', 'smooth-booking' ),
            '1_week'    => __( '1 week', 'smooth-booking' ),
            '2_weeks'   => __( '2 weeks', 'smooth-booking' ),
            '3_weeks'   => __( '3 weeks', 'smooth-booking' ),
            '4_weeks'   => __( '4 weeks', 'smooth-booking' ),
        ];
    }

    /**
     * Render service image HTML.
     */
    private function get_service_image_html( ?Service $service ): string {
        $attachment_id = $service ? $service->get_image_id() : null;

        if ( $attachment_id ) {
            $image = wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'class' => 'smooth-booking-avatar-image' ] );

            if ( $image ) {
                return "<span class='smooth-booking-avatar-wrapper'>{$image}</span>";
            }
        }

        return "<span class='smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder'><span class='dashicons dashicons-format-image' aria-hidden='true'></span><span class='screen-reader-text'>" . esc_html__( 'Service image', 'smooth-booking' ) . '</span></span>';
    }

    /**
     * Retrieve the base menu page URL.
     */
    private function get_base_page(): string {
        return add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'admin.php' ) );
    }

    /**
     * Retrieve edit link.
     */
    private function get_edit_link( int $service_id ): string {
        return add_query_arg(
            [
                'page'       => self::MENU_SLUG,
                'action'     => 'edit',
                'service_id' => $service_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    /**
     * Retrieve view link.
     */
    private function get_view_link( string $view ): string {
        $view = 'deleted' === $view ? 'deleted' : 'active';

        $args = [ 'page' => self::MENU_SLUG ];

        if ( 'deleted' === $view ) {
            $args['view'] = 'deleted';
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Persist admin notice.
     */
    private function add_notice( string $type, string $message ): void {
        set_transient(
            sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() ),
            [
                'type'    => $type,
                'message' => $message,
            ],
            300
        );
    }

    /**
     * Retrieve and clear notice.
     */
    private function consume_notice(): ?array {
        $key    = sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
        $notice = get_transient( $key );

        if ( false !== $notice ) {
            delete_transient( $key );

            return is_array( $notice ) ? $notice : null;
        }

        return null;
    }
}
