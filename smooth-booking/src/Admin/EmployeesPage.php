<?php
/**
 * Employees administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeCategory;
use SmoothBooking\Domain\Employees\EmployeeService;

/**
 * Renders and handles the employees management interface.
 */
class EmployeesPage {
    /**
     * Capability required to manage employees.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Menu slug used for the employees screen.
     */
    public const MENU_SLUG = 'smooth-booking-employees';

    /**
     * Transient key template for admin notices.
     */
    private const NOTICE_TRANSIENT_TEMPLATE = 'smooth_booking_employee_notice_%d';

    /**
     * @var EmployeeService
     */
    private EmployeeService $service;

    /**
     * Constructor.
     */
    public function __construct( EmployeeService $service ) {
        $this->service = $service;
    }

    /**
     * Render the employees admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage employees.', 'smooth-booking' ) );
        }

        $notice = $this->consume_notice();

        $action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $employee_id = isset( $_GET['employee_id'] ) ? absint( $_GET['employee_id'] ) : 0;
        $view        = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : 'active';
        $show_deleted = 'deleted' === $view;

        $editing_employee = null;
        $editing_error    = null;

        if ( 'edit' === $action && $employee_id > 0 ) {
            $employee = $this->service->get_employee( $employee_id );
            if ( is_wp_error( $employee ) ) {
                $editing_error = $employee->get_error_message();
            } else {
                $editing_employee = $employee;
            }
        }

        $employees  = $this->service->list_employees(
            [
                'only_deleted' => $show_deleted,
            ]
        );
        $categories = $this->service->list_categories();

        ?>
        <div class="wrap smooth-booking-employees-wrap">
            <h1><?php echo esc_html__( 'Employees', 'smooth-booking' ); ?></h1>

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

            <div class="smooth-booking-employee-forms">
                <?php $this->render_employee_form( $editing_employee, $categories ); ?>
            </div>

            <h2><?php echo esc_html__( 'Employee list', 'smooth-booking' ); ?></h2>
            <p><?php esc_html_e( 'Manage staff members available for booking assignments.', 'smooth-booking' ); ?></p>

            <div class="smooth-booking-toolbar">
                <?php if ( $show_deleted ) : ?>
                    <a class="button" href="<?php echo esc_url( $this->get_view_link( 'active' ) ); ?>">
                        <?php esc_html_e( 'Back to active employees', 'smooth-booking' ); ?>
                    </a>
                <?php else : ?>
                    <a class="button" href="<?php echo esc_url( $this->get_view_link( 'deleted' ) ); ?>">
                        <?php esc_html_e( 'Show deleted employees', 'smooth-booking' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( $show_deleted ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Deleted employees can be restored from this view.', 'smooth-booking' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="smooth-booking-employee-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Name', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Email', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Specialization', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Categories', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Online booking', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Visibility', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Default color', 'smooth-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Last updated', 'smooth-booking' ); ?></th>
                            <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $employees ) ) : ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e( 'No employees have been added yet.', 'smooth-booking' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $employees as $employee ) : ?>
                            <tr>
                                <td>
                                    <div class="smooth-booking-employee-name-cell">
                                        <?php echo $this->get_employee_avatar_html( $employee ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <span class="smooth-booking-employee-name-text"><?php echo esc_html( $employee->get_name() ); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $employee->get_email() ? esc_html( $employee->get_email() ) : esc_html( '—' ); ?></td>
                                <td><?php echo $employee->get_phone() ? esc_html( $employee->get_phone() ) : esc_html( '—' ); ?></td>
                                <td><?php echo $employee->get_specialization() ? esc_html( $employee->get_specialization() ) : esc_html( '—' ); ?></td>
                                <td>
                                    <?php
                                    $employee_categories = $employee->get_categories();
                                    if ( empty( $employee_categories ) ) {
                                        echo esc_html( '—' );
                                    } else {
                                        echo esc_html( implode( ', ', array_map(
                                            static function ( EmployeeCategory $category ): string {
                                                return $category->get_name();
                                            },
                                            $employee_categories
                                        ) ) );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo $employee->is_available_online()
                                        ? esc_html__( 'Available', 'smooth-booking' )
                                        : esc_html__( 'Offline only', 'smooth-booking' );
                                    ?>
                                </td>
                                <td><?php echo esc_html( ucfirst( $employee->get_visibility() ) ); ?></td>
                                <td>
                                    <?php if ( $employee->get_default_color() ) : ?>
                                        <span class="smooth-booking-color-indicator" style="background-color: <?php echo esc_attr( $employee->get_default_color() ); ?>"></span>
                                        <span class="screen-reader-text"><?php echo esc_html( $employee->get_default_color() ); ?></span>
                                    <?php else : ?>
                                        <?php echo esc_html( '—' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $this->format_datetime( $employee->get_updated_at() ) ); ?></td>
                                <td class="smooth-booking-actions-cell">
                                    <div class="smooth-booking-actions-menu" data-employee-id="<?php echo esc_attr( (string) $employee->get_id() ); ?>">
                                        <button type="button" class="button button-link smooth-booking-actions-toggle" aria-haspopup="true" aria-expanded="false">
                                            <span class="dashicons dashicons-ellipsis"></span>
                                            <span class="screen-reader-text"><?php esc_html_e( 'Open actions menu', 'smooth-booking' ); ?></span>
                                        </button>
                                        <ul class="smooth-booking-actions-list" hidden>
                                            <?php if ( ! $show_deleted ) : ?>
                                                <li>
                                                    <a class="smooth-booking-actions-link" href="<?php echo esc_url( $this->get_edit_link( $employee->get_id() ) ); ?>">
                                                        <?php esc_html_e( 'Edit', 'smooth-booking' ); ?>
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                        <?php wp_nonce_field( 'smooth_booking_delete_employee', '_smooth_booking_delete_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_delete_employee" />
                                                        <input type="hidden" name="employee_id" value="<?php echo esc_attr( (string) $employee->get_id() ); ?>" />
                                                        <input type="hidden" name="current_view" value="<?php echo esc_attr( $show_deleted ? 'deleted' : 'active' ); ?>" />
                                                        <button type="submit" class="smooth-booking-actions-link delete-link" data-confirm-message="<?php echo esc_attr( __( 'Are you sure you want to delete this employee?', 'smooth-booking' ) ); ?>">
                                                            <?php esc_html_e( 'Delete', 'smooth-booking' ); ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php else : ?>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-restore-form">
                                                        <?php wp_nonce_field( 'smooth_booking_restore_employee', '_smooth_booking_restore_nonce' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_restore_employee" />
                                                        <input type="hidden" name="employee_id" value="<?php echo esc_attr( (string) $employee->get_id() ); ?>" />
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
     * Handle create and update requests.
     */
    public function handle_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage employees.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_employee', '_smooth_booking_save_nonce' );

        $employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;

        $data = [
            'name'             => isset( $_POST['employee_name'] ) ? wp_unslash( (string) $_POST['employee_name'] ) : '',
            'email'            => isset( $_POST['employee_email'] ) ? wp_unslash( (string) $_POST['employee_email'] ) : '',
            'phone'            => isset( $_POST['employee_phone'] ) ? wp_unslash( (string) $_POST['employee_phone'] ) : '',
            'specialization'   => isset( $_POST['employee_specialization'] ) ? wp_unslash( (string) $_POST['employee_specialization'] ) : '',
            'available_online' => isset( $_POST['employee_available_online'] ) ? wp_unslash( (string) $_POST['employee_available_online'] ) : '0',
            'profile_image_id' => isset( $_POST['employee_profile_image_id'] ) ? wp_unslash( (string) $_POST['employee_profile_image_id'] ) : '0',
            'default_color'    => isset( $_POST['employee_default_color'] ) ? wp_unslash( (string) $_POST['employee_default_color'] ) : '',
            'visibility'       => isset( $_POST['employee_visibility'] ) ? wp_unslash( (string) $_POST['employee_visibility'] ) : 'public',
            'category_ids'     => isset( $_POST['employee_categories'] ) ? array_map( 'wp_unslash', (array) $_POST['employee_categories'] ) : [],
            'new_categories'   => isset( $_POST['employee_new_categories'] ) ? wp_unslash( (string) $_POST['employee_new_categories'] ) : '',
        ];

        $result = $employee_id > 0
            ? $this->service->update_employee( $employee_id, $data )
            : $this->service->create_employee( $data );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );

            $redirect = $employee_id > 0
                ? $this->get_edit_link( $employee_id )
                : $this->get_base_page();

            wp_safe_redirect( $redirect );
            exit;
        }

        $message = $employee_id > 0
            ? __( 'Employee updated successfully.', 'smooth-booking' )
            : __( 'Employee created successfully.', 'smooth-booking' );

        $this->add_notice( 'success', $message );

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Handle delete requests.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage employees.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_employee', '_smooth_booking_delete_nonce' );

        $employee_id   = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'active';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $employee_id ) {
            $this->add_notice( 'error', __( 'Missing employee identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->service->delete_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Employee deleted.', 'smooth-booking' ) );
        wp_safe_redirect( $redirect_link );
        exit;
    }

    /**
     * Handle restoration requests.
     */
    public function handle_restore(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage employees.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_restore_employee', '_smooth_booking_restore_nonce' );

        $employee_id   = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
        $current_view  = isset( $_POST['current_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['current_view'] ) ) : 'deleted';
        $redirect_link = 'deleted' === $current_view ? $this->get_view_link( 'deleted' ) : $this->get_base_page();

        if ( 0 === $employee_id ) {
            $this->add_notice( 'error', __( 'Missing employee identifier.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $result = $this->service->restore_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect_link );
            exit;
        }

        $this->add_notice( 'success', __( 'Employee restored.', 'smooth-booking' ) );
        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Enqueue admin assets for the employees screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_' . self::MENU_SLUG !== $hook && 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_style(
            'smooth-booking-admin-employees',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/css/admin-employees.css',
            [ 'wp-color-picker' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_media();

        wp_enqueue_script(
            'smooth-booking-admin-employees',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/js/admin-employees.js',
            [ 'jquery', 'wp-color-picker' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'smooth-booking-admin-employees',
            'SmoothBookingEmployees',
            [
                'confirmDelete'   => __( 'Are you sure you want to delete this employee?', 'smooth-booking' ),
                'chooseImage'     => __( 'Select profile image', 'smooth-booking' ),
                'useImage'        => __( 'Use image', 'smooth-booking' ),
                'removeImage'     => __( 'Remove image', 'smooth-booking' ),
                'placeholderHtml' => $this->get_avatar_html( null, esc_html__( 'Employee avatar', 'smooth-booking' ) ),
            ]
        );
    }

    /**
     * Render the employee form for creating or editing.
     *
     * @param Employee|null $employee Employee being edited or null for creation.
     */
    private function render_employee_form( ?Employee $employee, array $all_categories ): void {
        $is_edit = null !== $employee;

        $name               = $is_edit ? $employee->get_name() : '';
        $email              = $is_edit ? ( $employee->get_email() ?? '' ) : '';
        $phone              = $is_edit ? ( $employee->get_phone() ?? '' ) : '';
        $specialization     = $is_edit ? ( $employee->get_specialization() ?? '' ) : '';
        $available          = $is_edit ? $employee->is_available_online() : true;
        $profile_image_id   = $is_edit ? ( $employee->get_profile_image_id() ?? 0 ) : 0;
        $default_color      = $is_edit ? ( $employee->get_default_color() ?? '' ) : '';
        $visibility         = $is_edit ? $employee->get_visibility() : 'public';
        $selected_categories = $is_edit
            ? array_map(
                static function ( EmployeeCategory $category ): int {
                    return $category->get_id();
                },
                $employee->get_categories()
            )
            : [];
        ?>
        <div class="smooth-booking-employee-form-card">
            <h2><?php echo $is_edit ? esc_html__( 'Edit employee', 'smooth-booking' ) : esc_html__( 'Add new employee', 'smooth-booking' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'smooth_booking_save_employee', '_smooth_booking_save_nonce' ); ?>
                <input type="hidden" name="action" value="smooth_booking_save_employee" />
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="employee_id" value="<?php echo esc_attr( (string) $employee->get_id() ); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-name"><?php esc_html_e( 'Name', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="smooth-booking-employee-name" name="employee_name" value="<?php echo esc_attr( $name ); ?>" required />
                                <p class="description"><?php esc_html_e( 'Full name as visible to customers.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Profile image', 'smooth-booking' ); ?></th>
                            <td>
                                <div class="smooth-booking-avatar-field" data-placeholder="<?php echo esc_attr( $this->get_avatar_html( null, $name ?: esc_html__( 'Employee avatar', 'smooth-booking' ) ) ); ?>">
                                    <div class="smooth-booking-avatar-preview">
                                        <?php echo $this->get_avatar_html( $profile_image_id ? $profile_image_id : null, $name ?: esc_html__( 'Employee avatar', 'smooth-booking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                    <input type="hidden" name="employee_profile_image_id" id="smooth-booking-employee-profile-image" value="<?php echo esc_attr( (string) $profile_image_id ); ?>" />
                                    <button type="button" class="button smooth-booking-avatar-select"><?php esc_html_e( 'Select image', 'smooth-booking' ); ?></button>
                                    <button type="button" class="button-link smooth-booking-avatar-remove" <?php if ( ! $profile_image_id ) : ?>style="display:none"<?php endif; ?>><?php esc_html_e( 'Remove image', 'smooth-booking' ); ?></button>
                                </div>
                                <p class="description"><?php esc_html_e( 'Choose a profile picture to display alongside the employee.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-email"><?php esc_html_e( 'Email', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="email" class="regular-text" id="smooth-booking-employee-email" name="employee_email" value="<?php echo esc_attr( $email ); ?>" />
                                <p class="description"><?php esc_html_e( 'Notifications will be sent to this address.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-phone"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="smooth-booking-employee-phone" name="employee_phone" value="<?php echo esc_attr( $phone ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-specialization"><?php esc_html_e( 'Specialization', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="smooth-booking-employee-specialization" name="employee_specialization" value="<?php echo esc_attr( $specialization ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-visibility"><?php esc_html_e( 'Visibility', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-employee-visibility" name="employee_visibility">
                                    <option value="public" <?php selected( $visibility, 'public' ); ?>><?php esc_html_e( 'Public', 'smooth-booking' ); ?></option>
                                    <option value="private" <?php selected( $visibility, 'private' ); ?>><?php esc_html_e( 'Private', 'smooth-booking' ); ?></option>
                                    <option value="archived" <?php selected( $visibility, 'archived' ); ?>><?php esc_html_e( 'Archived', 'smooth-booking' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Archived employees remain hidden from booking interfaces but stay in the directory.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-default-color"><?php esc_html_e( 'Default color', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" class="smooth-booking-color-field" id="smooth-booking-employee-default-color" name="employee_default_color" value="<?php echo esc_attr( $default_color ); ?>" data-default-color="#2271b1" />
                                <p class="description"><?php esc_html_e( 'Used for calendars and highlighting the employee in widgets.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Online booking availability', 'smooth-booking' ); ?></th>
                            <td>
                                <label for="smooth-booking-employee-available-online">
                                    <input type="checkbox" id="smooth-booking-employee-available-online" name="employee_available_online" value="1" <?php checked( $available ); ?> />
                                    <?php esc_html_e( 'Employee can be booked online by customers.', 'smooth-booking' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-categories"><?php esc_html_e( 'Categories', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-employee-categories" name="employee_categories[]" multiple size="5" class="smooth-booking-category-select">
                                    <?php if ( empty( $all_categories ) ) : ?>
                                        <option value="" disabled><?php esc_html_e( 'No categories available yet.', 'smooth-booking' ); ?></option>
                                    <?php else : ?>
                                        <?php foreach ( $all_categories as $category ) : ?>
                                            <option value="<?php echo esc_attr( (string) $category->get_id() ); ?>" <?php selected( in_array( $category->get_id(), $selected_categories, true ) ); ?>>
                                                <?php echo esc_html( $category->get_name() ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Hold Control (Windows) or Command (macOS) to select multiple categories.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-employee-new-categories"><?php esc_html_e( 'Add new categories', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="smooth-booking-employee-new-categories" name="employee_new_categories" value="" placeholder="<?php esc_attr_e( 'e.g. General; Orthodontist', 'smooth-booking' ); ?>" />
                                <p class="description"><?php esc_html_e( 'Separate multiple categories with commas, semicolons or new lines.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( $is_edit ? __( 'Update employee', 'smooth-booking' ) : __( 'Add employee', 'smooth-booking' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Format MySQL datetime values for display.
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
     * Retrieve the edit link for the given employee.
     */
    private function get_edit_link( int $employee_id ): string {
        return add_query_arg(
            [
                'page'        => self::MENU_SLUG,
                'action'      => 'edit',
                'employee_id' => $employee_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    /**
     * Retrieve the base menu page URL.
     */
    private function get_base_page(): string {
        return $this->get_view_link( 'active' );
    }

    /**
     * Retrieve a menu page URL for the requested view.
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
     * Build avatar markup for a given employee.
     */
    private function get_employee_avatar_html( Employee $employee ): string {
        return $this->get_avatar_html( $employee->get_profile_image_id(), $employee->get_name() );
    }

    /**
     * Generate avatar markup for a given attachment.
     */
    private function get_avatar_html( ?int $attachment_id, string $name ): string {
        if ( $attachment_id ) {
            $image = wp_get_attachment_image(
                $attachment_id,
                'thumbnail',
                false,
                [
                    'class' => 'smooth-booking-avatar-image',
                    'alt'   => $name,
                ]
            );

            if ( $image ) {
                return '<span class="smooth-booking-avatar-wrapper">' . $image . '</span>';
            }
        }

        $placeholder = sprintf(
            '<span class="smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="screen-reader-text">%s</span></span>',
            esc_html( $name )
        );

        return $placeholder;
    }

    /**
     * Persist a flash notice for the current user.
     */
    private function add_notice( string $type, string $message ): void {
        $key = $this->get_notice_key();

        set_transient(
            $key,
            [
                'type'    => $type,
                'message' => $message,
            ],
            MINUTE_IN_SECONDS
        );
    }

    /**
     * Retrieve and clear the current notice.
     *
     * @return array{type:string,message:string}|null
     */
    private function consume_notice(): ?array {
        $key    = $this->get_notice_key();
        $notice = get_transient( $key );

        if ( false !== $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) {
            delete_transient( $key );
            return $notice;
        }

        return null;
    }

    /**
     * Get the transient key for the current user.
     */
    private function get_notice_key(): string {
        return sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
    }
}
