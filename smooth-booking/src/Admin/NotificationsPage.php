<?php
/**
 * Email notifications administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Notifications\EmailNotification;
use SmoothBooking\Domain\Notifications\EmailNotificationService;
use WP_Error;

use const MINUTE_IN_SECONDS;
use function __;
use function absint;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function get_locale;
use function get_transient;
use function is_wp_error;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function selected;
use function set_transient;
use function wp_die;
use function wp_editor;
use function wp_enqueue_editor;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function checked;

/**
 * Renders and handles the email notifications interface.
 */
class NotificationsPage {
    use AdminStylesTrait;

    public const CAPABILITY = 'manage_options';

    public const MENU_SLUG = 'smooth-booking-notifications';

    private const NOTICE_TRANSIENT_KEY = 'smooth_booking_notifications_notice';

    private EmailNotificationService $service;

    public function __construct( EmailNotificationService $service ) {
        $this->service = $service;
    }

    /**
     * Render the notifications admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage notifications.', 'smooth-booking' ) );
        }

        $notice = $this->consume_notice();

        $action          = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $notification_id = isset( $_GET['notification_id'] ) ? absint( $_GET['notification_id'] ) : 0;

        $editing_notification = null;
        $editing_error        = '';

        if ( 'edit' === $action && $notification_id > 0 ) {
            $notification = $this->service->get_notification( $notification_id, true );

            if ( is_wp_error( $notification ) ) {
                $editing_error = $notification->get_error_message();
            } else {
                $editing_notification = $notification;
            }
        }

        $notifications     = $this->service->list_notifications( true );
        $should_open_form  = $editing_notification instanceof EmailNotification || 'new' === $action;
        $form_container_id = 'smooth-booking-notification-form-panel';
        $open_label        = __( 'Add new notification', 'smooth-booking' );
        $close_label       = __( 'Close form', 'smooth-booking' );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-notifications-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Email notifications', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Create automated notifications for booking events, choose recipients, and design email templates.', 'smooth-booking' ); ?></p>
                    </div>
                    <div class="smooth-booking-admin-header__actions">
                        <button type="button" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-open-form" data-target="notification-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
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

                <?php if ( ! empty( $editing_error ) ) : ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html( $editing_error ); ?></p>
                    </div>
                <?php endif; ?>

                <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-notification-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="notification-form" data-focus-selector="#smooth-booking-notification-name">
                    <?php $this->render_notification_form( $editing_notification ); ?>
                </div>

                <h2><?php esc_html_e( 'Notification list', 'smooth-booking' ); ?></h2>

                <div class="smooth-booking-card smooth-booking-table-card">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Name', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Status', 'smooth-booking' ); ?></th>
                                <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $notifications ) ) : ?>
                            <tr>
                                <td colspan="3"><?php esc_html_e( 'No notifications have been created yet.', 'smooth-booking' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $notifications as $notification ) : ?>
                                <tr class="<?php echo $notification->is_deleted() ? 'smooth-booking-notification--deleted' : ''; ?>">
                                    <td>
                                        <strong><?php echo esc_html( $notification->get_name() ); ?></strong>
                                        <div class="description">
                                            <?php echo esc_html( $notification->get_trigger_event() ); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        if ( $notification->is_deleted() ) {
                                            esc_html_e( 'Deleted', 'smooth-booking' );
                                        } elseif ( $notification->is_enabled() ) {
                                            esc_html_e( 'Enabled', 'smooth-booking' );
                                        } else {
                                            esc_html_e( 'Disabled', 'smooth-booking' );
                                        }
                                        ?>
                                    </td>
                                    <td class="column-actions">
                                        <div class="smooth-booking-actions-menu">
                                            <button type="button" class="button-link smooth-booking-actions-toggle" aria-expanded="false">
                                                <span class="screen-reader-text"><?php esc_html_e( 'Toggle actions', 'smooth-booking' ); ?></span>
                                                <span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
                                            </button>
                                            <ul class="smooth-booking-actions-list" hidden>
                                                <?php if ( ! $notification->is_deleted() ) : ?>
                                                    <li>
                                                        <a href="<?php echo esc_url( $this->get_edit_link( $notification->get_id() ) ); ?>"><?php esc_html_e( 'Edit', 'smooth-booking' ); ?></a>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                        <?php wp_nonce_field( 'smooth_booking_delete_notification' ); ?>
                                                        <input type="hidden" name="action" value="smooth_booking_delete_notification" />
                                                        <input type="hidden" name="notification_id" value="<?php echo esc_attr( (string) $notification->get_id() ); ?>" />
                                                        <button type="submit" class="button-link delete-link">
                                                            <?php
                                                            if ( $notification->is_deleted() ) {
                                                                esc_html_e( 'Delete permanently', 'smooth-booking' );
                                                            } elseif ( $notification->is_enabled() ) {
                                                                esc_html_e( 'Disable notification', 'smooth-booking' );
                                                            } else {
                                                                esc_html_e( 'Delete', 'smooth-booking' );
                                                            }
                                                            ?>
                                                        </button>
                                                    </form>
                                                </li>
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
     * Handle create/update submissions.
     */
    public function handle_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage notifications.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_notification' );

        $notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

        $payload = [
            'name'               => $_POST['notification_name'] ?? '',
            'status'             => $_POST['notification_status'] ?? 'enabled',
            'type'               => $_POST['notification_type'] ?? 'booking.created',
            'appointment_status' => $_POST['notification_appointment_status'] ?? 'any',
            'service_scope'      => $_POST['notification_service_scope'] ?? 'any',
            'service_ids'        => isset( $_POST['notification_service_ids'] ) ? (array) $_POST['notification_service_ids'] : [],
            'recipients'         => isset( $_POST['notification_recipients'] ) ? (array) $_POST['notification_recipients'] : [],
            'custom_emails'      => $_POST['notification_custom_emails'] ?? '',
            'send_format'        => $_POST['notification_send_format'] ?? 'html',
            'subject'            => $_POST['notification_subject'] ?? '',
            'body_html'          => $_POST['notification_body_html'] ?? '',
            'body_text'          => $_POST['notification_body_text'] ?? '',
            'attach_ics'         => isset( $_POST['notification_attach_ics'] ) ? $_POST['notification_attach_ics'] : false,
            'locale'             => $_POST['notification_locale'] ?? get_locale(),
            'location_id'        => $_POST['notification_location_id'] ?? 0,
        ];

        if ( $notification_id > 0 ) {
            $result = $this->service->update_notification( $notification_id, $payload );
        } else {
            $result = $this->service->create_notification( $payload );
        }

        $redirect = $this->get_base_page();

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );

            if ( $notification_id > 0 ) {
                $redirect = $this->get_edit_link( $notification_id );
            } else {
                $redirect = $this->get_new_link();
            }
        } else {
            $message = $notification_id > 0
                ? __( 'Notification updated.', 'smooth-booking' )
                : __( 'Notification created.', 'smooth-booking' );
            $this->add_notice( 'success', $message );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle delete/disable submissions.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage notifications.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_notification' );

        $notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

        if ( $notification_id <= 0 ) {
            $this->add_notice( 'error', __( 'Invalid notification.', 'smooth-booking' ) );
            wp_safe_redirect( $this->get_base_page() );
            exit;
        }

        $notification = $this->service->get_notification( $notification_id, true );

        if ( is_wp_error( $notification ) ) {
            $this->add_notice( 'error', $notification->get_error_message() );
            wp_safe_redirect( $this->get_base_page() );
            exit;
        }

        if ( $notification->is_deleted() ) {
            $result = $this->service->force_delete_notification( $notification_id );
            $message = __( 'Notification deleted permanently.', 'smooth-booking' );
        } elseif ( $notification->is_enabled() ) {
            $result  = $this->service->disable_notification( $notification_id );
            $message = __( 'Notification disabled. Run delete again to remove it.', 'smooth-booking' );
        } else {
            $result  = $this->service->soft_delete_notification( $notification_id );
            $message = __( 'Notification moved to trash. Run delete again to remove it permanently.', 'smooth-booking' );
        }

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
        } else {
            $this->add_notice( 'success', $message );
        }

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        $this->enqueue_admin_styles();

        wp_enqueue_editor();

        wp_enqueue_style(
            'smooth-booking-admin-notifications',
            plugins_url( 'assets/css/admin-notifications.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'select2' );

        wp_enqueue_script(
            'smooth-booking-admin-notifications',
            plugins_url( 'assets/js/admin-notifications.js', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'jquery', 'select2' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'smooth-booking-admin-notifications',
            'SmoothBookingNotifications',
            [
                'showCodes' => __( 'Show available placeholders', 'smooth-booking' ),
                'hideCodes' => __( 'Hide available placeholders', 'smooth-booking' ),
                'editorId'  => 'smooth-booking-notification-body-html',
                'editorSettings' => [
                    'mediaButtons' => false,
                    'quicktags'    => true,
                    'tinymce'      => [
                        'wpautop' => true,
                        'height'  => 240,
                    ],
                ],
            ]
        );
    }

    private function render_notification_form( ?EmailNotification $notification ): void {
        $is_edit = $notification instanceof EmailNotification;

        $name               = $is_edit ? $notification->get_name() : '';
        $status             = $is_edit ? ( $notification->is_enabled() ? 'enabled' : 'disabled' ) : 'enabled';
        $type               = $is_edit ? $notification->get_trigger_event() : 'booking.created';
        $appointment_status = $is_edit ? $notification->get_appointment_status() : 'any';
        $service_scope      = $is_edit ? $notification->get_service_scope() : 'any';
        $service_ids        = $is_edit ? $notification->get_service_ids() : [];
        $recipients         = $is_edit ? $notification->get_recipients() : [ 'client' ];
        $custom_emails      = $is_edit ? implode( "\n", $notification->get_custom_emails() ) : '';
        $send_format        = $is_edit ? $notification->get_send_format() : 'html';
        $subject            = $is_edit ? $notification->get_subject() : '';
        $body_html          = $is_edit ? $notification->get_body_html() : '';
        $body_text          = $is_edit ? $notification->get_body_text() : '';
        $attach_ics         = $is_edit ? $notification->should_attach_ics() : false;
        $locale             = $is_edit ? $notification->get_locale() : get_locale();
        $location_id        = $is_edit ? ( $notification->get_location_id() ?? 0 ) : 0;

        $types             = $this->service->get_notification_types();
        $status_options    = $this->service->get_appointment_status_options();
        $recipient_options = $this->service->get_recipient_options();
        $service_options   = $this->service->get_service_choices();
        $format_options    = $this->service->get_send_format_options();
        $scope_options     = $this->service->get_service_scope_options();

        ?>
        <div class="smooth-booking-card smooth-booking-notification-form-card">
            <div class="smooth-booking-form-header">
                <h2><?php echo $is_edit ? esc_html__( 'Edit notification', 'smooth-booking' ) : esc_html__( 'Add new notification', 'smooth-booking' ); ?></h2>
                <?php if ( $is_edit ) : ?>
                    <div class="smooth-booking-form-header__actions">
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-cancel"><?php esc_html_e( 'Back to list', 'smooth-booking' ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-notification-form">
                <?php wp_nonce_field( 'smooth_booking_save_notification' ); ?>
                <input type="hidden" name="action" value="smooth_booking_save_notification" />
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="notification_id" value="<?php echo esc_attr( (string) $notification->get_id() ); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-name"><?php esc_html_e( 'Name', 'smooth-booking' ); ?><span class="required">*</span></label></th>
                            <td>
                                <input type="text" id="smooth-booking-notification-name" name="notification_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" required />
                                <p class="description"><?php esc_html_e( 'Enter notification name which will be displayed in the list.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Status', 'smooth-booking' ); ?></th>
                            <td>
                                <label><input type="radio" name="notification_status" value="enabled" <?php checked( 'enabled', $status ); ?> /> <?php esc_html_e( 'Enable', 'smooth-booking' ); ?></label>
                                <label class="smooth-booking-radio-inline"><input type="radio" name="notification_status" value="disabled" <?php checked( 'disabled', $status ); ?> /> <?php esc_html_e( 'Disabled', 'smooth-booking' ); ?></label>
                                <p class="description"><?php esc_html_e( 'Choose whether notification is enabled and sending messages or it is disabled and no messages are sent until you activate the notification.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-type"><?php esc_html_e( 'Type', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-notification-type" name="notification_type" class="regular-text">
                                    <?php foreach ( $types as $value => $info ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $info['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select the type of event at which the notification is sent.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-appointment-status"><?php esc_html_e( 'Appointment status', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-notification-appointment-status" name="notification_appointment_status" class="regular-text">
                                    <?php foreach ( $status_options as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appointment_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select what status an appointment should have for the notification to be sent.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Services', 'smooth-booking' ); ?></th>
                            <td>
                                <label for="smooth-booking-notification-service-scope" class="screen-reader-text"><?php esc_html_e( 'Service scope', 'smooth-booking' ); ?></label>
                                <select id="smooth-booking-notification-service-scope" name="notification_service_scope" class="regular-text smooth-booking-notification-service-scope">
                                    <?php foreach ( $scope_options as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $service_scope, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="smooth-booking-notification-services-select<?php echo 'selected' === $service_scope ? ' is-visible' : ''; ?>">
                                    <select name="notification_service_ids[]" id="smooth-booking-notification-service-ids" class="smooth-booking-select2" multiple data-placeholder="<?php esc_attr_e( 'Select services', 'smooth-booking' ); ?>">
                                        <?php foreach ( $service_options as $option ) : ?>
                                            <option value="<?php echo esc_attr( (string) $option['value'] ); ?>" <?php echo in_array( (int) $option['value'], $service_ids, true ) ? 'selected' : ''; ?>><?php echo esc_html( $option['label'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="description"><?php esc_html_e( 'Choose whether notification should be sent for specific services only or not.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Recipients', 'smooth-booking' ); ?></th>
                            <td>
                                <?php foreach ( $recipient_options as $value => $label ) : ?>
                                    <label class="smooth-booking-checkbox-inline">
                                        <input type="checkbox" name="notification_recipients[]" value="<?php echo esc_attr( $value ); ?>" <?php echo in_array( $value, $recipients, true ) ? 'checked' : ''; ?> />
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e( 'Choose who will receive this notification.', 'smooth-booking' ); ?></p>
                                <div class="smooth-booking-notification-custom-emails<?php echo in_array( 'custom', $recipients, true ) ? ' is-visible' : ''; ?>">
                                    <label for="smooth-booking-notification-custom-emails"><?php esc_html_e( 'Custom email addresses', 'smooth-booking' ); ?></label>
                                    <textarea id="smooth-booking-notification-custom-emails" name="notification_custom_emails" rows="4" class="large-text"><?php echo esc_textarea( $custom_emails ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'You can enter multiple email addresses (one per line).', 'smooth-booking' ); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Send emails as', 'smooth-booking' ); ?></th>
                            <td>
                                <?php foreach ( $format_options as $value => $label ) : ?>
                                    <label class="smooth-booking-radio-inline">
                                        <input type="radio" name="notification_send_format" value="<?php echo esc_attr( $value ); ?>" <?php checked( $send_format, $value ); ?> />
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e( 'HTML allows formatting, colors, fonts, positioning, etc. With Text you must use Text mode of rich-text editors below. On some servers only text emails are sent successfully.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-subject"><?php esc_html_e( 'Subject', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="smooth-booking-notification-subject" name="notification_subject" class="regular-text" value="<?php echo esc_attr( $subject ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Body', 'smooth-booking' ); ?></th>
                            <td>
                                <?php
                                wp_editor(
                                    $body_html,
                                    'smooth-booking-notification-body-html',
                                    [
                                        'textarea_name' => 'notification_body_html',
                                        'editor_height' => 240,
                                        'media_buttons' => false,
                                    ]
                                );
                                ?>
                                <textarea name="notification_body_text" id="smooth-booking-notification-body-text" class="smooth-booking-notification-body-text" rows="5" placeholder="<?php esc_attr_e( 'Plain text version...', 'smooth-booking' ); ?>"><?php echo esc_textarea( $body_text ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Use the Visual tab for HTML emails or the Text area for plain text messages.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Attach ICS file', 'smooth-booking' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="notification_attach_ics" value="1" <?php checked( $attach_ics ); ?> />
                                    <?php esc_html_e( 'Attach calendar invite (ICS file).', 'smooth-booking' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Codes', 'smooth-booking' ); ?></th>
                            <td>
                                <button type="button" class="button-link smooth-booking-toggle-codes" data-target="smooth-booking-notification-codes">
                                    <?php esc_html_e( 'Show available placeholders', 'smooth-booking' ); ?>
                                </button>
                                <div id="smooth-booking-notification-codes" class="smooth-booking-notification-codes" hidden>
                                    <?php $this->render_placeholder_table(); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-locale"><?php esc_html_e( 'Locale', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="smooth-booking-notification-locale" name="notification_locale" value="<?php echo esc_attr( $locale ); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-notification-location-id"><?php esc_html_e( 'Location ID', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="number" id="smooth-booking-notification-location-id" name="notification_location_id" value="<?php echo esc_attr( (string) $location_id ); ?>" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Optional: limit notification to a specific location (enter the location ID). Leave blank to apply to all locations.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="smooth-booking-form-actions">
                    <button type="submit" class="sba-btn sba-btn--primary sba-btn__large"><?php esc_html_e( 'Save notification', 'smooth-booking' ); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_placeholder_table(): void {
        $placeholders = $this->get_placeholder_definitions();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Code', 'smooth-booking' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Description', 'smooth-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $placeholders as $code => $description ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $code ); ?></code></td>
                    <td><?php echo esc_html( $description ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Retrieve placeholder definitions.
     *
     * @return array<string, string>
     */
    private function get_placeholder_definitions(): array {
        return [
            '{appointment_date}'               => __( 'Date of appointment', 'smooth-booking' ),
            '{appointment_end_date}'           => __( 'End date of appointment', 'smooth-booking' ),
            '{appointment_end_time}'           => __( 'End time of appointment', 'smooth-booking' ),
            '{appointment_id}'                 => __( 'Appointment ID', 'smooth-booking' ),
            '{appointment_notes}'              => __( 'Customer notes for appointment', 'smooth-booking' ),
            '{appointment_time}'               => __( 'Time of appointment', 'smooth-booking' ),
            '{approve_appointment_url}'        => __( 'URL of approve appointment link (use inside anchor tag)', 'smooth-booking' ),
            '{booking_number}'                 => __( 'Booking number', 'smooth-booking' ),
            '{cancel_appointment}'             => __( 'Cancel appointment link', 'smooth-booking' ),
            '{cancel_appointment_confirm_url}' => __( 'URL of cancel appointment link with confirmation (use inside anchor tag)', 'smooth-booking' ),
            '{cancel_appointment_url}'         => __( 'URL of cancel appointment link (use inside anchor tag)', 'smooth-booking' ),
            '{cancellation_reason}'            => __( 'Reason mentioned while cancelling appointment', 'smooth-booking' ),
            '{cancellation_time_limit}'        => __( 'Time limit to which appointments can be cancelled', 'smooth-booking' ),
            '{category_image}'                 => __( 'Image of service category', 'smooth-booking' ),
            '{category_info}'                  => __( 'Info of category', 'smooth-booking' ),
            '{category_name}'                  => __( 'Name of category', 'smooth-booking' ),
            '{client_address}'                 => __( 'Address of client', 'smooth-booking' ),
            '{client_birthday}'                => __( 'Client birthday', 'smooth-booking' ),
            '{client_email}'                   => __( 'Email of client', 'smooth-booking' ),
            '{client_first_name}'              => __( 'First name of client', 'smooth-booking' ),
            '{client_full_birthday}'           => __( 'Client birthday full date', 'smooth-booking' ),
            '{client_last_name}'               => __( 'Last name of client', 'smooth-booking' ),
            '{client_locale}'                  => __( 'Locale of client', 'smooth-booking' ),
            '{client_name}'                    => __( 'Full name of client', 'smooth-booking' ),
            '{client_note}'                    => __( 'Note of client', 'smooth-booking' ),
            '{client_phone}'                   => __( 'Phone of client', 'smooth-booking' ),
            '{client_timezone}'                => __( 'Time zone of client', 'smooth-booking' ),
            '{company_address}'                => __( 'Address of company', 'smooth-booking' ),
            '{company_logo}'                   => __( 'Company logo', 'smooth-booking' ),
            '{company_name}'                   => __( 'Name of company', 'smooth-booking' ),
            '{company_phone}'                  => __( 'Company phone', 'smooth-booking' ),
            '{company_website}'                => __( 'Company website address', 'smooth-booking' ),
            '{gift_card}'                      => __( 'Gift card code', 'smooth-booking' ),
            '{google_calendar_url}'            => __( 'URL for adding event to Google Calendar (use inside anchor tag)', 'smooth-booking' ),
            '{internal_note}'                  => __( 'Internal note', 'smooth-booking' ),
            '{online_meeting_join_url}'        => __( 'Online meeting join URL', 'smooth-booking' ),
            '{online_meeting_password}'        => __( 'Online meeting password', 'smooth-booking' ),
            '{online_meeting_start_url}'       => __( 'Online meeting start URL', 'smooth-booking' ),
            '{online_meeting_url}'             => __( 'Online meeting URL', 'smooth-booking' ),
            '{payment_status}'                 => __( 'Payment status', 'smooth-booking' ),
            '{payment_type}'                   => __( 'Payment type', 'smooth-booking' ),
            '{reject_appointment_url}'         => __( 'URL of reject appointment link (use inside anchor tag)', 'smooth-booking' ),
            '{service_duration}'               => __( 'Duration of service', 'smooth-booking' ),
            '{service_image}'                  => __( 'Image of service', 'smooth-booking' ),
            '{service_info}'                   => __( 'Info of service', 'smooth-booking' ),
            '{service_name}'                   => __( 'Name of service', 'smooth-booking' ),
            '{service_price}'                  => __( 'Price of service', 'smooth-booking' ),
            '{staff_category_image}'           => __( 'Image of staff category', 'smooth-booking' ),
            '{staff_category_info}'            => __( 'Info of staff category', 'smooth-booking' ),
            '{staff_category_name}'            => __( 'Name of staff category', 'smooth-booking' ),
            '{staff_email}'                    => __( 'Email of staff', 'smooth-booking' ),
            '{staff_info}'                     => __( 'Info of staff', 'smooth-booking' ),
            '{staff_name}'                     => __( 'Name of staff', 'smooth-booking' ),
            '{staff_phone}'                    => __( 'Phone of staff', 'smooth-booking' ),
            '{staff_photo}'                    => __( 'Photo of staff', 'smooth-booking' ),
            '{staff_timezone}'                 => __( 'Time zone of staff', 'smooth-booking' ),
            '{total_duration}'                 => __( 'Duration of appointment', 'smooth-booking' ),
            '{total_price}'                    => __( 'Total price of booking (sum of all cart items after applying coupon)', 'smooth-booking' ),
        ];
    }

    private function get_base_page(): string {
        return admin_url( 'admin.php?page=' . self::MENU_SLUG );
    }

    private function get_new_link(): string {
        return add_query_arg( [ 'action' => 'new' ], $this->get_base_page() );
    }

    private function get_edit_link( int $notification_id ): string {
        return add_query_arg(
            [
                'action'           => 'edit',
                'notification_id'  => $notification_id,
            ],
            $this->get_base_page()
        );
    }

    private function add_notice( string $type, string $message ): void {
        set_transient(
            self::NOTICE_TRANSIENT_KEY,
            [
                'type'    => $type,
                'message' => $message,
            ],
            MINUTE_IN_SECONDS
        );
    }

    private function consume_notice(): ?array {
        $notice = get_transient( self::NOTICE_TRANSIENT_KEY );

        if ( false !== $notice ) {
            delete_transient( self::NOTICE_TRANSIENT_KEY );

            return $notice;
        }

        return null;
    }
}
