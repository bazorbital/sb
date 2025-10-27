<?php
/**
 * WP-CLI commands for location management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Locations\Location;
use SmoothBooking\Domain\Locations\LocationService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function array_key_exists;
use function is_wp_error;
use function sprintf;
use function strtolower;
use function trim;
use function in_array;
use function is_bool;

/**
 * Provides WP-CLI access to location workflows.
 */
class LocationsCommand extends WP_CLI_Command {
    private LocationService $service;

    public function __construct( LocationService $service ) {
        $this->service = $service;
    }

    /**
     * List locations.
     *
     * ## OPTIONS
     *
     * [--include-deleted]
     * : Include soft-deleted locations in the output.
     *
     * [--only-deleted]
     * : Show only soft-deleted locations.
     */
    public function list( array $args, array $assoc_args ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyword
        $only_deleted    = isset( $assoc_args['only-deleted'] );
        $include_deleted = $only_deleted || isset( $assoc_args['include-deleted'] );

        $locations = $this->service->list_locations(
            [
                'include_deleted' => $include_deleted,
                'only_deleted'    => $only_deleted,
            ]
        );

        if ( empty( $locations ) ) {
            log( 'No locations found.' );
            return;
        }

        foreach ( $locations as $location ) {
            if ( ! $location instanceof Location ) {
                continue;
            }

            $label = $location->is_event_location() ? 'event' : 'standard';

            line( sprintf(
                '#%d %s — %s (%s)',
                $location->get_id(),
                $location->get_name(),
                $location->get_address() ?: 'n/a',
                $label
            ) );
        }
    }

    /**
     * Create a new location.
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Location name.
     *
     * [--address=<address>]
     * : Location address.
     *
     * [--phone=<phone>]
     * : Contact phone number.
     *
     * [--base-email=<email>]
     * : Base email address.
     *
     * [--website=<url>]
     * : Website URL.
     *
     * [--industry=<id>]
     * : Industry identifier.
     *
     * [--is-event=<yes|no>]
     * : Whether the location is used for events.
     *
     * [--profile-image-id=<id>]
     * : Attachment ID for the profile image.
     *
     * [--company-name=<name>]
     * : Company name.
     *
     * [--company-address=<address>]
     * : Company address.
     *
     * [--company-phone=<phone>]
     * : Company phone number.
     */
    public function create( array $args, array $assoc_args ): void {
        if ( empty( $assoc_args['name'] ) ) {
            error( 'The --name option is required.' );
        }

        $result = $this->service->create_location( $this->prepare_payload_from_cli( $assoc_args ) );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Location #%d created.', $result->get_id() ) );
    }

    /**
     * Update an existing location.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Identifier of the location to update.
     *
     * [--name=<name>]
     * : Updated name.
     *
     * Other options match the create command and override persisted values.
     */
    public function update( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Location ID is required.' );
        }

        $location_id = (int) $args[0];
        $location    = $this->service->get_location( $location_id );

        if ( is_wp_error( $location ) ) {
            error( $location->get_error_message() );
        }

        $payload = $this->prepare_payload_from_cli( $assoc_args, $location );

        $result = $this->service->update_location( $location_id, $payload );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Location #%d updated.', $location_id ) );
    }

    /**
     * Soft delete a location.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Identifier of the location to delete.
     */
    public function delete( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Location ID is required.' );
        }

        $location_id = (int) $args[0];
        $result      = $this->service->delete_location( $location_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Location #%d deleted.', $location_id ) );
    }

    /**
     * Restore a soft-deleted location.
     *
     * ## OPTIONS
     *
     * <location-id>
     * : Identifier of the location to restore.
     */
    public function restore( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Location ID is required.' );
        }

        $location_id = (int) $args[0];
        $result      = $this->service->restore_location( $location_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Location #%d restored.', $location_id ) );
    }

    /**
     * Prepare payload from CLI options.
     *
     * @param array<string, mixed>   $assoc_args CLI associative arguments.
     * @param Location|null $fallback Existing location for defaults.
     *
     * @return array<string, mixed>
     */
    private function prepare_payload_from_cli( array $assoc_args, ?Location $fallback = null ): array {
        $payload = [
            'name'              => $fallback ? $fallback->get_name() : '',
            'profile_image_id'  => $fallback ? ( $fallback->get_profile_image_id() ?? 0 ) : 0,
            'address'           => $fallback ? ( $fallback->get_address() ?? '' ) : '',
            'phone'             => $fallback ? ( $fallback->get_phone() ?? '' ) : '',
            'base_email'        => $fallback ? ( $fallback->get_base_email() ?? '' ) : '',
            'website'           => $fallback ? ( $fallback->get_website() ?? '' ) : '',
            'industry_id'       => $fallback ? $fallback->get_industry_id() : 0,
            'is_event_location' => $fallback ? $fallback->is_event_location() : false,
            'company_name'      => $fallback ? ( $fallback->get_company_name() ?? '' ) : '',
            'company_address'   => $fallback ? ( $fallback->get_company_address() ?? '' ) : '',
            'company_phone'     => $fallback ? ( $fallback->get_company_phone() ?? '' ) : '',
        ];

        if ( array_key_exists( 'name', $assoc_args ) ) {
            $payload['name'] = $assoc_args['name'];
        }

        if ( array_key_exists( 'profile-image-id', $assoc_args ) ) {
            $payload['profile_image_id'] = (int) $assoc_args['profile-image-id'];
        }

        if ( array_key_exists( 'address', $assoc_args ) ) {
            $payload['address'] = $assoc_args['address'];
        }

        if ( array_key_exists( 'phone', $assoc_args ) ) {
            $payload['phone'] = $assoc_args['phone'];
        }

        if ( array_key_exists( 'base-email', $assoc_args ) ) {
            $payload['base_email'] = $assoc_args['base-email'];
        }

        if ( array_key_exists( 'website', $assoc_args ) ) {
            $payload['website'] = $assoc_args['website'];
        }

        if ( array_key_exists( 'industry', $assoc_args ) ) {
            $payload['industry_id'] = (int) $assoc_args['industry'];
        }

        if ( array_key_exists( 'is-event', $assoc_args ) ) {
            $payload['is_event_location'] = $this->coerce_boolean( $assoc_args['is-event'] );
        }

        if ( array_key_exists( 'company-name', $assoc_args ) ) {
            $payload['company_name'] = $assoc_args['company-name'];
        }

        if ( array_key_exists( 'company-address', $assoc_args ) ) {
            $payload['company_address'] = $assoc_args['company-address'];
        }

        if ( array_key_exists( 'company-phone', $assoc_args ) ) {
            $payload['company_phone'] = $assoc_args['company-phone'];
        }

        return $payload;
    }

    private function coerce_boolean( $value ): bool { // phpcs:ignore Squiz.Commenting.FunctionComment.MissingParamComment
        if ( is_bool( $value ) ) {
            return $value;
        }

        $normalized = strtolower( trim( (string) $value ) );

        return in_array( $normalized, [ '1', 'yes', 'true', 'on', 'y' ], true );
    }
}
