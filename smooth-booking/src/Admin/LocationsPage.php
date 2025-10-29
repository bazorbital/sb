<?php
/**
 * Locations administration screen.
 *
 * @package SmoothBooking\Admin
 */

namespace SmoothBooking\Admin;

use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
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
use function esc_url;
use function get_current_user_id;
use function get_option;
use function get_transient;
use function is_wp_error;
use function mysql2date;
use function plugins_url;
use function sanitize_key;
use function selected;
use function checked;
use function wp_timezone_choice;
use function set_transient;
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
 * Renders and handles the locations management interface.
 */
class LocationsPage {
    use AdminStylesTrait;

    public const CAPABILITY = 'manage_options';

    public const MENU_SLUG = 'smooth-booking-locations';

    private const NOTICE_TRANSIENT_TEMPLATE = 'smooth_booking_location_notice_%d';

    private LocationService $service;

    public function __construct( LocationService $service ) {
        $this->service = $service;
    }

    /**
     * Render the locations admin page.
     */
    public function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage locations.', 'smooth-booking' ) );
        }

        $notice = $this->consume_notice();

        $action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
        $location_id = isset( $_GET['location_id'] ) ? absint( $_GET['location_id'] ) : 0;
        $view        = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : 'active';
        $show_deleted = 'deleted' === $view;

        $editing_location = null;
        $editing_error    = null;

        if ( 'edit' === $action && $location_id > 0 ) {
            $location = $this->service->get_location_with_deleted( $location_id );

            if ( is_wp_error( $location ) ) {
                $editing_error = $location->get_error_message();
            } elseif ( $location->is_deleted() ) {
                $editing_error = esc_html__( 'Restore the location before editing.', 'smooth-booking' );
            } else {
                $editing_location = $location;
            }
        }

        $locations = $this->service->list_locations(
            [
                'include_deleted' => $show_deleted,
                'only_deleted'    => $show_deleted,
            ]
        );

        $should_open_form  = $editing_location instanceof Location;
        $form_container_id = 'smooth-booking-location-form-panel';
        $open_label        = __( 'Add new location', 'smooth-booking' );
        $close_label       = __( 'Close form', 'smooth-booking' );

        ?>
        <div class="wrap smooth-booking-admin smooth-booking-locations-wrap">
            <div class="smooth-booking-admin__content">
                <div class="smooth-booking-admin-header">
                    <div class="smooth-booking-admin-header__content">
                        <h1><?php echo esc_html__( 'Locations', 'smooth-booking' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Create dedicated locations to power business hours, holidays, and booking visibility.', 'smooth-booking' ); ?></p>
                    </div>
                    <div class="smooth-booking-admin-header__actions">
                        <button type="button" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-open-form" data-target="location-form" data-open-label="<?php echo esc_attr( $open_label ); ?>" data-close-label="<?php echo esc_attr( $close_label ); ?>" aria-expanded="<?php echo $should_open_form ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $form_container_id ); ?>">
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

                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Business hours defined here will populate calendar visibility for all staff if you enable “Show only business hours in the calendar” under Settings → Calendar.', 'smooth-booking' ); ?></p>
                </div>

                <div id="<?php echo esc_attr( $form_container_id ); ?>" class="smooth-booking-form-drawer smooth-booking-location-form-drawer<?php echo $should_open_form ? ' is-open' : ''; ?>" data-context="location-form" data-focus-selector="#smooth-booking-location-name">
                    <?php $this->render_location_form( $editing_location ); ?>
                </div>

                <h2><?php echo esc_html__( 'Location list', 'smooth-booking' ); ?></h2>

                <div class="smooth-booking-toolbar">
                    <?php if ( $show_deleted ) : ?>
                        <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( $this->get_view_link( 'active' ) ); ?>">
                            <?php esc_html_e( 'Back to active locations', 'smooth-booking' ); ?>
                        </a>
                    <?php else : ?>
                        <a class="sba-btn sba-btn__medium sba-btn__filled-light" href="<?php echo esc_url( $this->get_view_link( 'deleted' ) ); ?>">
                            <?php esc_html_e( 'Show deleted locations', 'smooth-booking' ); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ( $show_deleted ) : ?>
                    <div class="notice notice-info">
                        <p><?php esc_html_e( 'Deleted locations can be restored from this view.', 'smooth-booking' ); ?></p>
                    </div>
                <?php endif; ?>

                <div class="smooth-booking-location-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Name', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Address', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Base email', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Time zone', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Website', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Industry', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Event location', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Company details', 'smooth-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Last updated', 'smooth-booking' ); ?></th>
                                <th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smooth-booking' ); ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $locations ) ) : ?>
                            <tr>
                                <td colspan="11"><?php esc_html_e( 'No locations have been added yet.', 'smooth-booking' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $locations as $location ) : ?>
                                <tr>
                                    <td>
                                        <div class="smooth-booking-location-name-cell">
                                            <?php echo $this->get_location_avatar_html( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="smooth-booking-location-name-text"><?php echo esc_html( $location->get_name() ); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $location->get_address() ? esc_html( $location->get_address() ) : esc_html( '—' ); ?></td>
                                    <td><?php echo $location->get_phone() ? esc_html( $location->get_phone() ) : esc_html( '—' ); ?></td>
                                    <td><?php echo $location->get_base_email() ? esc_html( $location->get_base_email() ) : esc_html( '—' ); ?></td>
                                    <td><?php echo esc_html( $location->get_timezone() ); ?></td>
                                    <td>
                                        <?php if ( $location->get_website() ) : ?>
                                            <a href="<?php echo esc_url( $location->get_website() ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( $location->get_website() ); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo esc_html( '—' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $this->service->get_industry_label( $location->get_industry_id() ) ); ?></td>
                                    <td><?php echo $location->is_event_location() ? esc_html__( 'Yes', 'smooth-booking' ) : esc_html__( 'No', 'smooth-booking' ); ?></td>
                                    <td>
                                        <?php if ( $location->get_company_name() ) : ?>
                                            <strong><?php echo esc_html( $location->get_company_name() ); ?></strong><br />
                                        <?php endif; ?>
                                        <?php if ( $location->get_company_phone() ) : ?>
                                            <span><?php echo esc_html( $location->get_company_phone() ); ?></span><br />
                                        <?php endif; ?>
                                        <?php if ( $location->get_company_address() ) : ?>
                                            <span><?php echo esc_html( $location->get_company_address() ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( ! $location->get_company_name() && ! $location->get_company_phone() && ! $location->get_company_address() ) : ?>
                                            <?php echo esc_html( '—' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $updated_at = $location->get_updated_at();
                                        if ( $updated_at ) {
                                            $format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
                                            echo esc_html( mysql2date( $format, $updated_at ) );
                                        } else {
                                            echo esc_html( '—' );
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
                                                <?php if ( ! $location->is_deleted() ) : ?>
                                                    <li>
                                                        <a href="<?php echo esc_url( $this->get_edit_link( $location->get_id() ) ); ?>">
                                                            <?php esc_html_e( 'Edit', 'smooth-booking' ); ?>
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-delete-form">
                                                            <?php wp_nonce_field( 'smooth_booking_delete_location' ); ?>
                                                            <input type="hidden" name="action" value="smooth_booking_delete_location" />
                                                            <input type="hidden" name="location_id" value="<?php echo esc_attr( $location->get_id() ); ?>" />
                                                            <button type="submit" class="button-link delete-link"><?php esc_html_e( 'Delete', 'smooth-booking' ); ?></button>
                                                        </form>
                                                    </li>
                                                <?php else : ?>
                                                    <li>
                                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                            <?php wp_nonce_field( 'smooth_booking_restore_location' ); ?>
                                                            <input type="hidden" name="action" value="smooth_booking_restore_location" />
                                                            <input type="hidden" name="location_id" value="<?php echo esc_attr( $location->get_id() ); ?>" />
                                                            <button type="submit" class="button-link"><?php esc_html_e( 'Restore', 'smooth-booking' ); ?></button>
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
     * Handle create/update submissions.
     */
    public function handle_save(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage locations.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_save_location' );

        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;

        $payload = [
            'name'             => $_POST['location_name'] ?? '',
            'profile_image_id' => $_POST['location_profile_image_id'] ?? 0,
            'address'          => $_POST['location_address'] ?? '',
            'phone'            => $_POST['location_phone'] ?? '',
            'base_email'       => $_POST['location_email'] ?? '',
            'website'          => $_POST['location_website'] ?? '',
            'timezone'         => $_POST['location_timezone'] ?? '',
            'industry_id'      => $_POST['location_industry'] ?? 0,
            'is_event_location'=> isset( $_POST['location_is_event'] ) ? $_POST['location_is_event'] : false,
            'company_name'     => $_POST['location_company_name'] ?? '',
            'company_address'  => $_POST['location_company_address'] ?? '',
            'company_phone'    => $_POST['location_company_phone'] ?? '',
        ];

        $redirect = $this->get_base_page();

        if ( $location_id > 0 ) {
            $result = $this->service->update_location( $location_id, $payload );
        } else {
            $result = $this->service->create_location( $payload );
        }

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );

            if ( $location_id > 0 ) {
                $redirect = $this->get_edit_link( $location_id );
            }

            wp_safe_redirect( $redirect );
            exit;
        }

        $message = $location_id > 0
            ? __( 'Location updated.', 'smooth-booking' )
            : __( 'Location created.', 'smooth-booking' );

        $this->add_notice( 'success', $message );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle soft delete requests.
     */
    public function handle_delete(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage locations.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_delete_location' );

        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;

        if ( $location_id <= 0 ) {
            $this->add_notice( 'error', __( 'Invalid location.', 'smooth-booking' ) );
            wp_safe_redirect( $this->get_base_page() );
            exit;
        }

        $result = $this->service->delete_location( $location_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
        } else {
            $this->add_notice( 'success', __( 'Location deleted.', 'smooth-booking' ) );
        }

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Handle restore requests.
     */
    public function handle_restore(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage locations.', 'smooth-booking' ) );
        }

        check_admin_referer( 'smooth_booking_restore_location' );

        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;

        if ( $location_id <= 0 ) {
            $this->add_notice( 'error', __( 'Invalid location.', 'smooth-booking' ) );
            wp_safe_redirect( $this->get_view_link( 'deleted' ) );
            exit;
        }

        $result = $this->service->restore_location( $location_id );

        if ( is_wp_error( $result ) ) {
            $this->add_notice( 'error', $result->get_error_message() );
        } else {
            $this->add_notice( 'success', __( 'Location restored.', 'smooth-booking' ) );
        }

        wp_safe_redirect( $this->get_base_page() );
        exit;
    }

    /**
     * Enqueue admin assets for the locations screen.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'smooth-booking_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_media();

        $this->enqueue_admin_styles();

        wp_enqueue_style(
            'smooth-booking-admin-locations',
            plugins_url( 'assets/css/admin-locations.css', SMOOTH_BOOKING_PLUGIN_FILE ),
            [ 'smooth-booking-admin-shared' ],
            SMOOTH_BOOKING_VERSION
        );

        wp_enqueue_script(
            'smooth-booking-admin-locations',
            SMOOTH_BOOKING_PLUGIN_URL . 'assets/js/admin-locations.js',
            [ 'jquery' ],
            SMOOTH_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'smooth-booking-admin-locations',
            'SmoothBookingLocations',
            [
                'confirmDelete'   => __( 'Are you sure you want to delete this location?', 'smooth-booking' ),
                'chooseImage'     => __( 'Select location image', 'smooth-booking' ),
                'useImage'        => __( 'Use image', 'smooth-booking' ),
                'removeImage'     => __( 'Remove image', 'smooth-booking' ),
                'placeholderHtml' => $this->get_location_avatar_html( null ),
            ]
        );
    }

    /**
     * Render the location form.
     */
    private function render_location_form( ?Location $location ): void {
        $is_edit = null !== $location;

        $name             = $is_edit ? $location->get_name() : '';
        $profile_image_id = $is_edit ? ( $location->get_profile_image_id() ?? 0 ) : 0;
        $address          = $is_edit ? ( $location->get_address() ?? '' ) : '';
        $phone            = $is_edit ? ( $location->get_phone() ?? '' ) : '';
        $base_email       = $is_edit ? ( $location->get_base_email() ?? '' ) : '';
        $website          = $is_edit ? ( $location->get_website() ?? '' ) : '';
        $timezone         = $is_edit ? $location->get_timezone() : ( get_option( 'timezone_string' ) ?: 'Europe/Budapest' );
        $industry_id      = $is_edit ? $location->get_industry_id() : 0;
        $is_event         = $is_edit ? $location->is_event_location() : false;
        $company_name     = $is_edit ? ( $location->get_company_name() ?? '' ) : '';
        $company_address  = $is_edit ? ( $location->get_company_address() ?? '' ) : '';
        $company_phone    = $is_edit ? ( $location->get_company_phone() ?? '' ) : '';

        $industry_groups = $this->service->get_industry_groups();
        ?>
        <div class="smooth-booking-card smooth-booking-location-form-card">
            <div class="smooth-booking-form-header">
                <h2><?php echo $is_edit ? esc_html__( 'Edit location', 'smooth-booking' ) : esc_html__( 'Add new location', 'smooth-booking' ); ?></h2>
                <div class="smooth-booking-form-header__actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-cancel">
                            <?php esc_html_e( 'Back to list', 'smooth-booking' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smooth-booking-location-form">
                <?php wp_nonce_field( 'smooth_booking_save_location' ); ?>
                <input type="hidden" name="action" value="smooth_booking_save_location" />
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="location_id" value="<?php echo esc_attr( $location->get_id() ); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-name"><?php esc_html_e( 'Location name', 'smooth-booking' ); ?><span class="required">*</span></label></th>
                            <td>
                                <input type="text" id="smooth-booking-location-name" name="location_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Location image', 'smooth-booking' ); ?></th>
                            <td>
                                <div class="smooth-booking-avatar-field">
                                    <div class="smooth-booking-avatar-preview">
                                        <?php echo $this->get_location_avatar_html( $location, $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                    <input type="hidden" name="location_profile_image_id" value="<?php echo esc_attr( $profile_image_id ); ?>" />
                                    <button type="button" class="sba-btn sba-btn__small sba-btn__filled smooth-booking-avatar-select"><?php esc_html_e( 'Select image', 'smooth-booking' ); ?></button>
                                    <button type="button" class="sba-btn sba-btn__small sba-btn__filled-light smooth-booking-avatar-remove"<?php if ( ! $profile_image_id ) : ?> style="display:none"<?php endif; ?>><?php esc_html_e( 'Remove image', 'smooth-booking' ); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-address"><?php esc_html_e( 'Address', 'smooth-booking' ); ?></label></th>
                            <td>
                                <textarea id="smooth-booking-location-address" name="location_address" rows="3" class="large-text"><?php echo esc_textarea( $address ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-phone"><?php esc_html_e( 'Phone', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="smooth-booking-location-phone" name="location_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-email"><?php esc_html_e( 'Base email address', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="email" id="smooth-booking-location-email" name="location_email" class="regular-text" value="<?php echo esc_attr( $base_email ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-website"><?php esc_html_e( 'Website', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="url" id="smooth-booking-location-website" name="location_website" class="regular-text" value="<?php echo esc_attr( $website ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-timezone"><?php esc_html_e( 'Time zone', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-location-timezone" name="location_timezone" class="regular-text">
                                    <?php echo wp_timezone_choice( $timezone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select the local time zone used for scheduling at this location.', 'smooth-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-location-industry"><?php esc_html_e( 'Industry', 'smooth-booking' ); ?></label></th>
                            <td>
                                <select id="smooth-booking-location-industry" class="form-control custom-select" name="location_industry">
                                    <option value="0"<?php selected( 0, $industry_id ); ?>><?php esc_html_e( 'Select industry', 'smooth-booking' ); ?></option>
                                    <?php foreach ( $industry_groups as $group ) : ?>
                                        <optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
                                            <?php foreach ( $group['options'] as $option ) : ?>
                                                <option value="<?php echo esc_attr( (string) $option['value'] ); ?>"<?php selected( (int) $option['value'], $industry_id ); ?>><?php echo esc_html( $option['label'] ); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Is event location?', 'smooth-booking' ); ?></th>
                            <td>
                                <label for="smooth-booking-location-is-event">
                                    <input type="checkbox" id="smooth-booking-location-is-event" name="location_is_event" value="1"<?php checked( $is_event ); ?> />
                                    <?php esc_html_e( 'Enable if this location is used for events.', 'smooth-booking' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr class="smooth-booking-form-section-heading">
                            <th colspan="2"><?php esc_html_e( 'Company details', 'smooth-booking' ); ?></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-company-name"><?php esc_html_e( 'Company name', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="smooth-booking-company-name" name="location_company_name" class="regular-text" value="<?php echo esc_attr( $company_name ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-company-address"><?php esc_html_e( 'Company address', 'smooth-booking' ); ?></label></th>
                            <td>
                                <textarea id="smooth-booking-company-address" name="location_company_address" rows="3" class="large-text"><?php echo esc_textarea( $company_address ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smooth-booking-company-phone"><?php esc_html_e( 'Company phone', 'smooth-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="smooth-booking-company-phone" name="location_company_phone" class="regular-text" value="<?php echo esc_attr( $company_phone ); ?>" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="smooth-booking-form-actions">
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $this->get_base_page() ); ?>" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-cancel">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </a>
                    <?php else : ?>
                        <button type="button" class="sba-btn sba-btn__medium sba-btn__filled-light smooth-booking-form-dismiss" data-target="location-form">
                            <?php esc_html_e( 'Cancel', 'smooth-booking' ); ?>
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="sba-btn sba-btn--primary sba-btn__medium smooth-booking-form-submit">
                        <?php echo $is_edit ? esc_html__( 'Update location', 'smooth-booking' ) : esc_html__( 'Create location', 'smooth-booking' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    private function add_notice( string $type, string $message ): void {
        set_transient(
            $this->get_notice_key(),
            [
                'type'    => $type,
                'message' => $message,
            ],
            MINUTE_IN_SECONDS
        );
    }

    private function consume_notice(): ?array {
        $key    = $this->get_notice_key();
        $notice = get_transient( $key );

        if ( false !== $notice && isset( $notice['type'], $notice['message'] ) ) {
            delete_transient( $key );
            return $notice;
        }

        return null;
    }

    private function get_notice_key(): string {
        return sprintf( self::NOTICE_TRANSIENT_TEMPLATE, get_current_user_id() );
    }

    private function get_base_page(): string {
        return add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'admin.php' ) );
    }

    private function get_view_link( string $view ): string {
        $args = [ 'page' => self::MENU_SLUG ];

        if ( 'deleted' === $view ) {
            $args['view'] = 'deleted';
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    private function get_edit_link( int $location_id ): string {
        return add_query_arg(
            [
                'page'        => self::MENU_SLUG,
                'action'      => 'edit',
                'location_id' => $location_id,
            ],
            admin_url( 'admin.php' )
        );
    }

    private function get_location_avatar_html( ?Location $location, string $name = '' ): string {
        $image_id = $location ? ( $location->get_profile_image_id() ?? 0 ) : 0;

        if ( $image_id ) {
            $image = wp_get_attachment_image( $image_id, 'thumbnail', false, [ 'class' => 'smooth-booking-avatar-image' ] );

            if ( $image ) {
                return '<span class="smooth-booking-avatar-wrapper">' . $image . '</span>';
            }
        }

        $placeholder_icon = '<span class="dashicons dashicons-location" aria-hidden="true"></span>';
        $screen_reader    = '<span class="screen-reader-text">' . esc_html( $name ?: __( 'Location image', 'smooth-booking' ) ) . '</span>';

        return '<span class="smooth-booking-avatar-wrapper smooth-booking-avatar-wrapper--placeholder">' . $placeholder_icon . $screen_reader . '</span>';
    }
}
