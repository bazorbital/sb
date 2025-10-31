<?php
/**
 * Shared renderer for appointment forms.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Appointments\Appointment;
use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Employees\Employee;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;

use function __;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_url;
use function selected;
use function wp_nonce_field;
use function admin_url;

/**
 * Provides a reusable appointment form renderer for admin screens.
 */
trait AppointmentFormRendererTrait {
    /**
     * General settings accessor.
     */
    protected GeneralSettings $general_settings;

    /**
     * Render appointment form markup.
     *
     * @param Appointment|null $appointment Current appointment instance.
     * @param Employee[]       $employees   Available employees.
     * @param Service[]        $services    Available services.
     * @param Customer[]       $customers   Available customers.
     */
    protected function render_appointment_form( ?Appointment $appointment, array $employees, array $services, array $customers ): void {
        $is_edit         = $appointment instanceof Appointment;
        $action          = $is_edit ? 'edit' : 'create';
        $date            = $is_edit ? $appointment->get_scheduled_start()->format( 'Y-m-d' ) : '';
        $start           = $is_edit ? $appointment->get_scheduled_start()->format( 'H:i' ) : '';
        $end             = $is_edit ? $appointment->get_scheduled_end()->format( 'H:i' ) : '';
        $status          = $is_edit ? $appointment->get_status() : 'pending';
        $payment         = $is_edit ? ( $appointment->get_payment_status() ?? '' ) : '';
        $notes           = $is_edit ? ( $appointment->get_notes() ?? '' ) : '';
        $internal        = $is_edit ? ( $appointment->get_internal_note() ?? '' ) : '';
        $customer_email  = $is_edit ? ( $appointment->get_customer_email() ?? '' ) : '';
        $customer_phone  = $is_edit ? ( $appointment->get_customer_phone() ?? '' ) : '';
        $selected_employee = $is_edit ? $appointment->get_employee_id() : 0;
        $selected_service  = $is_edit ? $appointment->get_service_id() : 0;
        $selected_customer = $is_edit ? ( $appointment->get_customer_id() ?? 0 ) : 0;
        $should_notify     = $is_edit ? $appointment->should_notify() : false;

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
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $this->get_form_referer() ); ?>" />
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
                            <th scope="row"><label for="smooth-booking-appointment-customer"><?php esc_html_e( 'Customer', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="customer_id" id="smooth-booking-appointment-customer" class="smooth-booking-select2" data-placeholder="<?php esc_attr_e( 'Select customer', 'smooth-booking' ); ?>">
                                    <option value=""><?php esc_html_e( 'Select customer', 'smooth-booking' ); ?></option>
                                    <?php foreach ( $customers as $customer ) : ?>
                                        <?php if ( ! $customer instanceof Customer ) { continue; } ?>
                                        <option value="<?php echo esc_attr( (string) $customer->get_id() ); ?>"<?php selected( $selected_customer, $customer->get_id() ); ?>><?php echo esc_html( $customer->get_display_name() ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-status"><?php esc_html_e( 'Status', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="status" id="smooth-booking-appointment-status" class="regular-text">
                                    <?php foreach ( $statuses as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-appointment-payment"><?php esc_html_e( 'Payment status', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select name="payment_status" id="smooth-booking-appointment-payment" class="regular-text">
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
     * Retrieve available time slots based on general settings.
     *
     * @return string[]
     */
    protected function get_time_slots(): array {
        return $this->general_settings->get_time_slots();
    }

    /**
     * Determine referer URL for appointment forms.
     */
    protected function get_form_referer(): string {
        if ( method_exists( $this, 'get_current_url' ) ) {
            return (string) $this->get_current_url();
        }

        return '';
    }
}
