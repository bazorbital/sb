<?php
/**
 * Customers administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Customers\CustomerTag;
use WP_Error;
use WP_User;

use const MINUTE_IN_SECONDS;
use function __;
use function absint;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_url;
use function get_current_user_id;
use function get_option;
use function get_transient;
use function get_user_by;
use function get_users;
use function is_rtl;
use function is_wp_error;
use function number_format_i18n;
use function paginate_links;
use function plugins_url;
use function remove_query_arg;
use function sanitize_key;
use function sanitize_text_field;
use function selected;
use function set_transient;
use function sprintf;
use function wp_date;
use function wp_get_attachment_image;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_die;

/**
 * Renders the customers management interface.
 */
class CustomersPage {
    use AdminStylesTrait;
    /**
     * Capability required to manage customers.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Menu slug used for the customers screen.
     */
    public const MENU_SLUG = 'smooth-booking-customers';

    /**
     * Transient key template for admin notices.
     */
    private const NOTICE_TRANSIENT_TEMPLATE = 'smooth_booking_customer_notice_%d';

    /**
     * @var CustomerService
     */
    private CustomerService $service;

    /**
     * Constructor.
     */
    public function __construct( CustomerService $service ) {
        $this->service = $service;
    }

    /**
     * Render the customers admin page.
     */
    public function render_page(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage customers.', 'smooth-booking' ) );
        }

        $notice = $this->consume_notice();

        $action       = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $customer_id  = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
        $view         = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : 'active';
        $open         = isset( $_GET['open'] ) ? sanitize_key( wp_unslash( (string) $_GET['open'] ) ) : '';
        $show_deleted = 'deleted' === $view;

        $search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'name';
        $order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( (string) $_GET['order'] ) ) : 'asc';
        $paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $editing_customer = null;
        $editing_error    = null;

        if ( 'edit' === $action && $customer_id > 0 ) {
            $customer = $this->service->get_customer( $customer_id );
            if ( is_wp_error( $customer ) ) {
                $editing_error = $customer->get_error_message();
            } else {
                $editing_customer = $customer;
            }
        }

        $pagination = $this->service->paginate_customers(
            [
                'paged'          => $paged,
                'per_page'       => 20,
                'search'         => $search,
                'orderby'        => $orderby,
                'order'          => $order,
                'include_deleted'=> $show_deleted,
                'only_deleted'   => $show_deleted,
            ]
        );

        $customers = $pagination['customers'];
        $total     = (int) $pagination['total'];
        $per_page  = (int) $pagination['per_page'];
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );

        $tags = $this->service->list_tags();

        $should_open_form  = $editing_customer instanceof Customer || 'form' === $open;
        $form_container_id = 'smooth-booking-customer-form-panel';
        $open_label        = __( 'Add new customer', 'smooth-booking' );
        $close_label       = __( 'Close form', 'smooth-booking' );

        $base_args = [
            'page' => self::MENU_SLUG,
        ];
        $base_url = add_query_arg( $base_args, admin_url( 'admin.php' ) );

        $query_args = [
            'view'   => $show_deleted ? 'deleted' : 'active',
            's'      => $search,
            'order'  => $order,
            'orderby'=> $orderby,
        ];

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-customers-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Customers', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Manage your client records, contact details, and booking history.', 'smooth-booking' ); ?></p>
                    </div>
                    <div class="smooth-booking-admin-header__actions">
                        <button type="button" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-open-form" data-target="customer-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
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

            <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-customer-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="customer-form" data-focus-selector="#smooth-booking-customer-name"<?php echo $should_open_form ? '' : ' hidden'; ?>>
                <?php $this->render_customer_form( $editing_customer, $tags ); ?>
            </div>

            <h2><?php echo esc_html__( 'Customer list', 'smooth-booking' ); ?></h2>

            <form method="get" class="smooth-booking-list-search">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                <input type="hidden" name="view" value="<?php echo esc_attr( $show_deleted ? 'deleted' : 'active' ); ?>" />
                <label class="screen-reader-text" for="smooth-booking-customer-search"><?php esc_html_e( 'Search customers', 'smooth-booking' ); ?></label>
                <input type="search" id="smooth-booking-customer-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, email or phone…', 'smooth-booking' ); ?>" />
                <button type="submit" class="sba-btn sba-btn--primary sba-btn__medium"><?php esc_html_e( 'Search', 'smooth-booking' ); ?></button>
                <?php if ( $search ) : ?>
                    <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( remove_query_arg( 's' ) ); ?>"><?php esc_html_e( 'Clear search', 'smooth-booking' ); ?></a>
                <?php endif; ?>
            </form>

            <div class="smooth-booking-toolbar">
                <?php if ( $show_deleted ) : ?>
                    <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( add_query_arg( [ 'view' => 'active' ], $base_url ) ); ?>"><?php esc_html_e( 'Back to active customers', 'smooth-booking' ); ?></a>
                <?php else : ?>
                    <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( add_query_arg( [ 'view' => 'deleted' ], $base_url ) ); ?>"><?php esc_html_e( 'Show deleted customers', 'smooth-booking' ); ?></a>
                <?php endif; ?>
            </div>

            <?php if ( $show_deleted ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Deleted customers can be restored from this view.', 'smooth-booking' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="smooth-booking-customer-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php $this->render_sortable_header( __( 'ID', 'smooth-booking' ), 'id', $orderby, $order, $query_args ); ?>
                            <?php $this->render_sortable_header( __( 'Name', 'smooth-booking' ), 'name', $orderby, $order, $query_args ); ?>
                            <th scope="col"><?php esc_html_e( 'User', 'smooth-booking' ); ?></th>
                            <?php $this->render_sortable_header( __( 'Phone', 'smooth-booking' ), 'phone', $orderby, $order, $query_args ); ?>
                            <?php $this->render_sortable_header( __( 'Email', 'smooth-booking' ), 'email', $orderby, $order, $query_args ); ?>
                            <th scope="col"><?php esc_html_e( 'Tags', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Notes', 'smooth-booking' ); ?></th>
                            <?php $this->render_sortable_header( __( 'Last appointment', 'smooth-booking' ), 'last_appointment', $orderby, $order, $query_args ); ?>
                            <?php $this->render_sortable_header( __( 'Total appointments', 'smooth-booking' ), 'total_appointments', $orderby, $order, $query_args ); ?>
                            <?php $this->render_sortable_header( __( 'Payments', 'smooth-booking' ), 'total_payments', $orderby, $order, $query_args ); ?>
                            <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $customers ) ) : ?>
                        <tr>
                            <td colspan="10"><?php esc_html_e( 'No customers found.', 'smooth-booking' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $customers as $customer ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) $customer->get_id() ); ?></td>
                                <td>
                                    <div class="smooth-booking-customer-name-cell">
                                        <?php echo $this->get_customer_avatar_html( $customer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <div>
                                            <span class="smooth-booking-customer-name-text"><?php echo esc_html( $customer->get_name() ); ?></span>
                                            <?php if ( $customer->get_first_name() || $customer->get_last_name() ) : ?>
                                                <span class="description">
                                                    <?php echo esc_html( trim( $customer->get_first_name() . ' ' . $customer->get_last_name() ) ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $this->get_user_label( $customer->get_user_id() ) ); ?></td>
                                <td><?php echo $customer->get_phone() ? esc_html( $customer->get_phone() ) : esc_html( '—' ); ?></td>
                                <td><?php echo $customer->get_email() ? esc_html( $customer->get_email() ) : esc_html( '—' ); ?></td>
                                <td><?php echo esc_html( $this->format_tags( $customer->get_tags() ) ); ?></td>
                                <td><?php echo $customer->get_notes() ? esc_html( $customer->get_notes() ) : esc_html( '—' ); ?></td>
                                <td><?php echo esc_html( $this->format_datetime( $customer->get_last_appointment() ? $customer->get_last_appointment()->getTimestamp() : null ) ); ?></td>
                                <td><?php echo esc_html( (string) $customer->get_total_appointments() ); ?></td>
                                <td><?php echo esc_html( $this->format_payments( $customer->get_total_payments() ) ); ?></td>
                                <td class="smooth-booking-actions-cell">
                                    <div class="smooth-booking-actions-menu" data-customer-id="<?php echo esc_attr( (string) $customer->get_id() ); ?>">
                                        <button type="button" class="sba-btn sba-btn--icon-without-box smooth-booking-actions-toggle" aria-haspopup="true" aria-expanded="false">
                                            <span class="dashicons dashicons-ellipsis"></span>
                                            <span class="screen-reader-text"><?php esc_html_e( 'Open actions menu', 'smooth-booking' ); ?></span>
                                        </button>
                                        <ul class="smooth-booking-actions-list" hidden>
                                            <?php if ( ! $show_deleted ) : ?>
                                                <li>
                                                    <a class="smooth-booking-actions-link" href="<?php echo esc_url( $this->get_edit_link( $customer->get_id(), $query_args ) ); ?>"><?php esc_html_e( 'Edit', 'smooth-booking' ); ?></a>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                        <?php wp_nonce_field( 'smooth_booking_delete_customer', '_smooth_booking_delete_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_delete_customer" />
                                                        <input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customer->get_id() ); ?>" />
                                                        <input type="hidden" name="current_view" value="<?php echo esc_attr( $show_deleted ? 'deleted' : 'active' ); ?>" />
                                                        <button type="submit" class="smooth-booking-actions-link delete-link" data-confirm-message="<?php echo esc_attr( __( 'Are you sure you want to delete this customer?', 'smooth-booking' ) ); ?>"><?php esc_html_e( 'Delete', 'smooth-booking' ); ?></button>
                                                    </form>
                                                </li>
                                            <?php else : ?>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-restore-form">
                                                        <?php wp_nonce_field( 'smooth_booking_restore_customer', '_smooth_booking_restore_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_restore_customer" />
                                                        <input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customer->get_id() ); ?>" />
                                                        <input type="hidden" name="current_view" value="deleted" />
                                                        <button type="submit" class="smooth-booking-actions-link"><?php esc_html_e( 'Restore', 'smooth-booking' ); ?></button>
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

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(
                            [
                                'total'   => $total_pages,
                                'current' => $paged,
                                'base'    => add_query_arg( array_merge( $query_args, [ 'paged' => '%#%' ] ), $base_url ),
                                'format'  => '',
                            ]
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
        <?php
    }

    /**
     * Handle create and update requests.
     */
    public function handle_save(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage customers.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_customer', '_smooth_booking_save_nonce' );

        $customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

        $data = [
            'name'              => isset( $_POST['customer_name'] ) ? wp_unslash( (string) $_POST['customer_name'] ) : '',
            'first_name'        => isset( $_POST['customer_first_name'] ) ? wp_unslash( (string) $_POST['customer_first_name'] ) : '',
            'last_name'         => isset( $_POST['customer_last_name'] ) ? wp_unslash( (string) $_POST['customer_last_name'] ) : '',
            'phone'             => isset( $_POST['customer_phone'] ) ? wp_unslash( (string) $_POST['customer_phone'] ) : '',
            'email'             => isset( $_POST['customer_email'] ) ? wp_unslash( (string) $_POST['customer_email'] ) : '',
            'date_of_birth'     => isset( $_POST['customer_date_of_birth'] ) ? wp_unslash( (string) $_POST['customer_date_of_birth'] ) : '',
            'country'           => isset( $_POST['customer_country'] ) ? wp_unslash( (string) $_POST['customer_country'] ) : '',
            'state_region'      => isset( $_POST['customer_state_region'] ) ? wp_unslash( (string) $_POST['customer_state_region'] ) : '',
            'postal_code'       => isset( $_POST['customer_postal_code'] ) ? wp_unslash( (string) $_POST['customer_postal_code'] ) : '',
            'city'              => isset( $_POST['customer_city'] ) ? wp_unslash( (string) $_POST['customer_city'] ) : '',
            'street_address'    => isset( $_POST['customer_street_address'] ) ? wp_unslash( (string) $_POST['customer_street_address'] ) : '',
            'additional_address'=> isset( $_POST['customer_additional_address'] ) ? wp_unslash( (string) $_POST['customer_additional_address'] ) : '',
            'street_number'     => isset( $_POST['customer_street_number'] ) ? wp_unslash( (string) $_POST['customer_street_number'] ) : '',
            'notes'             => isset( $_POST['customer_notes'] ) ? wp_unslash( (string) $_POST['customer_notes'] ) : '',
            'profile_image_id'  => isset( $_POST['customer_profile_image_id'] ) ? wp_unslash( (string) $_POST['customer_profile_image_id'] ) : '0',
            'user_action'       => isset( $_POST['customer_user_action'] ) ? wp_unslash( (string) $_POST['customer_user_action'] ) : 'none',
            'existing_user_id'  => isset( $_POST['customer_existing_user'] ) ? wp_unslash( (string) $_POST['customer_existing_user'] ) : '0',
            'tag_ids'           => isset( $_POST['customer_tags'] ) ? array_map( 'wp_unslash', (array) $_POST['customer_tags'] ) : [],
            'new_tags'          => isset( $_POST['customer_new_tags'] ) ? wp_unslash( (string) $_POST['customer_new_tags'] ) : '',
        ];

        $result = $customer_id > 0
            ? $this->service->update_customer( $customer_id, $data )
            : $this->service->create_customer( $data );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );

            $redirect = $customer_id > 0
                ? $this->get_edit_link( $customer_id )
                : $this->get_base_page();

            wp_safe_redirect( $redirect );
            exit;
        }

        $message = $customer_id > 0
            ? __( 'Customer updated successfully.', 'smooth-booking' )
            : __( 'Customer created successfully.', 'smooth-booking' );

        $this->add_notice( 'success', $message );

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Handle delete requests.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage customers.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_customer', '_smooth_booking_delete_nonce' );

        $customer_id   = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'active';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $customer_id ) {
            $this->add_notice( 'error', __( 'Missing customer identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->service->delete_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Customer deleted.', 'smooth-booking' ) );
        wp_safe_redirect( $redirect_link );
        exit;
    }

    /**
     * Handle restoration requests.
     */
    public function handle_restore(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage customers.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_restore_customer', '_smooth_booking_restore_nonce' );

        $customer_id   = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'deleted';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $customer_id ) {
            $this->add_notice( 'error', __( 'Missing customer identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->service->restore_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Customer restored.', 'smooth-booking' ) );
        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Enqueue admin assets for the customers screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_media();

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-admin-customers',
            plugins_url( 'assets/css/admin-customers.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-admin-customers',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/js/admin-customers.js',
            [ 'jquery' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'smooth-booking-admin-customers',
            'SmoothBookingCustomers',
            [
                'confirmDelete'   => __( 'Are you sure you want to delete this customer?', 'smooth-booking' ),
                'chooseImage'     => __( 'Select profile image', 'smooth-booking' ),
                'useImage'        => __( 'Use image', 'smooth-booking' ),
                'removeImage'     => __( 'Remove image', 'smooth-booking' ),
                'placeholderHtml' => $this->get_customer_avatar_html( null, esc_html__( 'Customer avatar', 'smooth-booking' ) ),
            ]
        );
    }


    /**
     * Render the customer form for creating or editing.
     *
     * @param Customer|null   $customer Customer being edited or null for creation.
     * @param CustomerTag[] $all_tags Available tags.
     */
    private function render_customer_form( ?Customer $customer, array $all_tags ): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
        $is_edit = null !== $customer;

        $name              = $is_edit ? $customer->get_name() : '';
        $first_name        = $is_edit ? ( $customer->get_first_name() ?? '' ) : '';
        $last_name         = $is_edit ? ( $customer->get_last_name() ?? '' ) : '';
        $phone             = $is_edit ? ( $customer->get_phone() ?? '' ) : '';
        $email             = $is_edit ? ( $customer->get_email() ?? '' ) : '';
        $date_of_birth     = $is_edit ? ( $customer->get_date_of_birth() ?? '' ) : '';
        $country           = $is_edit ? ( $customer->get_country() ?? '' ) : '';
        $state_region      = $is_edit ? ( $customer->get_state() ?? '' ) : '';
        $postal_code       = $is_edit ? ( $customer->get_postal_code() ?? '' ) : '';
        $city              = $is_edit ? ( $customer->get_city() ?? '' ) : '';
        $street_address    = $is_edit ? ( $customer->get_street_address() ?? '' ) : '';
        $additional_address = $is_edit ? ( $customer->get_additional_address() ?? '' ) : '';
        $street_number     = $is_edit ? ( $customer->get_street_number() ?? '' ) : '';
        $notes             = $is_edit ? ( $customer->get_notes() ?? '' ) : '';
        $profile_image_id  = $is_edit ? ( $customer->get_profile_image_id() ?? 0 ) : 0;
        $user_id           = $is_edit ? ( $customer->get_user_id() ?? 0 ) : 0;
        $user_action       = $user_id ? 'assign' : 'none';

        $selected_tags = $is_edit
            ? array_map(
                static function ( CustomerTag $tag ): int {
                    return $tag->get_id();
                },
                $customer->get_tags()
            )
            : [];

        $users = get_users(
            [
                'orderby' => 'display_name',
                'number'  => 100,
            ]
        );

        ?>
        <div class="smooth-booking-customer-form-card smooth-booking-card">
            <div class="smooth-booking-form-header">
                <h2><?php echo $is_edit ? esc_html__( 'Edit customer', 'smooth-booking' ) : esc_html__( 'Add new customer', 'smooth-booking' ); ?></h2>
                <div class="smooth-booking-form-header__actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-cancel"><?php esc_html_e( 'Back to list', 'smooth-booking' ); ?></a>
                    <?php else : ?>
                        <button type="button" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-dismiss" data-target="customer-form"><?php esc_html_e( 'Cancel', 'smooth-booking' ); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <form class="smooth-booking-customer-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'smooth_booking_save_customer', '_smooth_booking_save_nonce' ); ?>
                <input type="hidden" name="action" value="smooth_booking_save_customer" />
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customer->get_id() ); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-name"><?php esc_html_e( 'Customer name', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-name" name="customer_name" value="<?php echo esc_attr( $name ); ?>" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Profile image', 'smooth-booking' ); ?></th>
                            <td>
                                <div class="smooth-booking-avatar-field" data-placeholder="<?php echo esc_attr( $this->get_customer_avatar_html( null, esc_html__( 'Customer avatar', 'smooth-booking' ) ) ); ?>">
                                    <div class="smooth-booking-avatar-preview">
                                        <?php echo $this->get_customer_avatar_html( $customer, esc_html__( 'Customer avatar', 'smooth-booking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                    <div class="smooth-booking-avatar-actions">
                                        <button type="button" class="sba-btn sba-btn__small sba-btn__filled smooth-booking-avatar-select"><?php esc_html_e( 'Choose image', 'smooth-booking' ); ?></button>
                                        <button type="button" class="sba-btn sba-btn__small sba-btn__filled-light smooth-booking-avatar-remove"<?php echo $profile_image_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'smooth-booking' ); ?></button>
                                        <input type="hidden" name="customer_profile_image_id" value="<?php echo esc_attr( (string) $profile_image_id ); ?>" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-user-action"><?php esc_html_e( 'WordPress user', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-customer-user-action" name="customer_user_action">
                                    <option value="none"<?php selected( 'none', $user_action ); ?>><?php esc_html_e( "Don't create a WordPress user", 'smooth-booking' ); ?></option>
                                    <option value="create"<?php selected( 'create', $user_action ); ?>><?php esc_html_e( 'Create WordPress user', 'smooth-booking' ); ?></option>
                                    <option value="assign"<?php selected( 'assign', $user_action ); ?>><?php esc_html_e( 'Assign existing WordPress user', 'smooth-booking' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Create or assign a WordPress user account for this customer.', 'smooth-booking' ); ?></p>
                                <div class="smooth-booking-existing-user-field"<?php echo 'assign' === $user_action ? '' : ' style="display:none"'; ?>>
                                    <label for="smooth-booking-customer-existing-user" class="screen-reader-text"><?php esc_html_e( 'Existing WordPress user', 'smooth-booking' ); ?></label>
                                    <select id="smooth-booking-customer-existing-user" name="customer_existing_user">
                                        <option value="0"><?php esc_html_e( 'Select user', 'smooth-booking' ); ?></option>
                                        <?php foreach ( $users as $user ) : ?>
                                            <option value="<?php echo esc_attr( (string) $user->ID ); ?>"<?php selected( $user_id, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-first-name"><?php esc_html_e( 'First name', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-first-name" name="customer_first_name" value="<?php echo esc_attr( $first_name ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-last-name"><?php esc_html_e( 'Last name', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-last-name" name="customer_last_name" value="<?php echo esc_attr( $last_name ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-phone"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-phone" name="customer_phone" value="<?php echo esc_attr( $phone ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-email"><?php esc_html_e( 'Email', 'smooth-booking' ); ?></label></th>
                            <td><input type="email" class="regular-text" id="smooth-booking-customer-email" name="customer_email" value="<?php echo esc_attr( $email ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-tags"><?php esc_html_e( 'Tags', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-customer-tags" name="customer_tags[]" multiple size="5" class="smooth-booking-tags-select">
                                    <?php foreach ( $all_tags as $tag ) : ?>
                                        <option value="<?php echo esc_attr( (string) $tag->get_id() ); ?>"<?php selected( in_array( $tag->get_id(), $selected_tags, true ) ); ?>><?php echo esc_html( $tag->get_name() ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Hold CTRL or CMD to select multiple tags.', 'smooth-booking' ); ?></p>
                                <label for="smooth-booking-customer-new-tags" class="screen-reader-text"><?php esc_html_e( 'Create new tags', 'smooth-booking' ); ?></label>
                                <input type="text" id="smooth-booking-customer-new-tags" name="customer_new_tags" value="" placeholder="<?php echo esc_attr__( 'Add new tags separated by comma', 'smooth-booking' ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-date-of-birth"><?php esc_html_e( 'Date of birth', 'smooth-booking' ); ?></label></th>
                            <td><input type="date" id="smooth-booking-customer-date-of-birth" name="customer_date_of_birth" value="<?php echo esc_attr( $date_of_birth ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-country"><?php esc_html_e( 'Country', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-country" name="customer_country" value="<?php echo esc_attr( $country ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-state-region"><?php esc_html_e( 'State/Region', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-state-region" name="customer_state_region" value="<?php echo esc_attr( $state_region ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-postal-code"><?php esc_html_e( 'Postal code', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-postal-code" name="customer_postal_code" value="<?php echo esc_attr( $postal_code ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-city"><?php esc_html_e( 'City', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-city" name="customer_city" value="<?php echo esc_attr( $city ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-street-address"><?php esc_html_e( 'Street address', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-street-address" name="customer_street_address" value="<?php echo esc_attr( $street_address ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-additional-address"><?php esc_html_e( 'Additional address', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-additional-address" name="customer_additional_address" value="<?php echo esc_attr( $additional_address ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-street-number"><?php esc_html_e( 'Street number', 'smooth-booking' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="smooth-booking-customer-street-number" name="customer_street_number" value="<?php echo esc_attr( $street_number ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-customer-notes"><?php esc_html_e( 'Notes', 'smooth-booking' ); ?></label></th>
                            <td>
                                <textarea id="smooth-booking-customer-notes" name="customer_notes" rows="4" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'This text can be inserted into notifications with {client_note} code.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="smooth-booking-form-actions">
                    <button type="submit" class="sba-btn sba-btn--primary sba-btn__large smooth-booking-form-submit"><?php echo $is_edit ? esc_html__( 'Update customer', 'smooth-booking' ) : esc_html__( 'Create customer', 'smooth-booking' ); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render sortable table header cells.
     */
    private function render_sortable_header( string $label, string $key, string $current_orderby, string $current_order, array $query_args ): void {
        $order = 'asc';
        if ( $current_orderby === $key ) {
            $order = 'asc' === $current_order ? 'desc' : 'asc';
        }

        $url = add_query_arg(
            array_merge(
                $query_args,
                [
                    'orderby' => $key,
                    'order'   => $order,
                    'paged'   => 1,
                ]
            ),
            $this->get_base_page()
        );

        $class = 'sortable';
        if ( $current_orderby === $key ) {
            $class .= ' sorted ' . ( 'asc' === $current_order ? 'asc' : 'desc' );
        }

        printf(
            '<th scope="col" class="manage-column %1$s"><a href="%2$s"><span>%3$s</span><span class="sorting-indicator"></span></a></th>',
            esc_attr( $class ),
            esc_url( $url ),
            esc_html( $label )
        );
    }

    /**
     * Retrieve avatar HTML for customer.
     */
    private function get_customer_avatar_html( ?Customer $customer, ?string $alt = null ): string {
        $attachment_id = $customer ? $customer->get_profile_image_id() : null;

        if ( $attachment_id ) {
            $image = wp_get_attachment_image( $attachment_id, [ 64, 64 ], false, [ 'class' => 'smooth-booking-avatar-image', 'alt' => $alt ?? '' ] );
            if ( $image ) {
                return '<span class="smooth-booking-avatar-wrapper">' . $image . '</span>';
            }
        }

        $placeholder = '<span class="smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder dashicons dashicons-admin-users" aria-hidden="true"></span>';

        return apply_filters( 'smooth_booking_customer_avatar_html', $placeholder, $customer );
    }

    /**
     * Format customer tags.
     *
     * @param CustomerTag[] $tags Tags.
     */
    private function format_tags( array $tags ): string {
        if ( empty( $tags ) ) {
            return '—';
        }

        return implode(
            ', ',
            array_map(
                static function ( CustomerTag $tag ): string {
                    return $tag->get_name();
                },
                $tags
            )
        );
    }

    /**
     * Format datetime for display.
     */
    private function format_datetime( ?int $timestamp ): string {
        if ( empty( $timestamp ) ) {
            return '—';
        }

        return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }

    /**
     * Format payments.
     */
    private function format_payments( float $amount ): string {
        return number_format_i18n( $amount, 2 );
    }

    /**
     * Format user label.
     */
    private function get_user_label( ?int $user_id ): string {
        if ( ! $user_id ) {
            return '—';
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user instanceof WP_User ) {
            return '—';
        }

        return sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email );
    }

    /**
     * Persist admin notice to transient.
     */
    private function add_notice( string $type, string $message ): void {
        $key = sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
        set_transient( $key, [ 'type' => $type, 'message' => $message ], MINUTE_IN_SECONDS );
    }

    /**
     * Retrieve and consume notice.
     */
    private function consume_notice(): ?array {
        $key = sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
        $notice = get_transient( $key );

        if ( $notice ) {
            delete_transient( $key );
        }

        return is_array( $notice ) ? $notice : null;
    }

    /**
     * Base page URL.
     */
    private function get_base_page(): string {
        return add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'admin.php' ) );
    }

    /**
     * Generate view link.
     */
    private function get_view_link( string $view ): string {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'view' => $view,
            ],
            admin_url( 'admin.php' )
        );
    }

    /**
     * Generate edit link.
     */
    private function get_edit_link( int $customer_id, array $query_args = [] ): string {
        $args = array_merge(
            [
                'page'        => self::MENU_SLUG,
                'action'      => 'edit',
                'customer_id' => $customer_id,
            ],
            $query_args
        );

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }
}
