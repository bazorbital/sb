<?php
/**
 * Business logic for managing locations.
 *
 * @package SmoothBooking\Domain\Locations
 */

namespace SmoothBooking\Domain\Locations;

use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use function __;
use function absint;
use function apply_filters;
use function array_map;
use function array_merge;
use function esc_html__;
use function esc_url_raw;
use function in_array;
use function is_email;
use function is_wp_error;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function timezone_identifiers_list;
use function trim;
use function wp_unslash;

/**
 * Provides validation and orchestration for location CRUD.
 */
class LocationService {
    private LocationRepositoryInterface $repository;

    private Logger $logger;

    public function __construct( LocationRepositoryInterface $repository, Logger $logger ) {
        $this->repository = $repository;
        $this->logger     = $logger;
    }

    /**
     * Retrieve the configured industries grouped by vertical.
     *
     * @return array<int, array{label:string,options:array<int,array{value:int,label:string}>}>
     */
    public function get_industry_groups(): array {
        $groups = apply_filters( 'smooth_booking_location_industries', $this->get_default_industry_groups() );

        return array_map(
            static function ( array $group ): array {
                $group['options'] = array_map(
                    static function ( array $option ): array {
                        return [
                            'value' => (int) $option['value'],
                            'label' => (string) $option['label'],
                        ];
                    },
                    $group['options'] ?? []
                );

                $group['label'] = (string) $group['label'];

                return $group;
            },
            $groups
        );
    }

    /**
     * Retrieve a human readable label for the provided industry ID.
     */
    public function get_industry_label( int $industry_id ): string {
        if ( 0 === $industry_id ) {
            return esc_html__( 'Not specified', 'smooth-booking' );
        }

        foreach ( $this->get_industry_groups() as $group ) {
            foreach ( $group['options'] as $option ) {
                if ( $industry_id === (int) $option['value'] ) {
                    return (string) $option['label'];
                }
            }
        }

        return esc_html__( 'Custom industry', 'smooth-booking' );
    }

    /**
     * List locations with optional deleted filters.
     *
     * @return Location[]
     */
    public function list_locations( array $args = [] ): array {
        $include_deleted = ! empty( $args['include_deleted'] );
        $only_deleted    = ! empty( $args['only_deleted'] );

        $locations = $this->repository->all( $include_deleted, $only_deleted );

        /**
         * Filter the location list prior to display.
         *
         * @hook smooth_booking_locations_list
         * @since 0.10.0
         *
         * @param Location[] $locations List of locations.
         * @param array      $args      Arguments used for retrieval.
         */
        return apply_filters( 'smooth_booking_locations_list', $locations, $args );
    }

    /**
     * Retrieve a single location.
     *
     * @return Location|WP_Error
     */
    public function get_location( int $location_id ) {
        $location = $this->repository->find( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        return $location;
    }

    /**
     * Retrieve a location including deleted entries.
     *
     * @return Location|WP_Error
     */
    public function get_location_with_deleted( int $location_id ) {
        $location = $this->repository->find_with_deleted( $location_id );

        if ( null === $location ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        return $location;
    }

    /**
     * Create a new location.
     *
     * @param array<string, mixed> $data Submitted data.
     *
     * @return Location|WP_Error
     */
    public function create_location( array $data ) {
        $validated = $this->validate_location_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->create( $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed creating location: ' . $result->get_error_message() );
            return $result;
        }

        return $result;
    }

    /**
     * Update an existing location.
     *
     * @param int                   $location_id Location identifier.
     * @param array<string, mixed> $data        Submitted data.
     *
     * @return Location|WP_Error
     */
    public function update_location( int $location_id, array $data ) {
        $existing = $this->repository->find_with_deleted( $location_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        if ( $existing->is_deleted() ) {
            return new WP_Error(
                'smooth_booking_location_deleted',
                __( 'The location has been deleted and must be restored before editing.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_location_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->update( $location_id, $validated );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed updating location: ' . $result->get_error_message() );
            return $result;
        }

        return $result;
    }

    /**
     * Soft delete a location.
     *
     * @return true|WP_Error
     */
    public function delete_location( int $location_id ) {
        $existing = $this->repository->find( $location_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        $result = $this->repository->soft_delete( $location_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed deleting location: ' . $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Restore a soft deleted location.
     *
     * @return Location|WP_Error
     */
    public function restore_location( int $location_id ) {
        $existing = $this->repository->find_with_deleted( $location_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_location_not_found',
                __( 'The requested location could not be found.', 'smooth-booking' )
            );
        }

        if ( ! $existing->is_deleted() ) {
            return new WP_Error(
                'smooth_booking_location_not_deleted',
                __( 'The location is already active.', 'smooth-booking' )
            );
        }

        $result = $this->repository->restore( $location_id );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed restoring location: ' . $result->get_error_message() );
            return $result;
        }

        return $this->repository->find( $location_id ) ?? $existing;
    }

    /**
     * Validate and normalise submitted data.
     *
     * @param array<string, mixed> $data Submitted payload.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function validate_location_data( array $data ) {
        $name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( (string) $data['name'] ) ) : '';

        if ( '' === $name ) {
            return new WP_Error(
                'smooth_booking_location_invalid_name',
                __( 'Location name is required.', 'smooth-booking' )
            );
        }

        $profile_image_id = isset( $data['profile_image_id'] ) ? absint( $data['profile_image_id'] ) : 0;
        $address          = isset( $data['address'] ) ? sanitize_textarea_field( wp_unslash( (string) $data['address'] ) ) : '';
        $phone            = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( (string) $data['phone'] ) ) : '';
        $base_email       = isset( $data['base_email'] ) ? sanitize_email( wp_unslash( (string) $data['base_email'] ) ) : '';
        $website          = isset( $data['website'] ) ? esc_url_raw( wp_unslash( (string) $data['website'] ) ) : '';
        $company_name     = isset( $data['company_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['company_name'] ) ) : '';
        $company_address  = isset( $data['company_address'] ) ? sanitize_textarea_field( wp_unslash( (string) $data['company_address'] ) ) : '';
        $company_phone    = isset( $data['company_phone'] ) ? sanitize_text_field( wp_unslash( (string) $data['company_phone'] ) ) : '';

        if ( '' !== $base_email && ! is_email( $base_email ) ) {
            return new WP_Error(
                'smooth_booking_location_invalid_email',
                __( 'Please provide a valid base email address.', 'smooth-booking' )
            );
        }

        $industry_id = isset( $data['industry_id'] ) ? absint( $data['industry_id'] ) : 0;
        $allowed     = array_merge( [ 0 ], array_map(
            static function ( array $option ): int {
                return (int) $option['value'];
            },
            $this->get_flat_industry_options()
        ) );

        if ( ! in_array( $industry_id, $allowed, true ) ) {
            return new WP_Error(
                'smooth_booking_location_invalid_industry',
                __( 'Please choose a valid industry option.', 'smooth-booking' )
            );
        }

        $timezone = isset( $data['timezone'] ) ? sanitize_text_field( wp_unslash( (string) $data['timezone'] ) ) : '';
        $timezone = '' !== $timezone ? $timezone : 'Europe/Budapest';

        if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
            return new WP_Error(
                'smooth_booking_location_invalid_timezone',
                __( 'Please select a valid time zone.', 'smooth-booking' )
            );
        }

        $is_event_location = rest_sanitize_boolean( $data['is_event_location'] ?? false ) ? 1 : 0;

        return [
            'name'              => $name,
            'profile_image_id'  => $profile_image_id,
            'address'           => '' !== trim( $address ) ? $address : null,
            'phone'             => '' !== trim( $phone ) ? $phone : null,
            'base_email'        => '' !== trim( (string) $base_email ) ? $base_email : null,
            'website'           => '' !== trim( $website ) ? $website : null,
            'timezone'          => $timezone,
            'industry_id'       => $industry_id,
            'is_event_location' => $is_event_location,
            'company_name'      => '' !== trim( $company_name ) ? $company_name : null,
            'company_address'   => '' !== trim( $company_address ) ? $company_address : null,
            'company_phone'     => '' !== trim( $company_phone ) ? $company_phone : null,
        ];
    }

    /**
     * Retrieve available timezone options.
     *
     * @return array<int, array{value:string,label:string}>
     */
    public function get_timezone_options(): array {
        $identifiers = timezone_identifiers_list();

        return array_map(
            static function ( string $timezone ): array {
                return [
                    'value' => $timezone,
                    'label' => $timezone,
                ];
            },
            $identifiers
        );
    }

    /**
     * Flatten industry options for validation.
     *
     * @return array<int, array{value:int,label:string}>
     */
    private function get_flat_industry_options(): array {
        $options = [];

        foreach ( $this->get_industry_groups() as $group ) {
            foreach ( $group['options'] as $option ) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * Default industry definitions.
     *
     * @return array<int, array{label:string,options:array<int,array{value:int,label:string}>}>
     */
    private function get_default_industry_groups(): array {
        return [
            [
                'label'   => __( 'Education', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 34, 'label' => __( 'Universities', 'smooth-booking' ) ],
                    [ 'value' => 35, 'label' => __( 'Colleges', 'smooth-booking' ) ],
                    [ 'value' => 36, 'label' => __( 'Schools', 'smooth-booking' ) ],
                    [ 'value' => 37, 'label' => __( 'Libraries', 'smooth-booking' ) ],
                    [ 'value' => 38, 'label' => __( 'Teaching', 'smooth-booking' ) ],
                    [ 'value' => 39, 'label' => __( 'Tutoring lessons', 'smooth-booking' ) ],
                    [ 'value' => 40, 'label' => __( 'Parent meetings', 'smooth-booking' ) ],
                    [ 'value' => 41, 'label' => __( 'Services', 'smooth-booking' ) ],
                    [ 'value' => 42, 'label' => __( 'Child care', 'smooth-booking' ) ],
                    [ 'value' => 43, 'label' => __( 'Driving Schools', 'smooth-booking' ) ],
                    [ 'value' => 44, 'label' => __( 'Driving Instructors', 'smooth-booking' ) ],
                    [ 'value' => 45, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Beauty and wellness', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 11, 'label' => __( 'Beauty salons', 'smooth-booking' ) ],
                    [ 'value' => 12, 'label' => __( 'Hair salons', 'smooth-booking' ) ],
                    [ 'value' => 13, 'label' => __( 'Nail salons', 'smooth-booking' ) ],
                    [ 'value' => 14, 'label' => __( 'Eyelash extensions', 'smooth-booking' ) ],
                    [ 'value' => 15, 'label' => __( 'Spa', 'smooth-booking' ) ],
                    [ 'value' => 16, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Events and entertainment', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 46, 'label' => __( 'Events (One time and Recurring)', 'smooth-booking' ) ],
                    [ 'value' => 47, 'label' => __( 'Business events', 'smooth-booking' ) ],
                    [ 'value' => 48, 'label' => __( 'Meeting rooms', 'smooth-booking' ) ],
                    [ 'value' => 49, 'label' => __( 'Escape rooms', 'smooth-booking' ) ],
                    [ 'value' => 50, 'label' => __( 'Art classes', 'smooth-booking' ) ],
                    [ 'value' => 51, 'label' => __( 'Equipment rental', 'smooth-booking' ) ],
                    [ 'value' => 52, 'label' => __( 'Photographers', 'smooth-booking' ) ],
                    [ 'value' => 53, 'label' => __( 'Restaurants', 'smooth-booking' ) ],
                    [ 'value' => 54, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Medical', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 17, 'label' => __( 'Medical Clinics & Doctors', 'smooth-booking' ) ],
                    [ 'value' => 18, 'label' => __( 'Dentists', 'smooth-booking' ) ],
                    [ 'value' => 19, 'label' => __( 'Chiropractors', 'smooth-booking' ) ],
                    [ 'value' => 20, 'label' => __( 'Acupuncture', 'smooth-booking' ) ],
                    [ 'value' => 21, 'label' => __( 'Massage', 'smooth-booking' ) ],
                    [ 'value' => 22, 'label' => __( 'Physiologists', 'smooth-booking' ) ],
                    [ 'value' => 23, 'label' => __( 'Psychologists', 'smooth-booking' ) ],
                    [ 'value' => 24, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Officials', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 55, 'label' => __( 'City councils', 'smooth-booking' ) ],
                    [ 'value' => 56, 'label' => __( 'Embassies and consulates', 'smooth-booking' ) ],
                    [ 'value' => 57, 'label' => __( 'Attorneys', 'smooth-booking' ) ],
                    [ 'value' => 58, 'label' => __( 'Legal services', 'smooth-booking' ) ],
                    [ 'value' => 59, 'label' => __( 'Financial services', 'smooth-booking' ) ],
                    [ 'value' => 60, 'label' => __( 'Interview scheduling', 'smooth-booking' ) ],
                    [ 'value' => 61, 'label' => __( 'Call centers', 'smooth-booking' ) ],
                    [ 'value' => 62, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Personal meetings and services', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 25, 'label' => __( 'Consulting', 'smooth-booking' ) ],
                    [ 'value' => 26, 'label' => __( 'Counselling', 'smooth-booking' ) ],
                    [ 'value' => 27, 'label' => __( 'Coaching', 'smooth-booking' ) ],
                    [ 'value' => 28, 'label' => __( 'Spiritual services', 'smooth-booking' ) ],
                    [ 'value' => 29, 'label' => __( 'Design consultants', 'smooth-booking' ) ],
                    [ 'value' => 30, 'label' => __( 'Cleaning', 'smooth-booking' ) ],
                    [ 'value' => 31, 'label' => __( 'Household', 'smooth-booking' ) ],
                    [ 'value' => 32, 'label' => __( 'Pet services', 'smooth-booking' ) ],
                    [ 'value' => 33, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Retailers', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 1, 'label' => __( 'Supermarket', 'smooth-booking' ) ],
                    [ 'value' => 2, 'label' => __( 'Retail Finance', 'smooth-booking' ) ],
                    [ 'value' => 3, 'label' => __( 'Other retailers', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Sport', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 4, 'label' => __( 'Personal trainers', 'smooth-booking' ) ],
                    [ 'value' => 5, 'label' => __( 'Gyms', 'smooth-booking' ) ],
                    [ 'value' => 6, 'label' => __( 'Fitness classes', 'smooth-booking' ) ],
                    [ 'value' => 7, 'label' => __( 'Yoga classes', 'smooth-booking' ) ],
                    [ 'value' => 8, 'label' => __( 'Golf classes', 'smooth-booking' ) ],
                    [ 'value' => 9, 'label' => __( 'Sport items renting', 'smooth-booking' ) ],
                    [ 'value' => 10, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
            [
                'label'   => __( 'Other', 'smooth-booking' ),
                'options' => [
                    [ 'value' => 63, 'label' => __( 'Other', 'smooth-booking' ) ],
                ],
            ],
        ];
    }
}
