<?php
/**
 * WP-CLI commands for service management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function array_map;
use function explode;
use function is_wp_error;
use function number_format;
use function sprintf;

/**
 * Provides WP-CLI access to service management workflows.
 */
class ServicesCommand extends WP_CLI_Command {
    /**
     * @var ServiceService
     */
    private ServiceService $service;

    /**
     * Constructor.
     */
    public function __construct( ServiceService $service ) {
        $this->service = $service;
    }

    /**
     * List services.
     *
     * ## OPTIONS
     *
     * [--include-deleted]
     * : Include soft-deleted services in the output.
     *
     * [--only-deleted]
     * : Show only soft-deleted services.
     *
     * ## EXAMPLES
     *
     *     wp smooth services list
     *     wp smooth services list --only-deleted
     */
    public function list( array $args, array $assoc_args ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyWord
        $only_deleted    = isset( $assoc_args['only-deleted'] );
        $include_deleted = $only_deleted || isset( $assoc_args['include-deleted'] );

        $services = $this->service->list_services(
            [
                'include_deleted' => $include_deleted,
                'only_deleted'    => $only_deleted,
            ]
        );

        if ( empty( $services ) ) {
            log( 'No services found.' );
            return;
        }

        foreach ( $services as $service ) {
            if ( ! $service instanceof Service ) {
                continue;
            }

            $price = $service->get_price();
            $label = null === $price ? 'n/a' : number_format( (float) $price, 2 );

            line( sprintf( '#%d %s (%s) — price: %s', $service->get_id(), $service->get_name(), $service->get_visibility(), $label ) );
        }
    }

    /**
     * Create a new service.
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Service name.
     *
     * [--price=<price>]
     * : Monetary price for the service.
     *
     * [--visibility=<public|private>]
     * : Visibility setting.
     *
     * [--profile-image-id=<id>]
     * : Attachment ID of the service image.
     *
     * [--default-background-color=<hex>]
     * : HEX background color code used in calendars.
     *
     * [--default-text-color=<hex>]
     * : HEX text color code used in calendars.
     *
     * [--payment-methods-mode=<default|custom>]
     * : Payment methods mode.
     *
     * [--providers-preference=<option>]
     * : Provider preference strategy.
     *
     * [--providers-random-tie=<enabled|disabled>]
     * : Enable random selection when multiple providers fit the criteria.
     *
     * [--occupancy-before=<days>]
     * : Days before appointment to consider for occupancy.
     *
     * [--occupancy-after=<days>]
     * : Days after appointment to consider for occupancy.
     *
     * [--duration=<key>]
     * : Duration key (e.g. 30_minutes, one_day).
     *
     * [--slot-length=<key>]
     * : Slot length key.
     *
     * [--padding-before=<key>]
     * : Padding before appointments.
     *
     * [--padding-after=<key>]
     * : Padding after appointments.
     *
     * [--online-meeting=<off|zoom|google_meet>]
     * : Online meeting provider.
     *
     * [--limit-per-customer=<key>]
     * : Appointment limit per customer.
     *
     * [--final-step-url-enabled=<enabled|disabled>]
     * : Toggle final step redirect URL.
     *
     * [--final-step-url=<url>]
     * : Redirect URL after booking.
     *
     * [--min-time-booking=<key>]
     * : Minimum time requirement prior to booking.
     *
     * [--min-time-cancel=<key>]
     * : Minimum time requirement prior to canceling.
     *
     * [--category=<id>]
     * : Assign category IDs (repeat for multiple).
     *
     * [--new-categories=<list>]
     * : Comma separated categories to create.
     *
     * [--tag=<id>]
     * : Assign tag IDs (repeat for multiple).
     *
     * [--new-tags=<list>]
     * : Comma separated tags to create.
     *
     * [--provider=<employee_id[:order]>]
     * : Assign providers with optional order.
     *
     * [--info=<text>]
     * : Additional information text.
     */
    public function create( array $args, array $assoc_args ): void {
        if ( empty( $assoc_args['name'] ) ) {
            error( 'The --name option is required.' );
        }

        $result = $this->service->create_service( $this->prepare_payload_from_cli( $assoc_args ) );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Service #%d created.', $result->get_id() ) );
    }

    /**
     * Update an existing service.
     *
     * ## OPTIONS
     *
     * <service-id>
     * : Identifier of the service to update.
     *
     * [--name=<name>]
     * : Updated name.
     *
     * Other options match the create command and override persisted values.
     */
    public function update( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Service ID is required.' );
        }

        $service_id = (int) $args[0];
        $service    = $this->service->get_service( $service_id );

        if ( is_wp_error( $service ) ) {
            error( $service->get_error_message() );
        }

        $payload = $this->prepare_payload_from_cli( $assoc_args, $service );

        $result = $this->service->update_service( $service_id, $payload );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Service #%d updated.', $service_id ) );
    }

    /**
     * Soft delete a service.
     */
    public function delete( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Service ID is required.' );
        }

        $service_id = (int) $args[0];

        $result = $this->service->delete_service( $service_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Service #%d deleted.', $service_id ) );
    }

    /**
     * Restore a soft-deleted service.
     */
    public function restore( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Service ID is required.' );
        }

        $service_id = (int) $args[0];

        $result = $this->service->restore_service( $service_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Service #%d restored.', $service_id ) );
    }

    /**
     * Prepare payload from CLI arguments.
     *
     * @param array<string, mixed> $assoc_args Arguments passed to the command.
     * @param Service|null         $existing   Existing service for defaults.
     *
     * @return array<string, mixed>
     */
    private function prepare_payload_from_cli( array $assoc_args, ?Service $existing = null ): array {
        $category_ids = isset( $assoc_args['category'] ) ? (array) $assoc_args['category'] : [];
        $tag_ids      = isset( $assoc_args['tag'] ) ? (array) $assoc_args['tag'] : [];
        $providers    = isset( $assoc_args['provider'] ) ? $this->parse_providers( (array) $assoc_args['provider'] ) : [];

        if ( $existing instanceof Service ) {
            if ( empty( $category_ids ) ) {
                $category_ids = array_map(
                    static function ( array $category ): int {
                        return (int) $category['id'];
                    },
                    $existing->to_array()['categories']
                );
            }

            if ( empty( $tag_ids ) ) {
                $tag_ids = array_map(
                    static function ( array $tag ): int {
                        return (int) $tag['id'];
                    },
                    $existing->to_array()['tags']
                );
            }

            if ( empty( $providers ) ) {
                $providers = $existing->get_providers();
            }
        }

        return [
            'name'                         => $assoc_args['name'] ?? ( $existing ? $existing->get_name() : '' ),
            'profile_image_id'             => $assoc_args['profile-image-id'] ?? ( $existing ? ( $existing->get_image_id() ?? 0 ) : 0 ),
            'default_background_color'     => $assoc_args['default-background-color'] ?? $assoc_args['default-color'] ?? ( $existing ? ( $existing->get_background_color() ?? '' ) : '' ),
            'default_text_color'           => $assoc_args['default-text-color'] ?? ( $existing ? ( $existing->get_text_color() ?? '' ) : '' ),
            'visibility'                   => $assoc_args['visibility'] ?? ( $existing ? $existing->get_visibility() : 'public' ),
            'price'                        => $assoc_args['price'] ?? ( $existing ? ( $existing->get_price() ?? '' ) : '' ),
            'payment_methods_mode'         => $assoc_args['payment-methods-mode'] ?? ( $existing ? $existing->get_payment_methods_mode() : 'default' ),
            'info'                         => $assoc_args['info'] ?? ( $existing ? ( $existing->get_info() ?? '' ) : '' ),
            'providers_preference'         => $assoc_args['providers-preference'] ?? ( $existing ? $existing->get_providers_preference() : 'specified_order' ),
            'providers_random_tie'         => $assoc_args['providers-random-tie'] ?? ( $existing ? ( $existing->is_providers_random_tie() ? 'enabled' : 'disabled' ) : 'disabled' ),
            'occupancy_period_before'      => $assoc_args['occupancy-before'] ?? ( $existing ? $existing->get_occupancy_period_before() : 0 ),
            'occupancy_period_after'       => $assoc_args['occupancy-after'] ?? ( $existing ? $existing->get_occupancy_period_after() : 0 ),
            'duration_key'                 => $assoc_args['duration'] ?? ( $existing ? $existing->get_duration_key() : '15_minutes' ),
            'slot_length_key'              => $assoc_args['slot-length'] ?? ( $existing ? $existing->get_slot_length_key() : 'default' ),
            'padding_before_key'           => $assoc_args['padding-before'] ?? ( $existing ? $existing->get_padding_before_key() : 'off' ),
            'padding_after_key'            => $assoc_args['padding-after'] ?? ( $existing ? $existing->get_padding_after_key() : 'off' ),
            'online_meeting_provider'      => $assoc_args['online-meeting'] ?? ( $existing ? $existing->get_online_meeting_provider() : 'off' ),
            'limit_per_customer'           => $assoc_args['limit-per-customer'] ?? ( $existing ? $existing->get_limit_per_customer() : 'off' ),
            'final_step_url_enabled'       => $assoc_args['final-step-url-enabled'] ?? ( $existing ? ( $existing->is_final_step_url_enabled() ? 'enabled' : 'disabled' ) : 'disabled' ),
            'final_step_url'               => $assoc_args['final-step-url'] ?? ( $existing ? ( $existing->get_final_step_url() ?? '' ) : '' ),
            'min_time_prior_booking_key'   => $assoc_args['min-time-booking'] ?? ( $existing ? $existing->get_min_time_prior_booking_key() : 'default' ),
            'min_time_prior_cancel_key'    => $assoc_args['min-time-cancel'] ?? ( $existing ? $existing->get_min_time_prior_cancel_key() : 'default' ),
            'category_ids'                 => array_map( 'intval', $category_ids ),
            'new_categories'               => $assoc_args['new-categories'] ?? '',
            'tag_ids'                      => array_map( 'intval', $tag_ids ),
            'new_tags'                     => $assoc_args['new-tags'] ?? '',
            'providers'                    => $providers,
        ];
    }

    /**
     * Parse provider CLI arguments.
     *
     * @param string[] $values Provider arguments.
     *
     * @return array<int, array{employee_id:int, order:int}>
     */
    private function parse_providers( array $values ): array {
        $providers = [];

        foreach ( $values as $value ) {
            $parts = explode( ':', (string) $value );

            $providers[] = [
                'employee_id' => (int) ( $parts[0] ?? 0 ),
                'order'       => isset( $parts[1] ) ? (int) $parts[1] : 0,
            ];
        }

        return $providers;
    }
}
