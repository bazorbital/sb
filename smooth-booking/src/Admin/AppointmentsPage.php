<?php
/**
 * Appointments administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use DateTimeInterface;
use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Appointments\AppointmentService;
use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerService;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Employees\EmployeeService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use WP_Error;

use function __;
use function absint;
use function add_query_arg;
use function admin_url;
use function array_merge;
use function check_admin_referer;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function esc_url_raw;
use function get_current_user_id;
use function get_option;
use function get_transient;
use function in_array;
use function is_array;
use function is_rtl;
use function is_ssl;
use function mb_strlen;
use function mb_substr;
use function paginate_links;
use function plugins_url;
use function date;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function selected;
use function set_transient;
use function checked;
use function _n;
use function sprintf;
use function trim;
use function wp_date;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_kses_post;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_parse_args;
use function wp_safe_redirect;
use function wp_unslash;
use function strtotime;

/**
 * Renders and handles the appointments management interface.
 */
class AppointmentsPage {
    use AdminStylesTrait;
    /**
     * Capability required to manage appointments.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Menu slug used for the appointments screen.
     */
    public const MENU_SLUG = 'smooth-booking-appointments';

    /**
     * Transient key template for admin notices.
     */
    private const NOTICE_TRANSIENT_TEMPLATE = 'smooth_booking_appointment_notice_%d';

    /**
     * Appointment service instance.
     */
    private AppointmentService $appointments;

    /**
     * Employee service instance.
     */
    private EmployeeService $employees;

    /**
     * Service service instance.
     */
    private ServiceService $services;

    /**
     * Customer service instance.
     */
    private CustomerService $customers;

    /**
     * Constructor.
     */
    public function __construct( AppointmentService $appointments, EmployeeService $employees, ServiceService $services, CustomerService $customers ) {
        $this->appointments = $appointments;
        $this->employees    = $employees;
        $this->services     = $services;
        $this->customers    = $customers;
    }

    /**
     * Render the appointments admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage appointments.', 'smooth-booking' ) );
        }

        $notice           = $this->consume_notice();
        $action           = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $appointment_id   = isset( $_GET['appointment_id'] ) ? absint( $_GET['appointment_id'] ) : 0;
        $open             = isset( $_GET['open'] ) ? sanitize_key( wp_unslash( (string) $_GET['open'] ) ) : '';
        $query_args       = $this->get_query_args();
        $filters          = $this->get_filters();
        $editing_item     = null;
        $editing_error    = null;

        if ( 'edit' === $action && $appointment_id > 0 ) {
            $appointment = $this->appointments->get_appointment( $appointment_id );
            if ( is_wp_error( $appointment ) ) {
                $editing_error = $appointment->get_error_message();
            } else {
                $editing_item = $appointment;
            }
        }

        $query_filters = [
            'appointment_id'   => $filters['appointment_id'],
            'appointment_from' => $filters['appointment_from_sql'],
            'appointment_to'   => $filters['appointment_to_sql'],
            'created_from'     => $filters['created_from_sql'],
            'created_to'       => $filters['created_to_sql'],
            'customer_search'  => $filters['customer_search'],
            'employee_id'      => $filters['employee_id'],
            'service_id'       => $filters['service_id'],
            'status'           => $filters['status'],
        ];

        $pagination = $this->appointments->paginate_appointments( array_merge( $query_args, $query_filters ) );
        $appointments = $pagination['appointments'];
        $total        = (int) $pagination['total'];
        $per_page     = (int) $pagination['per_page'];
        $paged        = (int) $pagination['paged'];

        $employees = $this->employees->list_employees();
        $services  = $this->services->list_services();
        $customers = $this->customers->paginate_customers( [ 'per_page' => 200 ] );
        $customer_items = isset( $customers['customers'] ) && is_array( $customers['customers'] ) ? $customers['customers'] : [];

        $should_open_form  = $editing_item instanceof Appointment || 'form' === $open;
        $form_container_id = 'smooth-booking-appointment-form-panel';
        $open_label        = __( 'Add new appointment', 'smooth-booking' );
        $close_label       = __( 'Close form', 'smooth-booking' );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-appointments-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Appointments', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Create, review, and manage bookings across your team.', 'smooth-booking' ); ?></p>
                    </div>
                    <div class="smooth-booking-admin-header__actions">
                        <button type="button" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-open-form" data-target="appointment-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
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

            <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-appointment-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="appointment-form" data-focus-selector="#smooth-booking-appointment-service"<?php echo $should_open_form ? '' : ' hidden'; ?>>
                <?php $this->render_appointment_form( $editing_item, $employees, $services, $customer_items ); ?>
            </div>

            <h2><?php esc_html_e( 'Appointment list', 'smooth-booking' ); ?></h2>

            <?php $this->render_filters( $filters, $employees, $services ); ?>

            <?php $this->render_table( $appointments, $filters, $query_args, $paged, $per_page, $total ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle appointment save request.
     */
    public function handle_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage appointments.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_appointment' );

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $redirect       = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wp_http_referer'] ) ) : $this->get_base_page();

        $payload = [
            'provider_id'        => isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0,
            'service_id'         => isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0,
            'customer_id'        => isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0,
            'appointment_date'   => isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['appointment_date'] ) ) : '',
            'appointment_start'  => isset( $_POST['appointment_start'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['appointment_start'] ) ) : '',
            'appointment_end'    => isset( $_POST['appointment_end'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['appointment_end'] ) ) : '',
            'notes'              => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) ) : '',
            'internal_note'      => isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['internal_note'] ) ) : '',
            'status'             => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : 'pending',
            'payment_status'     => isset( $_POST['payment_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['payment_status'] ) ) : '',
            'send_notifications' => ! empty( $_POST['send_notifications'] ),
            'is_recurring'       => ! empty( $_POST['is_recurring'] ),
            'customer_email'     => isset( $_POST['customer_email'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['customer_email'] ) ) : '',
            'customer_phone'     => isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['customer_phone'] ) ) : '',
        ];

        $result = $appointment_id > 0
            ? $this->appointments->update_appointment( $appointment_id, $payload )
            : $this->appointments->create_appointment( $payload );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
            wp_safe_redirect( $redirect );
            exit;
        }

        $message = $appointment_id > 0
            ? __( 'Appointment updated successfully.', 'smooth-booking' )
            : __( 'Appointment created successfully.', 'smooth-booking' );

        $this->add_notice( 'success', $message );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle appointment delete.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage appointments.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_appointment' );

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $redirect       = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wp_http_referer'] ) ) : $this->get_base_page();

        if ( $appointment_id <= 0 ) {
            $this->add_notice( 'error', __( 'Invalid appointment.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        $result = $this->appointments->delete_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
        } else {
            $this->add_notice( 'success', __( 'Appointment deleted.', 'smooth-booking' ) );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle appointment restore.
     */
    public function handle_restore(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage appointments.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_restore_appointment' );

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $redirect       = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wp_http_referer'] ) ) : $this->get_base_page();

        if ( $appointment_id <= 0 ) {
            $this->add_notice( 'error', __( 'Invalid appointment.', 'smooth-booking' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        $result = $this->appointments->restore_appointment( $appointment_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
        } else {
            $this->add_notice( 'success', __( 'Appointment restored.', 'smooth-booking' ) );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Enqueue assets for the appointments page.
     */
    public function enqueue_assets( string $hook ): void {
        $allowed_hooks = [
            'smooth-booking_page_' . self::MENU_SLUG,
        ];

        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-admin-appointments',
            plugins_url( 'assets/css/admin-appointments.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'select2' );

        wp_enqueue_script(
            'smooth-booking-admin-appointments',
            plugins_url( 'assets/js/admin-appointments.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'select2' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        $settings = [
            'confirmDelete'   => __( 'Are you sure you want to delete this appointment?', 'smooth-booking' ),
            'confirmRestore'  => __( 'Restore this appointment?', 'smooth-booking' ),
            'addCustomerUrl'  => esc_url( add_query_arg( [ 'page' => CustomersPage::MENU_SLUG, 'open' => 'form' ], admin_url( 'admin.php' ) ) ),
        ];

        wp_localize_script( 'smooth-booking-admin-appointments', 'SmoothBookingAppointments', $settings );
    }

    /**
     * Render appointment form.
     *
     * @param Appointment|null $appointment Current appointment.
     * @param Employee[]       $employees   Available employees.
     * @param Service[]        $services    Available services.
     * @param Customer[]       $customers   Available customers.
     */
    private function render_appointment_form( ?Appointment $appointment, array $employees, array $services, array $customers ): void {
        $is_edit  = $appointment instanceof Appointment;
        $action   = $is_edit ? 'edit' : 'create';
        $date     = $is_edit ? $appointment->get_scheduled_start()->format( 'Y-m-d' ) : '';
        $start    = $is_edit ? $appointment->get_scheduled_start()->format( 'H:i' ) : '';
        $end      = $is_edit ? $appointment->get_scheduled_end()->format( 'H:i' ) : '';
        $status   = $is_edit ? $appointment->get_status() : 'pending';
        $payment  = $is_edit ? ( $appointment->get_payment_status() ?? '' ) : '';
        $notes    = $is_edit ? ( $appointment->get_notes() ?? '' ) : '';
        $internal = $is_edit ? ( $appointment->get_internal_note() ?? '' ) : '';
        $customer_email = $is_edit ? ( $appointment->get_customer_email() ?? '' ) : '';
        $customer_phone = $is_edit ? ( $appointment->get_customer_phone() ?? '' ) : '';
        $selected_employee = $is_edit ? $appointment->get_employee_id() : 0;
        $selected_service  = $is_edit ? $appointment->get_service_id() : 0;
        $selected_customer = $is_edit ? ( $appointment->get_customer_id() ?? 0 ) : 0;
        $should_notify     = $is_edit ? $appointment->should_notify() : false;
        $is_recurring      = $is_edit ? $appointment->is_recurring() : false;

        $statuses = [
            'pending'   => __( 'Pending', 'smooth-booking' ),
            'confirmed' => __( 'Confirmed', 'smooth-booking' ),
            'completed' => __( 'Completed', 'smooth-booking' ),
            'canceled'  => __( 'Canceled', 'smooth-booking' ),
        ];

        $payments = [
            ''            => __( 'Not set', 'smooth-booking' ),
            'pending'     => __( 'Pending', 'smooth-booking' ),
            'authorized'  => __( 'Authorized', 'smooth-booking' ),
            'paid'        => __( 'Paid', 'smooth-booking' ),
            'refunded'    => __( 'Refunded', 'smooth-booking' ),
            'failed'      => __( 'Failed', 'smooth-booking' ),
            'canceled'    => __( 'Canceled', 'smooth-booking' ),
        ];

        ?>
        <div class="smooth-booking-form-card">
            <div class="smooth-booking-form-card__header">
                <h2><?php echo esc_html( $is_edit ? __( 'Edit appointment', 'smooth-booking' ) : __( 'Add appointment', 'smooth-booking' ) ); ?></h2>
                <button type="button" class="button-link smooth-booking-form-dismiss" data-target="appointment-form"><?php esc_html_e( 'Close', 'smooth-booking' ); ?></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-form">
                <input type="hidden" name="action" value="smooth_booking_save_appointment" />
                <input type="hidden" name="appointment_id" value="<?php echo esc_attr( $is_edit ? (string) $appointment->get_id() : '0' ); ?>" />
                <?php wp_nonce_field( 'smooth_booking_save_appointment' ); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-provider"><?php esc_html_e( 'Provider', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="provider_id" id="smooth-booking-appointment-provider" class="smooth-booking-select2" data-placeholder="<?php esc_attr_e( 'Select provider', 'smooth-booking' ); ?>">
                                    <option value=""><?php esc_html_e( 'Select provider', 'smooth-booking' ); ?></option>
                                    <?php foreach ( $employees as $employee ) : ?>
                                        <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                                        <option value="<?php echo esc_attr( (string) $employee->get_id() ); ?>"<?php selected( $selected_employee, $employee->get_id() ); ?>><?php echo esc_html( $employee->get_name() ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-service"><?php esc_html_e( 'Service', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="service_id" id="smooth-booking-appointment-service" class="smooth-booking-select2" data-placeholder="<?php esc_attr_e( 'Select service', 'smooth-booking' ); ?>">
                                    <option value=""><?php esc_html_e( 'Select service', 'smooth-booking' ); ?></option>
                                    <?php foreach ( $services as $service ) : ?>
                                        <?php if ( ! $service instanceof Service ) { continue; } ?>
                                        <option value="<?php echo esc_attr( (string) $service->get_id() ); ?>"<?php selected( $selected_service, $service->get_id() ); ?>><?php echo esc_html( $service->get_name() ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Date & period', 'smooth-booking' ); ?></th>
                            <td>
                                <div class="smooth-booking-appointment-datetime">
                                    <label class="smooth-booking-inline-field" for="smooth-booking-appointment-date">
                                        <span><?php esc_html_e( 'Date', 'smooth-booking' ); ?></span>
                                        <input type="date" name="appointment_date" id="smooth-booking-appointment-date" value="<?php echo esc_attr( $date ); ?>" />
                                    </label>
                                    <label class="smooth-booking-inline-field" for="smooth-booking-appointment-start">
                                        <span><?php esc_html_e( 'Start', 'smooth-booking' ); ?></span>
                                        <select name="appointment_start" id="smooth-booking-appointment-start" class="smooth-booking-time-select">
                                            <option value=""><?php esc_html_e( 'Select start', 'smooth-booking' ); ?></option>
                                            <?php foreach ( $this->get_time_slots() as $slot ) : ?>
                                                <option value="<?php echo esc_attr( $slot ); ?>"<?php selected( $start, $slot ); ?>><?php echo esc_html( $slot ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="smooth-booking-inline-field" for="smooth-booking-appointment-end">
                                        <span><?php esc_html_e( 'End', 'smooth-booking' ); ?></span>
                                        <select name="appointment_end" id="smooth-booking-appointment-end" class="smooth-booking-time-select">
                                            <option value=""><?php esc_html_e( 'Select end', 'smooth-booking' ); ?></option>
                                            <?php foreach ( $this->get_time_slots() as $slot ) : ?>
                                                <option value="<?php echo esc_attr( $slot ); ?>"<?php selected( $end, $slot ); ?>><?php echo esc_html( $slot ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-repeat"><?php esc_html_e( 'Repeat this appointment', 'smooth-booking' ); ?></label></th>
                            <td>
                                <label for="smooth-booking-appointment-repeat" class="smooth-booking-checkbox">
                                    <input type="checkbox" name="is_recurring" id="smooth-booking-appointment-repeat" value="1" <?php checked( $is_recurring ); ?> />
                                    <span><?php esc_html_e( 'Enable recurrence for this booking.', 'smooth-booking' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-customer"><?php esc_html_e( 'Customer', 'smooth-booking' ); ?></label></th>
                            <td>
                                <div class="smooth-booking-customer-select">
                                    <select name="customer_id" id="smooth-booking-appointment-customer" class="smooth-booking-select2" data-placeholder="<?php esc_attr_e( 'Select customer', 'smooth-booking' ); ?>">
                                        <option value="0"><?php esc_html_e( 'Select customer', 'smooth-booking' ); ?></option>
                                        <?php foreach ( $customers as $customer ) : ?>
                                            <?php if ( ! $customer instanceof Customer ) { continue; } ?>
                                            <option value="<?php echo esc_attr( (string) $customer->get_id() ); ?>"<?php selected( $selected_customer, $customer->get_id() ); ?>><?php echo esc_html( $customer->get_name() ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => CustomersPage::MENU_SLUG, 'open' => 'form' ], admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Add customer', 'smooth-booking' ); ?></a>
                                </div>
                                <p class="description"><?php esc_html_e( 'Select an existing customer or create a new profile.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-status"><?php esc_html_e( 'Status', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="status" id="smooth-booking-appointment-status">
                                    <?php foreach ( $statuses as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-payment"><?php esc_html_e( 'Payment status', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="payment_status" id="smooth-booking-appointment-payment">
                                    <?php foreach ( $payments as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $payment, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-customer-email"><?php esc_html_e( 'Customer email', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="email" name="customer_email" id="smooth-booking-appointment-customer-email" value="<?php echo esc_attr( $customer_email ); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-customer-phone"><?php esc_html_e( 'Customer phone', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" name="customer_phone" id="smooth-booking-appointment-customer-phone" value="<?php echo esc_attr( $customer_phone ); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-notes"><?php esc_html_e( 'Internal note', 'smooth-booking' ); ?></label></th>
                            <td>
                                <textarea name="internal_note" id="smooth-booking-appointment-notes" class="large-text" rows="4"><?php echo esc_textarea( $internal ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'This text can be inserted into notifications with {internal_note} code.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-public-notes"><?php esc_html_e( 'Customer notes', 'smooth-booking' ); ?></label></th>
                            <td>
                                <textarea name="notes" id="smooth-booking-appointment-public-notes" class="large-text" rows="4"><?php echo esc_textarea( $notes ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-notify"><?php esc_html_e( 'Send notifications', 'smooth-booking' ); ?></label></th>
                            <td>
                                <label for="smooth-booking-appointment-notify" class="smooth-booking-checkbox">
                                    <input type="checkbox" name="send_notifications" id="smooth-booking-appointment-notify" value="1" <?php checked( $should_notify ); ?> />
                                    <span><?php esc_html_e( 'Notify attendees about this appointment.', 'smooth-booking' ); ?></span>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save appointment', 'smooth-booking' ); ?></button>
                    <button type="button" class="button smooth-booking-form-dismiss" data-target="appointment-form"><?php esc_html_e( 'Cancel', 'smooth-booking' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render filter controls.
     *
     * @param array<string, string> $filters  Current filters.
     * @param Employee[]            $employees Employees list.
     * @param Service[]             $services  Services list.
     */
    private function render_filters( array $filters, array $employees, array $services ): void {
        $base = $this->get_base_page();
        ?>
        <form method="get" action="<?php echo esc_url( $base ); ?>" class="smooth-booking-appointment-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
            <div class="smooth-booking-filter-row">
                <label>
                    <span><?php esc_html_e( 'ID', 'smooth-booking' ); ?></span>
                    <input type="number" name="appointment_id" value="<?php echo esc_attr( $filters['appointment_id'] ?? '' ); ?>" min="1" class="small-text" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Appointment from', 'smooth-booking' ); ?></span>
                    <input type="date" name="appointment_from" value="<?php echo esc_attr( $filters['appointment_from'] ?? '' ); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Appointment to', 'smooth-booking' ); ?></span>
                    <input type="date" name="appointment_to" value="<?php echo esc_attr( $filters['appointment_to'] ?? '' ); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Created from', 'smooth-booking' ); ?></span>
                    <input type="date" name="created_from" value="<?php echo esc_attr( $filters['created_from'] ?? '' ); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Created to', 'smooth-booking' ); ?></span>
                    <input type="date" name="created_to" value="<?php echo esc_attr( $filters['created_to'] ?? '' ); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Customer search', 'smooth-booking' ); ?></span>
                    <input type="search" name="customer_search" value="<?php echo esc_attr( $filters['customer_search'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Name or email', 'smooth-booking' ); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Employee', 'smooth-booking' ); ?></span>
                    <select name="employee_id">
                        <option value=""><?php esc_html_e( 'All employees', 'smooth-booking' ); ?></option>
                        <?php foreach ( $employees as $employee ) : ?>
                            <?php if ( ! $employee instanceof Employee ) { continue; } ?>
                            <option value="<?php echo esc_attr( (string) $employee->get_id() ); ?>"<?php selected( (int) ( $filters['employee_id'] ?? 0 ), $employee->get_id() ); ?>><?php echo esc_html( $employee->get_name() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e( 'Service', 'smooth-booking' ); ?></span>
                    <select name="service_id">
                        <option value=""><?php esc_html_e( 'All services', 'smooth-booking' ); ?></option>
                        <?php foreach ( $services as $service ) : ?>
                            <?php if ( ! $service instanceof Service ) { continue; } ?>
                            <option value="<?php echo esc_attr( (string) $service->get_id() ); ?>"<?php selected( (int) ( $filters['service_id'] ?? 0 ), $service->get_id() ); ?>><?php echo esc_html( $service->get_name() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e( 'Status', 'smooth-booking' ); ?></span>
                    <select name="status">
                        <option value=""><?php esc_html_e( 'All statuses', 'smooth-booking' ); ?></option>
                        <option value="pending"<?php selected( 'pending', $filters['status'] ?? '' ); ?>><?php esc_html_e( 'Pending', 'smooth-booking' ); ?></option>
                        <option value="confirmed"<?php selected( 'confirmed', $filters['status'] ?? '' ); ?>><?php esc_html_e( 'Confirmed', 'smooth-booking' ); ?></option>
                        <option value="completed"<?php selected( 'completed', $filters['status'] ?? '' ); ?>><?php esc_html_e( 'Completed', 'smooth-booking' ); ?></option>
                        <option value="canceled"<?php selected( 'canceled', $filters['status'] ?? '' ); ?>><?php esc_html_e( 'Canceled', 'smooth-booking' ); ?></option>
                    </select>
                </label>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'smooth-booking' ); ?></button>
                <a class="button" href="<?php echo esc_url( $this->get_base_page() ); ?>"><?php esc_html_e( 'Reset', 'smooth-booking' ); ?></a>
            </p>
        </form>
        <?php
    }

    /**
     * Render appointments table.
     *
     * @param Appointment[] $appointments Appointments list.
     * @param array          $filters      Current filters.
     * @param array          $query_args   Query args (pagination).
     */
    private function render_table( array $appointments, array $filters, array $query_args, int $paged, int $per_page, int $total ): void {
        $query_filter_args = $this->get_filter_query_args( $filters );

        $columns = [
            'booking_id'      => __( 'ID', 'smooth-booking' ),
            'scheduled_start' => __( 'Appointment date', 'smooth-booking' ),
            'employee'        => __( 'Employee', 'smooth-booking' ),
            'customer_name'   => __( 'Customer name', 'smooth-booking' ),
            'customer'        => __( 'Customer', 'smooth-booking' ),
            'phone'           => __( 'Phone', 'smooth-booking' ),
            'email'           => __( 'Customer email', 'smooth-booking' ),
            'service'         => __( 'Service', 'smooth-booking' ),
            'duration'        => __( 'Duration', 'smooth-booking' ),
            'status'          => __( 'Status', 'smooth-booking' ),
            'payment'         => __( 'Payment', 'smooth-booking' ),
            'notes'           => __( 'Notes', 'smooth-booking' ),
            'created_at'      => __( 'Created datetime', 'smooth-booking' ),
        ];

        $total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
        ?>
        <div class="smooth-booking-appointments-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <?php foreach ( $columns as $key => $label ) : ?>
                            <?php if ( in_array( $key, [ 'booking_id', 'scheduled_start', 'created_at', 'status', 'payment' ], true ) ) : ?>
                                <?php $this->render_sortable_header( $label, $key, $query_args['orderby'], $query_args['order'], $query_filter_args ); ?>
                            <?php else : ?>
                                <th scope="col"><?php echo esc_html( $label ); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $appointments ) ) : ?>
                    <tr>
                        <td colspan="<?php echo esc_attr( (string) ( count( $columns ) + 1 ) ); ?>"><?php esc_html_e( 'No appointments found.', 'smooth-booking' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $appointments as $appointment ) : ?>
                        <?php if ( ! $appointment instanceof Appointment ) { continue; } ?>
                        <tr>
                            <td><?php echo esc_html( '#' . $appointment->get_id() ); ?></td>
                            <td><?php echo esc_html( $this->format_datetime( $appointment->get_scheduled_start() ) ); ?></td>
                            <td><?php echo esc_html( $appointment->get_employee_name() ?? '—' ); ?></td>
                            <td><?php echo esc_html( $this->get_customer_display_name( $appointment ) ); ?></td>
                            <td><?php echo esc_html( $appointment->get_customer_account_name() ?? '—' ); ?></td>
                            <td><?php echo esc_html( $appointment->get_customer_phone() ?? '—' ); ?></td>
                            <td><?php echo esc_html( $appointment->get_customer_email() ?? '—' ); ?></td>
                            <td><?php echo esc_html( $appointment->get_service_name() ?? '—' ); ?></td>
                            <td><?php echo esc_html( $this->format_duration( $appointment->get_duration_minutes() ) ); ?></td>
                            <td><?php echo esc_html( ucfirst( $appointment->get_status() ) ); ?></td>
                            <td><?php echo esc_html( $this->format_payment_status( $appointment->get_payment_status() ) ); ?></td>
                            <td><?php echo esc_html( $this->truncate_text( $appointment->get_internal_note() ?? $appointment->get_notes() ?? '' ) ); ?></td>
                            <td><?php echo esc_html( $this->format_datetime( $appointment->get_created_at() ) ); ?></td>
                            <td class="column-actions">
                                <div class="smooth-booking-actions-menu">
                                    <button type="button" class="button-link smooth-booking-actions-toggle" aria-expanded="false">
                                        <span class="dashicons dashicons-ellipsis"></span>
                                        <span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span>
                                    </button>
                                    <ul class="smooth-booking-actions-list" hidden>
                                        <li><a href="<?php echo esc_url( $this->get_edit_link( $appointment->get_id(), $filters ) ); ?>"><?php esc_html_e( 'Edit', 'smooth-booking' ); ?></a></li>
                                        <?php if ( $appointment->is_deleted() ) : ?>
                                            <li>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-restore-form">
                                                    <?php wp_nonce_field( 'smooth_booking_restore_appointment' ); ?>
                                                    <input type="hidden" name="action" value="smooth_booking_restore_appointment" />
                                                    <input type="hidden" name="appointment_id" value="<?php echo esc_attr( (string) $appointment->get_id() ); ?>" />
                                                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $this->get_current_url() ); ?>" />
                                                    <button type="submit" class="button-link"><?php esc_html_e( 'Restore', 'smooth-booking' ); ?></button>
                                                </form>
                                            </li>
                                        <?php else : ?>
                                            <li>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                    <?php wp_nonce_field( 'smooth_booking_delete_appointment' ); ?>
                                                    <input type="hidden" name="action" value="smooth_booking_delete_appointment" />
                                                    <input type="hidden" name="appointment_id" value="<?php echo esc_attr( (string) $appointment->get_id() ); ?>" />
                                                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $this->get_current_url() ); ?>" />
                                                    <button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'smooth-booking' ); ?></button>
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
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                [
                                    'base'      => esc_url_raw( add_query_arg( array_merge( $query_filter_args, [ 'paged' => '%#%' ] ), $this->get_base_page() ) ),
                                    'format'    => '',
                                    'current'   => max( 1, $paged ),
                                    'total'     => $total_pages,
                                    'add_args'  => false,
                                    'prev_text' => __( '&laquo; Previous', 'smooth-booking' ),
                                    'next_text' => __( 'Next &raquo;', 'smooth-booking' ),
                                ]
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Retrieve query args.
     *
     * @return array<string, mixed>
     */
    private function get_query_args(): array {
        $paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'scheduled_start';
        $order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( (string) $_GET['order'] ) ) : 'DESC';

        $allowed_orderby = [ 'booking_id', 'scheduled_start', 'created_at', 'status', 'payment' ];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'scheduled_start';
        }

        $allowed_order = [ 'ASC', 'DESC' ];
        $order         = strtoupper( $order );
        if ( ! in_array( $order, $allowed_order, true ) ) {
            $order = 'DESC';
        }

        return [
            'paged'   => max( 1, $paged ),
            'per_page'=> 20,
            'orderby' => 'payment' === $orderby ? 'payment_status' : $orderby,
            'order'   => $order,
        ];
    }

    /**
     * Collect filters from request.
     *
     * @return array<string, string>
     */
    private function get_filters(): array {
        $filters = [
            'appointment_id'        => isset( $_GET['appointment_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['appointment_id'] ) ) : '',
            'appointment_from'      => isset( $_GET['appointment_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['appointment_from'] ) ) : '',
            'appointment_to'        => isset( $_GET['appointment_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['appointment_to'] ) ) : '',
            'created_from'          => isset( $_GET['created_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['created_from'] ) ) : '',
            'created_to'            => isset( $_GET['created_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['created_to'] ) ) : '',
            'customer_search'       => isset( $_GET['customer_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['customer_search'] ) ) : '',
            'employee_id'           => isset( $_GET['employee_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['employee_id'] ) ) : '',
            'service_id'            => isset( $_GET['service_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['service_id'] ) ) : '',
            'status'                => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '',
        ];

        $filters['appointment_from_sql'] = $this->normalize_date_filter( $filters['appointment_from'], 'start' );
        $filters['appointment_to_sql']   = $this->normalize_date_filter( $filters['appointment_to'], 'end' );
        $filters['created_from_sql']     = $this->normalize_date_filter( $filters['created_from'], 'start' );
        $filters['created_to_sql']       = $this->normalize_date_filter( $filters['created_to'], 'end' );

        return $filters;
    }

    /**
     * Prepare filter query arguments excluding derived SQL values.
     *
     * @param array<string, string> $filters Filters.
     *
     * @return array<string, string>
     */
    private function get_filter_query_args( array $filters ): array {
        return [
            'appointment_id'  => $filters['appointment_id'],
            'appointment_from'=> $filters['appointment_from'],
            'appointment_to'  => $filters['appointment_to'],
            'created_from'    => $filters['created_from'],
            'created_to'      => $filters['created_to'],
            'customer_search' => $filters['customer_search'],
            'employee_id'     => $filters['employee_id'],
            'service_id'      => $filters['service_id'],
            'status'          => $filters['status'],
        ];
    }

    /**
     * Normalize date boundary filters.
     */
    private function normalize_date_filter( string $value, string $boundary ): string {
        if ( '' === $value ) {
            return '';
        }

        $timestamp = strtotime( $value );

        if ( false === $timestamp ) {
            return '';
        }

        if ( 'end' === $boundary ) {
            $timestamp = strtotime( '23:59:59', $timestamp );
        }

        if ( 'start' === $boundary ) {
            $timestamp = strtotime( '00:00:00', $timestamp );
        }

        return date( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Render sortable header.
     */
    private function render_sortable_header( string $label, string $key, string $current_orderby, string $current_order, array $filters ): void {
        $order = 'asc';
        if ( $current_orderby === $key || ( 'payment' === $key && 'payment_status' === $current_orderby ) ) {
            $order = 'asc' === strtolower( $current_order ) ? 'desc' : 'asc';
        }

        $args = array_merge( $filters, [
            'orderby' => $key,
            'order'   => strtoupper( $order ),
        ] );

        $url = add_query_arg( $args, $this->get_base_page() );

        $class = 'sortable';
        if ( $current_orderby === $key || ( 'payment' === $key && 'payment_status' === $current_orderby ) ) {
            $class .= ' sorted ' . ( 'asc' === strtolower( $current_order ) ? 'asc' : 'desc' );
        }

        printf(
            '<th scope="col" class="manage-column %1$s"><a href="%2$s"><span>%3$s</span><span class="sorting-indicator"></span></a></th>',
            esc_attr( $class ),
            esc_url( $url ),
            esc_html( $label )
        );
    }

    /**
     * Format datetime output.
     */
    private function format_datetime( DateTimeInterface $value ): string {
        $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        return wp_date( $format, $value->getTimestamp() );
    }

    /**
     * Format duration minutes into label.
     */
    private function format_duration( int $minutes ): string {
        if ( $minutes <= 0 ) {
            return '—';
        }

        $hours = (int) floor( $minutes / 60 );
        $mins  = $minutes % 60;

        if ( $hours > 0 && $mins > 0 ) {
            return sprintf( _n( '%d hour %d min', '%d hours %d min', $hours, 'smooth-booking' ), $hours, $mins );
        }

        if ( $hours > 0 ) {
            return sprintf( _n( '%d hour', '%d hours', $hours, 'smooth-booking' ), $hours );
        }

        return sprintf( _n( '%d minute', '%d minutes', $mins, 'smooth-booking' ), $mins );
    }

    /**
     * Format payment status.
     */
    private function format_payment_status( ?string $status ): string {
        if ( empty( $status ) ) {
            return __( '—', 'smooth-booking' );
        }

        $map = [
            'pending'    => __( 'Pending', 'smooth-booking' ),
            'authorized' => __( 'Authorized', 'smooth-booking' ),
            'paid'       => __( 'Paid', 'smooth-booking' ),
            'refunded'   => __( 'Refunded', 'smooth-booking' ),
            'failed'     => __( 'Failed', 'smooth-booking' ),
            'canceled'   => __( 'Canceled', 'smooth-booking' ),
        ];

        return $map[ $status ] ?? ucfirst( $status );
    }

    /**
     * Reduce long text output.
     */
    private function truncate_text( string $text, int $length = 40 ): string {
        if ( mb_strlen( $text ) <= $length ) {
            return $text;
        }

        return mb_substr( $text, 0, $length ) . '…';
    }

    /**
     * Display customer label.
     */
    private function get_customer_display_name( Appointment $appointment ): string {
        $first = $appointment->get_customer_first_name();
        $last  = $appointment->get_customer_last_name();

        if ( $first || $last ) {
            return trim( $first . ' ' . $last );
        }

        return $appointment->get_customer_account_name() ?? '—';
    }

    /**
     * Persist notice message.
     */
    private function add_notice( string $type, string $message ): void {
        $key = sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
        set_transient( $key, [ 'type' => $type, 'message' => $message ], MINUTE_IN_SECONDS );
    }

    /**
     * Consume stored notice.
     */
    private function consume_notice(): ?array {
        $key    = sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
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
     * Current page URL with query string.
     */
    private function get_current_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';

        return esc_url_raw( $scheme . $host . $uri );
    }

    /**
     * Generate edit link.
     */
    private function get_edit_link( int $appointment_id, array $filters = [] ): string {
        $args = array_merge( $filters, [
            'page'           => self::MENU_SLUG,
            'action'         => 'edit',
            'appointment_id' => $appointment_id,
        ] );

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Build available time slots.
     *
     * @return string[]
     */
    private function get_time_slots(): array {
        $slots = [];
        $start = new \DateTimeImmutable( 'today midnight' );

        for ( $i = 0; $i < 96; $i++ ) {
            $slots[] = $start->modify( sprintf( '+%d minutes', $i * 15 ) )->format( 'H:i' );
        }

        return $slots;
    }
}
