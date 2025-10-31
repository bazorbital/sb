<?php
/**
 * Employees administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\BusinessHours\BusinessHoursService;
use SmoothBooking\Domain\Employees\EmployeeCategory;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceCategory;
use SmoothBooking\Domain\Services\ServiceService;
use WP_Error;
use function abs;
use function absint;
use function array_filter;
use function array_map;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function is_array;
use function is_wp_error;
use function number_format_i18n;
use function plugins_url;
use function preg_match;
use function round;
use function sanitize_key;
use function substr;
use function trim;
use function wp_localize_script;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Renders and handles the employees management interface.
 */
class EmployeesPage {
    use AdminStylesTrait;
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
     * @var LocationService
     */
    private LocationService $location_service;

    /**
     * @var ServiceService
     */
    private ServiceService $service_service;

    /**
     * @var BusinessHoursService
     */
    private BusinessHoursService $business_hours_service;

    /**
     * Constructor.
     */
    public function __construct( EmployeeService $service, LocationService $location_service, ServiceService $service_service, BusinessHoursService $business_hours_service ) {
        $this->service                 = $service;
        $this->location_service        = $location_service;
        $this->service_service         = $service_service;
        $this->business_hours_service  = $business_hours_service;
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
        $locations  = $this->location_service->list_locations();
        $service_categories = $this->service_service->list_categories();
        $services          = $this->service_service->list_services();
        $service_groups    = $this->group_services_by_category( $service_categories, $services );
        $days              = $this->business_hours_service->get_days();
        $location_schedules = $this->build_location_schedule_map( $locations );

        wp_localize_script(
            'smooth-booking-admin-employees',
            'SmoothBookingEmployees',
            [
                'confirmDelete'    => __( 'Are you sure you want to delete this employee?', 'smooth-booking' ),
                'chooseImage'      => __( 'Select profile image', 'smooth-booking' ),
                'useImage'         => __( 'Use image', 'smooth-booking' ),
                'removeImage'      => __( 'Remove image', 'smooth-booking' ),
                'placeholderHtml'  => $this->get_avatar_html( null, esc_html__( 'Employee avatar', 'smooth-booking' ) ),
                'locationSchedules'=> $this->prepare_location_schedule_for_js( $location_schedules ),
                'scheduleDays'     => $this->prepare_schedule_days_for_js( $days ),
                'strings'          => [
                    'removeBreak'  => __( 'Remove this break?', 'smooth-booking' ),
                    'copySchedule' => __( 'Copy this schedule to all following days?', 'smooth-booking' ),
                    'applyLocation'=> __( 'Replace the schedule with the selected location hours?', 'smooth-booking' ),
                ],
            ]
        );

        $should_open_form  = $editing_employee instanceof Employee;
        $form_container_id = 'smooth-booking-employee-form-panel';
        $open_label        = __( 'Add new employee', 'smooth-booking' );
        $close_label       = __( 'Close form', 'smooth-booking' );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-employees-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Employees', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Manage staff members available for booking assignments.', 'smooth-booking' ); ?></p>
                    </div>
                    <div class="smooth-booking-admin-header__actions">
                        <button type="button" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-open-form" data-target="employee-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
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

            <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-employee-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="employee-form" data-focus-selector="#smooth-booking-employee-name">
                <?php $this->render_employee_form( $editing_employee, $categories, $locations, $service_groups, $days, $location_schedules ); ?>
            </div>

            <h2><?php echo esc_html__( 'Employee list', 'smooth-booking' ); ?></h2>

            <div class="smooth-booking-toolbar">
                <?php if ( $show_deleted ) : ?>
                    <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( $this->get_view_link( 'active' ) ); ?>">
                        <?php esc_html_e( 'Back to active employees', 'smooth-booking' ); ?>
                    </a>
                <?php else : ?>
                    <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( $this->get_view_link( 'deleted' ) ); ?>">
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
                                        <button type="button" class="sba-btn sba-btn--icon-without-box smooth-booking-actions-toggle" aria-haspopup="true" aria-expanded="false">
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
            'locations'        => isset( $_POST['employee_locations'] ) ? array_map( 'wp_unslash', (array) $_POST['employee_locations'] ) : [],
            'services'         => isset( $_POST['employee_services'] ) ? wp_unslash( (array) $_POST['employee_services'] ) : [],
            'schedule'         => isset( $_POST['employee_schedule'] ) ? wp_unslash( (array) $_POST['employee_schedule'] ) : [],
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

        wp_enqueue_media();

        $this->enqueue_admin_styles( [ 'wp-color-picker' ] );

        wp_enqueue_style(
            'smooth-booking-admin-employees',
            plugins_url( 'assets/css/admin-employees.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-admin-employees',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/js/admin-employees.js',
            [ 'jquery', 'wp-color-picker' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

    }

    /**
     * Render the employee form for creating or editing.
     *
     * @param Employee|null $employee Employee being edited or null for creation.
     */
    private function render_employee_form( ?Employee $employee, array $all_categories, array $locations, array $service_groups, array $days, array $location_schedules ): void {
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

        $selected_locations = $is_edit ? $employee->get_location_ids() : [];
        $assigned_services  = [];

        if ( $is_edit ) {
            foreach ( $employee->get_services() as $assignment ) {
                if ( isset( $assignment['service_id'] ) ) {
                    $assigned_services[ (int) $assignment['service_id'] ] = $assignment;
                }
            }
        }

        $schedule = $this->normalize_schedule_for_view( $is_edit ? $employee->get_schedule() : [] );

        if ( $this->schedule_is_empty( $schedule ) ) {
            $default_location_id = ! empty( $selected_locations )
                ? (int) $selected_locations[0]
                : (int) array_key_first( $location_schedules );

            if ( $default_location_id && isset( $location_schedules[ $default_location_id ] ) ) {
                $schedule = $this->normalize_schedule_for_view( $location_schedules[ $default_location_id ] );
            }
        }

        $schedule = $this->normalize_schedule_for_view( $schedule );
        ?>
        <div class="smooth-booking-employee-form-card smooth-booking-card">
            <div class="smooth-booking-form-header">
                <h2><?php echo $is_edit ? esc_html__( 'Edit employee', 'smooth-booking' ) : esc_html__( 'Add new employee', 'smooth-booking' ); ?></h2>
                <div class="smooth-booking-form-header__actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-cancel">
                            <?php esc_html_e( 'Back to list', 'smooth-booking' ); ?>
                        </a>
                    <?php else : ?>
                        <button type="button" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-dismiss" data-target="employee-form">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <form class="smooth-booking-employee-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'smooth_booking_save_employee', '_smooth_booking_save_nonce' ); ?>
                <input type="hidden" name="action" value="smooth_booking_save_employee" />
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="employee_id" value="<?php echo esc_attr( (string) $employee->get_id() ); ?>" />
                <?php endif; ?>

                <div class="smooth-booking-form-sections">
                    <div class="smooth-booking-form-tabs" role="tablist">
                        <button type="button" class="smooth-booking-form-tab is-active" id="smooth-booking-employee-tab-general" data-target="smooth-booking-employee-section-general" role="tab" aria-controls="smooth-booking-employee-section-general" aria-selected="true">
                            <?php esc_html_e( 'General', 'smooth-booking' ); ?>
                        </button>
                        <button type="button" class="smooth-booking-form-tab" id="smooth-booking-employee-tab-locations" data-target="smooth-booking-employee-section-locations" role="tab" aria-controls="smooth-booking-employee-section-locations" aria-selected="false">
                            <?php esc_html_e( 'Location', 'smooth-booking' ); ?>
                        </button>
                        <button type="button" class="smooth-booking-form-tab" id="smooth-booking-employee-tab-services" data-target="smooth-booking-employee-section-services" role="tab" aria-controls="smooth-booking-employee-section-services" aria-selected="false">
                            <?php esc_html_e( 'Services', 'smooth-booking' ); ?>
                        </button>
                        <button type="button" class="smooth-booking-form-tab" id="smooth-booking-employee-tab-schedule" data-target="smooth-booking-employee-section-schedule" role="tab" aria-controls="smooth-booking-employee-section-schedule" aria-selected="false">
                            <?php esc_html_e( 'Schedule', 'smooth-booking' ); ?>
                        </button>
                    </div>
                    <div class="smooth-booking-form-panels">
                        <section id="smooth-booking-employee-section-general" class="smooth-booking-form-panel is-active" role="tabpanel" aria-labelledby="smooth-booking-employee-tab-general">
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
                                                <button type="button" class="sba-btn sba-btn__small sba-btn__filled smooth-booking-avatar-select"><?php esc_html_e( 'Select image', 'smooth-booking' ); ?></button>
                                                <button type="button" class="sba-btn sba-btn__small sba-btn__filled-light smooth-booking-avatar-remove" <?php if ( ! $profile_image_id ) : ?>style="display:none"<?php endif; ?>><?php esc_html_e( 'Remove image', 'smooth-booking' ); ?></button>
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
                        </section>
                        <section id="smooth-booking-employee-section-locations" class="smooth-booking-form-panel" role="tabpanel" aria-labelledby="smooth-booking-employee-tab-locations" hidden>
                            <?php if ( empty( $locations ) ) : ?>
                                <p class="description"><?php esc_html_e( 'Create a location first to assign employees.', 'smooth-booking' ); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e( 'Select the locations where this employee is available.', 'smooth-booking' ); ?></p>
                                <ul class="smooth-booking-location-list">
                                    <?php foreach ( $locations as $location ) : ?>
                                        <?php if ( ! $location instanceof Location ) { continue; } ?>
                                        <li class="smooth-booking-location-option">
                                            <label>
                                                <input type="checkbox" name="employee_locations[]" value="<?php echo esc_attr( (string) $location->get_id() ); ?>" <?php checked( in_array( $location->get_id(), $selected_locations, true ) ); ?> />
                                                <span><?php echo esc_html( $location->get_name() ); ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                        <section id="smooth-booking-employee-section-services" class="smooth-booking-form-panel" role="tabpanel" aria-labelledby="smooth-booking-employee-tab-services" hidden>
                            <p class="description"><?php esc_html_e( 'Choose which services this employee can perform and override pricing if needed.', 'smooth-booking' ); ?></p>
                            <div class="smooth-booking-services">
                                <?php foreach ( $service_groups as $group_key => $group ) : ?>
                                    <?php
                                    $category      = $group['category'];
                                    $category_id   = $category instanceof ServiceCategory ? (string) $category->get_id() : 'uncategorized';
                                    $category_name = $category instanceof ServiceCategory ? $category->get_name() : esc_html__( 'Uncategorized services', 'smooth-booking' );
                                    $all_selected  = $this->is_service_group_selected( $group['services'], $assigned_services );
                                    ?>
                                    <div class="smooth-booking-services-group" data-category="<?php echo esc_attr( $category_id ); ?>">
                                        <div class="smooth-booking-services-group__header">
                                            <label>
                                                <input type="checkbox" class="smooth-booking-services-group-toggle" data-category="<?php echo esc_attr( $category_id ); ?>" <?php checked( $all_selected ); ?> />
                                                <span><?php echo esc_html( $category_name ); ?></span>
                                            </label>
                                        </div>
                                        <div class="smooth-booking-services-group__items">
                                            <?php if ( empty( $group['services'] ) ) : ?>
                                                <p class="description"><?php esc_html_e( 'No services available in this category yet.', 'smooth-booking' ); ?></p>
                                            <?php else : ?>
                                                <?php foreach ( $group['services'] as $service ) : ?>
                                                    <?php if ( ! $service instanceof Service ) { continue; } ?>
                                                    <?php
                                                    $service_id   = $service->get_id();
                                                    $is_selected  = isset( $assigned_services[ $service_id ] );
                                                    $override     = $is_selected ? $assigned_services[ $service_id ]['price'] : null;
                                                    ?>
                                                    <div class="smooth-booking-service-item" data-category="<?php echo esc_attr( $category_id ); ?>">
                                                        <label class="smooth-booking-service-item__name">
                                                            <input type="checkbox" name="employee_services[<?php echo esc_attr( (string) $service_id ); ?>][selected]" value="1" class="smooth-booking-service-toggle" data-category="<?php echo esc_attr( $category_id ); ?>" <?php checked( $is_selected ); ?> />
                                                            <span><?php echo esc_html( $service->get_name() ); ?></span>
                                                        </label>
                                                        <div class="smooth-booking-service-item__pricing">
                                                            <span class="smooth-booking-service-item__base"><?php echo esc_html( sprintf( /* translators: %s: base service price. */ __( 'Base: %s', 'smooth-booking' ), $this->format_service_price( $service->get_price() ) ) ); ?></span>
                                                            <label class="screen-reader-text" for="smooth-booking-service-price-<?php echo esc_attr( (string) $service_id ); ?>"><?php esc_html_e( 'Custom price', 'smooth-booking' ); ?></label>
                                                            <input type="number" id="smooth-booking-service-price-<?php echo esc_attr( (string) $service_id ); ?>" name="employee_services[<?php echo esc_attr( (string) $service_id ); ?>][price]" class="small-text smooth-booking-service-price" value="<?php echo null !== $override ? esc_attr( (string) $override ) : ''; ?>" step="0.01" min="0" placeholder="<?php esc_attr_e( 'Override', 'smooth-booking' ); ?>" <?php disabled( ! $is_selected ); ?> />
                                                        </div>
                                                        <input type="hidden" name="employee_services[<?php echo esc_attr( (string) $service_id ); ?>][service_id]" value="<?php echo esc_attr( (string) $service_id ); ?>" />
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section id="smooth-booking-employee-section-schedule" class="smooth-booking-form-panel" role="tabpanel" aria-labelledby="smooth-booking-employee-tab-schedule" hidden>
                            <p class="description"><?php esc_html_e( 'Define the working hours and breaks for each day of the week.', 'smooth-booking' ); ?></p>
                            <?php if ( ! empty( $locations ) ) : ?>
                                <div class="smooth-booking-schedule-toolbar">
                                    <label for="smooth-booking-schedule-location"><?php esc_html_e( 'Apply hours from location', 'smooth-booking' ); ?></label>
                                    <select id="smooth-booking-schedule-location" class="smooth-booking-schedule-location">
                                        <?php foreach ( $locations as $location ) : ?>
                                            <?php if ( ! $location instanceof Location ) { continue; } ?>
                                            <option value="<?php echo esc_attr( (string) $location->get_id() ); ?>"><?php echo esc_html( $location->get_name() ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button smooth-booking-schedule-apply"><?php esc_html_e( 'Apply', 'smooth-booking' ); ?></button>
                                </div>
                            <?php endif; ?>
                            <div class="smooth-booking-schedule" data-break-template-target="smooth-booking-schedule-break-template">
                                <?php foreach ( $days as $day_index => $day_info ) : ?>
                                    <?php
                                    if ( ! is_array( $day_info ) || ! isset( $day_info['label'] ) ) {
                                        continue;
                                    }
                                    $day_id       = (int) $day_index;
                                    $day_label    = (string) $day_info['label'];
                                    $day_schedule = $schedule[ $day_id ] ?? [ 'start_time' => null, 'end_time' => null, 'is_off_day' => true, 'breaks' => [] ];
                                    $is_off_day   = ! empty( $day_schedule['is_off_day'] );
                                    $start_value  = $day_schedule['start_time'] ?? '';
                                    $end_value    = $day_schedule['end_time'] ?? '';
                                    $breaks       = is_array( $day_schedule['breaks'] ?? null ) ? $day_schedule['breaks'] : [];
                                    ?>
                                    <div class="smooth-booking-schedule-row" data-day="<?php echo esc_attr( (string) $day_id ); ?>">
                                        <div class="smooth-booking-schedule-day"><?php echo esc_html( $day_label ); ?></div>
                                        <div class="smooth-booking-schedule-hours">
                                            <label>
                                                <span class="screen-reader-text"><?php esc_html_e( 'Start time', 'smooth-booking' ); ?></span>
                                                <input type="time" name="employee_schedule[<?php echo esc_attr( (string) $day_id ); ?>][start]" class="smooth-booking-schedule-start" value="<?php echo esc_attr( (string) $start_value ); ?>" step="300" <?php disabled( $is_off_day ); ?> />
                                            </label>
                                            <span class="smooth-booking-schedule-separator"><?php esc_html_e( 'to', 'smooth-booking' ); ?></span>
                                            <label>
                                                <span class="screen-reader-text"><?php esc_html_e( 'End time', 'smooth-booking' ); ?></span>
                                                <input type="time" name="employee_schedule[<?php echo esc_attr( (string) $day_id ); ?>][end]" class="smooth-booking-schedule-end" value="<?php echo esc_attr( (string) $end_value ); ?>" step="300" <?php disabled( $is_off_day ); ?> />
                                            </label>
                                            <button type="button" class="button button-secondary smooth-booking-schedule-copy" data-day="<?php echo esc_attr( (string) $day_id ); ?>" aria-label="<?php esc_attr_e( 'Copy hours to following days', 'smooth-booking' ); ?>">
                                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                        <div class="smooth-booking-schedule-actions">
                                            <label class="smooth-booking-schedule-off">
                                                <input type="checkbox" name="employee_schedule[<?php echo esc_attr( (string) $day_id ); ?>][is_off]" value="1" class="smooth-booking-schedule-off-toggle" <?php checked( $is_off_day ); ?> />
                                                <span><?php esc_html_e( 'Off', 'smooth-booking' ); ?></span>
                                            </label>
                                            <button type="button" class="button smooth-booking-schedule-add-break" data-day="<?php echo esc_attr( (string) $day_id ); ?>" <?php if ( $is_off_day ) : ?>disabled<?php endif; ?>><?php esc_html_e( 'Add break', 'smooth-booking' ); ?></button>
                                        </div>
                                        <div class="smooth-booking-schedule-breaks" data-day="<?php echo esc_attr( (string) $day_id ); ?>">
                                            <?php foreach ( $breaks as $break_index => $break ) : ?>
                                                <?php
                                                $break_start = isset( $break['start_time'] ) ? (string) $break['start_time'] : '';
                                                $break_end   = isset( $break['end_time'] ) ? (string) $break['end_time'] : '';
                                                ?>
                                                <div class="smooth-booking-schedule-break">
                                                    <label>
                                                        <span class="screen-reader-text"><?php esc_html_e( 'Break start', 'smooth-booking' ); ?></span>
                                                        <input type="time" name="employee_schedule[<?php echo esc_attr( (string) $day_id ); ?>][breaks][<?php echo esc_attr( (string) $break_index ); ?>][start]" value="<?php echo esc_attr( $break_start ); ?>" step="300" class="smooth-booking-schedule-break-start" <?php disabled( $is_off_day ); ?> />
                                                    </label>
                                                    <span class="smooth-booking-schedule-separator"><?php esc_html_e( 'to', 'smooth-booking' ); ?></span>
                                                    <label>
                                                        <span class="screen-reader-text"><?php esc_html_e( 'Break end', 'smooth-booking' ); ?></span>
                                                        <input type="time" name="employee_schedule[<?php echo esc_attr( (string) $day_id ); ?>][breaks][<?php echo esc_attr( (string) $break_index ); ?>][end]" value="<?php echo esc_attr( $break_end ); ?>" step="300" class="smooth-booking-schedule-break-end" <?php disabled( $is_off_day ); ?> />
                                                    </label>
                                                    <button type="button" class="button button-link-delete smooth-booking-schedule-remove-break" aria-label="<?php esc_attr_e( 'Remove break', 'smooth-booking' ); ?>">&times;</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="smooth-booking-schedule-break smooth-booking-schedule-break--template" data-template="break" hidden>
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Break start', 'smooth-booking' ); ?></span>
                                    <input type="time" data-break-input="start" step="300" />
                                </label>
                                <span class="smooth-booking-schedule-separator"><?php esc_html_e( 'to', 'smooth-booking' ); ?></span>
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Break end', 'smooth-booking' ); ?></span>
                                    <input type="time" data-break-input="end" step="300" />
                                </label>
                                <button type="button" class="button button-link-delete smooth-booking-schedule-remove-break" aria-label="<?php esc_attr_e( 'Remove break', 'smooth-booking' ); ?>">&times;</button>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="smooth-booking-form-actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__large sba-btn__filled-light smooth-booking-form-cancel">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </a>
                    <?php else : ?>
                        <button type="button" class="sba-btn sba-btn__large sba-btn__filled-light smooth-booking-form-dismiss" data-target="employee-form">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="sba-btn sba-btn--primary sba-btn__large smooth-booking-form-submit">
                        <?php echo esc_html( $is_edit ? __( 'Update employee', 'smooth-booking' ) : __( 'Add employee', 'smooth-booking' ) ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Group services by category for display.
     *
     * @param ServiceCategory[] $categories Service categories.
     * @param Service[]         $services   Services to group.
     *
     * @return array<int, array{category:?ServiceCategory,services:Service[]}>
     */
    private function group_services_by_category( array $categories, array $services ): array {
        $groups       = [];
        $order        = [];
        $uncategorized_key = 'uncategorized';

        foreach ( $categories as $category ) {
            if ( ! $category instanceof ServiceCategory ) {
                continue;
            }

            $key           = 'category_' . $category->get_id();
            $groups[ $key ] = [
                'category' => $category,
                'services' => [],
            ];
            $order[]        = $key;
        }

        $groups[ $uncategorized_key ] = [
            'category' => null,
            'services' => [],
        ];

        foreach ( $services as $service ) {
            if ( ! $service instanceof Service ) {
                continue;
            }

            $service_categories = array_filter(
                $service->get_categories(),
                static function ( $candidate ): bool {
                    return $candidate instanceof ServiceCategory;
                }
            );

            if ( empty( $service_categories ) ) {
                $groups[ $uncategorized_key ]['services'][] = $service;
                continue;
            }

            foreach ( $service_categories as $service_category ) {
                $key = 'category_' . $service_category->get_id();

                if ( ! isset( $groups[ $key ] ) ) {
                    $groups[ $key ] = [
                        'category' => $service_category,
                        'services' => [],
                    ];
                    $order[]      = $key;
                }

                $groups[ $key ]['services'][] = $service;
            }
        }

        $ordered_groups = [];

        foreach ( $order as $key ) {
            if ( ! isset( $groups[ $key ] ) ) {
                continue;
            }

            $ordered_groups[] = $groups[ $key ];
        }

        if ( ! empty( $groups[ $uncategorized_key ]['services'] ) ) {
            $ordered_groups[] = $groups[ $uncategorized_key ];
        }

        return $ordered_groups;
    }

    /**
     * Determine whether all services in a group are selected.
     *
     * @param Service[] $services           Services in the group.
     * @param array     $assigned_services  Selected services keyed by service ID.
     */
    private function is_service_group_selected( array $services, array $assigned_services ): bool {
        $has_services = false;

        foreach ( $services as $service ) {
            if ( ! $service instanceof Service ) {
                continue;
            }

            $has_services = true;

            if ( ! isset( $assigned_services[ $service->get_id() ] ) ) {
                return false;
            }
        }

        return $has_services;
    }

    /**
     * Format a service price for display.
     */
    private function format_service_price( ?float $price ): string {
        if ( null === $price ) {
            return '—';
        }

        $rounded = round( $price, 2 );
        $decimals = abs( $rounded - round( $rounded ) ) < 0.01 ? 0 : 2;

        return number_format_i18n( $rounded, $decimals );
    }

    /**
     * Build a schedule map keyed by location identifier.
     *
     * @param Location[] $locations Available locations.
     *
     * @return array<int, array<int, array{start_time:string,end_time:string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>>
     */
    private function build_location_schedule_map( array $locations ): array {
        $map = [];

        foreach ( $locations as $location ) {
            if ( ! $location instanceof Location ) {
                continue;
            }

            $hours = $this->business_hours_service->get_location_hours( $location->get_id() );

            if ( is_wp_error( $hours ) ) {
                continue;
            }

            $schedule = [];

            foreach ( $hours as $day => $definition ) {
                $day_index = (int) $day;

                if ( $day_index < 1 || $day_index > 7 || ! is_array( $definition ) ) {
                    continue;
                }

                $schedule[ $day_index ] = [
                    'start_time' => isset( $definition['open'] ) ? (string) $definition['open'] : '',
                    'end_time'   => isset( $definition['close'] ) ? (string) $definition['close'] : '',
                    'is_off_day' => ! empty( $definition['is_closed'] ),
                    'breaks'     => [],
                ];
            }

            $map[ $location->get_id() ] = $this->normalize_schedule_for_view( $schedule );
        }

        return $map;
    }

    /**
     * Prepare location schedules for JavaScript consumption.
     *
     * @param array<int, array<int, array{start_time:string,end_time:string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>> $location_schedules Location schedules.
     *
     * @return array<int, array<int, array{start_time:string,end_time:string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>>
     */
    private function prepare_location_schedule_for_js( array $location_schedules ): array {
        $prepared = [];

        foreach ( $location_schedules as $location_id => $schedule ) {
            $location_id = (int) $location_id;

            if ( $location_id <= 0 ) {
                continue;
            }

            $normalized = $this->normalize_schedule_for_view( is_array( $schedule ) ? $schedule : [] );

            foreach ( $normalized as $day => $definition ) {
                $prepared[ $location_id ][ $day ] = [
                    'start_time' => $definition['start_time'],
                    'end_time'   => $definition['end_time'],
                    'is_off_day' => ! empty( $definition['is_off_day'] ),
                    'breaks'     => $definition['breaks'],
                ];
            }
        }

        return $prepared;
    }

    /**
     * Prepare day labels for JavaScript.
     *
     * @param array<int, array{label:string}> $days Day definitions.
     *
     * @return array<int, string>
     */
    private function prepare_schedule_days_for_js( array $days ): array {
        $prepared = [];

        foreach ( $days as $index => $day ) {
            if ( ! is_array( $day ) || ! isset( $day['label'] ) ) {
                continue;
            }

            $prepared[ (int) $index ] = (string) $day['label'];
        }

        return $prepared;
    }

    /**
     * Normalize schedule data for display.
     *
     * @param array<int|string, mixed> $schedule Raw schedule definition.
     *
     * @return array<int, array{start_time:string,end_time:string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>>
     */
    private function normalize_schedule_for_view( array $schedule ): array {
        $normalized = [];

        for ( $day = 1; $day <= 7; $day++ ) {
            $normalized[ $day ] = [
                'start_time' => '',
                'end_time'   => '',
                'is_off_day' => true,
                'breaks'     => [],
            ];
        }

        foreach ( $schedule as $day => $definition ) {
            $day_index = (int) $day;

            if ( $day_index < 1 || $day_index > 7 || ! is_array( $definition ) ) {
                continue;
            }

            $is_off = false;
            if ( isset( $definition['is_off_day'] ) ) {
                $is_off = (bool) $definition['is_off_day'];
            } elseif ( isset( $definition['is_off'] ) ) {
                $is_off = (bool) $definition['is_off'];
            }

            if ( $is_off ) {
                $normalized[ $day_index ] = [
                    'start_time' => '',
                    'end_time'   => '',
                    'is_off_day' => true,
                    'breaks'     => [],
                ];
                continue;
            }

            $start_time = '';
            if ( isset( $definition['start_time'] ) ) {
                $start_time = $this->sanitize_schedule_time_for_display( (string) $definition['start_time'] );
            } elseif ( isset( $definition['start'] ) ) {
                $start_time = $this->sanitize_schedule_time_for_display( (string) $definition['start'] );
            }

            $end_time = '';
            if ( isset( $definition['end_time'] ) ) {
                $end_time = $this->sanitize_schedule_time_for_display( (string) $definition['end_time'] );
            } elseif ( isset( $definition['end'] ) ) {
                $end_time = $this->sanitize_schedule_time_for_display( (string) $definition['end'] );
            }

            if ( '' === $start_time || '' === $end_time ) {
                $normalized[ $day_index ] = [
                    'start_time' => '',
                    'end_time'   => '',
                    'is_off_day' => true,
                    'breaks'     => [],
                ];
                continue;
            }

            $breaks = [];

            if ( isset( $definition['breaks'] ) && is_array( $definition['breaks'] ) ) {
                foreach ( $definition['breaks'] as $break ) {
                    if ( ! is_array( $break ) ) {
                        continue;
                    }

                    $break_start = '';
                    if ( isset( $break['start_time'] ) ) {
                        $break_start = $this->sanitize_schedule_time_for_display( (string) $break['start_time'] );
                    } elseif ( isset( $break['start'] ) ) {
                        $break_start = $this->sanitize_schedule_time_for_display( (string) $break['start'] );
                    }

                    $break_end = '';
                    if ( isset( $break['end_time'] ) ) {
                        $break_end = $this->sanitize_schedule_time_for_display( (string) $break['end_time'] );
                    } elseif ( isset( $break['end'] ) ) {
                        $break_end = $this->sanitize_schedule_time_for_display( (string) $break['end'] );
                    }

                    if ( '' === $break_start || '' === $break_end ) {
                        continue;
                    }

                    $breaks[] = [
                        'start_time' => $break_start,
                        'end_time'   => $break_end,
                    ];
                }
            }

            $normalized[ $day_index ] = [
                'start_time' => $start_time,
                'end_time'   => $end_time,
                'is_off_day' => false,
                'breaks'     => $breaks,
            ];
        }

        return $normalized;
    }

    /**
     * Determine if a schedule is empty.
     *
     * @param array<int, array{start_time:string,end_time:string,is_off_day:bool,breaks:array<int,array{start_time:string,end_time:string}}>> $schedule Schedule definition.
     */
    private function schedule_is_empty( array $schedule ): bool {
        foreach ( $schedule as $definition ) {
            if ( ! is_array( $definition ) ) {
                continue;
            }

            $is_off = ! empty( $definition['is_off_day'] );
            $has_hours = ( isset( $definition['start_time'] ) && '' !== $definition['start_time'] )
                && ( isset( $definition['end_time'] ) && '' !== $definition['end_time'] );
            $has_breaks = ! empty( $definition['breaks'] );

            if ( $has_hours || ( ! $is_off && $has_breaks ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize time value for display.
     */
    private function sanitize_schedule_time_for_display( string $raw ): string {
        $value = substr( trim( $raw ), 0, 5 );

        if ( preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
            return $value;
        }

        return '';
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
