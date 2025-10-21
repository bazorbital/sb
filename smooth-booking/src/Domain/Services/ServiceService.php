<?php
/**
 * Business logic for managing services.
 *
 * @package SmoothBooking\Domain\Services
 */

namespace SmoothBooking\Domain\Services;

use SmoothBooking\Domain\Employees\EmployeeRepositoryInterface;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

use function __;
use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function esc_url_raw;
use function is_wp_error;
use function preg_split;
use function rest_sanitize_boolean;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function wp_kses_post;
use function wp_unslash;

/**
 * Provides validation and orchestration for services.
 */
class ServiceService {
    /**
     * Allowed visibility states.
     */
    private const VISIBILITY = [ 'public', 'private' ];

    /**
     * Provider preference options.
     */
    private const PROVIDER_PREFERENCES = [
        'most_expensive',
        'least_expensive',
        'specified_order',
        'least_occupied_day',
        'most_occupied_day',
        'least_occupied_period',
        'most_occupied_period',
    ];

    /**
     * Duration keys.
     */
    private const DURATION_OPTIONS = [
        '15_minutes',
        '30_minutes',
        '45_minutes',
        '60_minutes',
        '75_minutes',
        '90_minutes',
        '105_minutes',
        '120_minutes',
        '135_minutes',
        '150_minutes',
        '165_minutes',
        '180_minutes',
        '195_minutes',
        '210_minutes',
        '225_minutes',
        '240_minutes',
        '255_minutes',
        '270_minutes',
        '285_minutes',
        '300_minutes',
        '360_minutes',
        '420_minutes',
        '480_minutes',
        '540_minutes',
        '600_minutes',
        '660_minutes',
        '720_minutes',
        'one_day',
        'two_days',
        'three_days',
        'four_days',
        'five_days',
        'six_days',
        'one_week',
    ];

    /**
     * Slot length options.
     */
    private const SLOT_LENGTH_OPTIONS = [
        'default',
        'service_duration',
        '2_minutes',
        '4_minutes',
        '5_minutes',
        '10_minutes',
        '12_minutes',
        '15_minutes',
        '20_minutes',
        '30_minutes',
        '45_minutes',
        '60_minutes',
        '90_minutes',
        '120_minutes',
        '180_minutes',
        '240_minutes',
        '360_minutes',
    ];

    /**
     * Padding keys.
     */
    private const PADDING_OPTIONS = [
        'off',
        '15_minutes',
        '30_minutes',
        '45_minutes',
        '60_minutes',
        '75_minutes',
        '90_minutes',
        '105_minutes',
        '120_minutes',
        '135_minutes',
        '150_minutes',
        '165_minutes',
        '180_minutes',
        '195_minutes',
        '210_minutes',
        '225_minutes',
        '240_minutes',
        '255_minutes',
        '270_minutes',
        '285_minutes',
        '300_minutes',
        '360_minutes',
        '420_minutes',
        '480_minutes',
        '540_minutes',
        '600_minutes',
        '660_minutes',
        '720_minutes',
        'one_day',
    ];

    /**
     * Online meeting providers.
     */
    private const ONLINE_MEETING_OPTIONS = [ 'off', 'zoom', 'google_meet' ];

    /**
     * Customer limit options.
     */
    private const LIMIT_PER_CUSTOMER_OPTIONS = [
        'off',
        'upcoming',
        'per_24_hours',
        'per_day',
        'per_7_days',
        'per_week',
        'per_30_days',
        'per_month',
        'per_365_days',
        'per_year',
    ];

    /**
     * Minimum time requirement keys.
     */
    private const MIN_TIME_OPTIONS = [
        'default',
        'disabled',
        '30_minutes',
        '1_hour',
        '2_hours',
        '3_hours',
        '4_hours',
        '5_hours',
        '6_hours',
        '7_hours',
        '8_hours',
        '9_hours',
        '10_hours',
        '11_hours',
        '12_hours',
        '1_day',
        '2_days',
        '3_days',
        '4_days',
        '5_days',
        '6_days',
        '1_week',
        '2_weeks',
        '3_weeks',
        '4_weeks',
    ];

    /**
     * Payment method options.
     */
    private const PAYMENT_METHODS = [ 'default', 'custom' ];

    /**
     * @var ServiceRepositoryInterface
     */
    private ServiceRepositoryInterface $repository;

    /**
     * @var ServiceCategoryRepositoryInterface
     */
    private ServiceCategoryRepositoryInterface $category_repository;

    /**
     * @var ServiceTagRepositoryInterface
     */
    private ServiceTagRepositoryInterface $tag_repository;

    /**
     * @var EmployeeRepositoryInterface
     */
    private EmployeeRepositoryInterface $employee_repository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct(
        ServiceRepositoryInterface $repository,
        ServiceCategoryRepositoryInterface $category_repository,
        ServiceTagRepositoryInterface $tag_repository,
        EmployeeRepositoryInterface $employee_repository,
        Logger $logger
    ) {
        $this->repository          = $repository;
        $this->category_repository = $category_repository;
        $this->tag_repository      = $tag_repository;
        $this->employee_repository = $employee_repository;
        $this->logger              = $logger;
    }

    /**
     * Retrieve services.
     *
     * @return Service[]
     */
    public function list_services( array $args = [] ): array {
        $include_deleted = ! empty( $args['include_deleted'] );
        $only_deleted    = ! empty( $args['only_deleted'] );

        $services = $this->repository->all( $include_deleted, $only_deleted );

        return $this->attach_relations( $services );
    }

    /**
     * Get a single service.
     *
     * @return Service|WP_Error
     */
    public function get_service( int $service_id ) {
        $service = $this->repository->find( $service_id );

        if ( null === $service ) {
            return new WP_Error(
                'smooth_booking_service_not_found',
                __( 'The requested service could not be found.', 'smooth-booking' )
            );
        }

        return $this->enrich_service( $service );
    }

    /**
     * Create a new service.
     *
     * @param array<string, mixed> $data Submitted form data.
     *
     * @return Service|WP_Error
     */
    public function create_service( array $data ) {
        $validated = $this->validate_service_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->create( $validated['record'] );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed creating service: %s', $result->get_error_message() ) );
            return $result;
        }

        $service_id = $result->get_id();

        $this->repository->sync_service_categories( $service_id, $this->resolve_category_ids( $validated['category_ids'], $validated['new_categories'] ) );
        $this->repository->sync_service_tags( $service_id, $this->resolve_tag_ids( $validated['tag_ids'], $validated['new_tags'] ) );
        $this->repository->sync_service_providers( $service_id, $validated['providers'] );

        $service = $this->repository->find( $service_id );

        if ( null === $service ) {
            return $result;
        }

        return $this->enrich_service( $service );
    }

    /**
     * Update a service.
     *
     * @param array<string, mixed> $data Submitted data.
     *
     * @return Service|WP_Error
     */
    public function update_service( int $service_id, array $data ) {
        $existing = $this->repository->find( $service_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_service_not_found',
                __( 'The requested service could not be found.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_service_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $result = $this->repository->update( $service_id, $validated['record'] );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed updating service #%d: %s', $service_id, $result->get_error_message() ) );
            return $result;
        }

        $this->repository->sync_service_categories( $service_id, $this->resolve_category_ids( $validated['category_ids'], $validated['new_categories'] ) );
        $this->repository->sync_service_tags( $service_id, $this->resolve_tag_ids( $validated['tag_ids'], $validated['new_tags'] ) );
        $this->repository->sync_service_providers( $service_id, $validated['providers'] );

        $service = $this->repository->find( $service_id );

        if ( null === $service ) {
            return $existing;
        }

        return $this->enrich_service( $service );
    }

    /**
     * Soft delete a service.
     *
     * @return true|WP_Error
     */
    public function delete_service( int $service_id ) {
        $existing = $this->repository->find( $service_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_service_not_found',
                __( 'The requested service could not be found.', 'smooth-booking' )
            );
        }

        return $this->repository->soft_delete( $service_id );
    }

    /**
     * Restore a soft-deleted service.
     *
     * @return true|WP_Error
     */
    public function restore_service( int $service_id ) {
        $existing = $this->repository->find_with_deleted( $service_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_service_not_found',
                __( 'The requested service could not be found.', 'smooth-booking' )
            );
        }

        return $this->repository->restore( $service_id );
    }

    /**
     * List categories.
     *
     * @return ServiceCategory[]
     */
    public function list_categories(): array {
        return $this->category_repository->all();
    }

    /**
     * List tags.
     *
     * @return ServiceTag[]
     */
    public function list_tags(): array {
        return $this->tag_repository->all();
    }

    /**
     * Validate incoming data.
     *
     * @param array<string, mixed> $data Raw data.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validate_service_data( array $data ) {
        $name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( (string) $data['name'] ) ) : '';

        if ( '' === $name ) {
            return new WP_Error( 'smooth_booking_service_invalid_name', __( 'Service name is required.', 'smooth-booking' ) );
        }

        $price = null;

        if ( isset( $data['price'] ) && '' !== $data['price'] ) {
            $price = (float) $data['price'];

            if ( $price < 0 ) {
                return new WP_Error( 'smooth_booking_service_invalid_price', __( 'Price cannot be negative.', 'smooth-booking' ) );
            }
        }

        $color = isset( $data['default_color'] ) ? sanitize_hex_color( (string) $data['default_color'] ) : '';
        $color = $color ?: null;

        $visibility = isset( $data['visibility'] ) ? sanitize_key( (string) $data['visibility'] ) : 'public';

        if ( ! in_array( $visibility, self::VISIBILITY, true ) ) {
            $visibility = 'public';
        }

        $image_id = isset( $data['profile_image_id'] ) ? absint( $data['profile_image_id'] ) : 0;

        $payment_method = isset( $data['payment_methods_mode'] ) ? sanitize_key( (string) $data['payment_methods_mode'] ) : 'default';

        if ( ! in_array( $payment_method, self::PAYMENT_METHODS, true ) ) {
            $payment_method = 'default';
        }

        $providers_preference = isset( $data['providers_preference'] ) ? sanitize_key( (string) $data['providers_preference'] ) : 'specified_order';

        if ( ! in_array( $providers_preference, self::PROVIDER_PREFERENCES, true ) ) {
            $providers_preference = 'specified_order';
        }

        $providers_random = isset( $data['providers_random_tie'] ) ? rest_sanitize_boolean( $data['providers_random_tie'] ) : false;

        $occupancy_before = isset( $data['occupancy_period_before'] ) ? max( 0, absint( $data['occupancy_period_before'] ) ) : 0;
        $occupancy_after  = isset( $data['occupancy_period_after'] ) ? max( 0, absint( $data['occupancy_period_after'] ) ) : 0;

        $duration_key = isset( $data['duration_key'] ) ? sanitize_key( (string) $data['duration_key'] ) : '15_minutes';

        if ( ! in_array( $duration_key, self::DURATION_OPTIONS, true ) ) {
            $duration_key = '15_minutes';
        }

        $slot_length_key = isset( $data['slot_length_key'] ) ? sanitize_key( (string) $data['slot_length_key'] ) : 'default';

        if ( ! in_array( $slot_length_key, self::SLOT_LENGTH_OPTIONS, true ) ) {
            $slot_length_key = 'default';
        }

        $padding_before = isset( $data['padding_before_key'] ) ? sanitize_key( (string) $data['padding_before_key'] ) : 'off';

        if ( ! in_array( $padding_before, self::PADDING_OPTIONS, true ) ) {
            $padding_before = 'off';
        }

        $padding_after = isset( $data['padding_after_key'] ) ? sanitize_key( (string) $data['padding_after_key'] ) : 'off';

        if ( ! in_array( $padding_after, self::PADDING_OPTIONS, true ) ) {
            $padding_after = 'off';
        }

        $online_meeting = isset( $data['online_meeting_provider'] ) ? sanitize_key( (string) $data['online_meeting_provider'] ) : 'off';

        if ( ! in_array( $online_meeting, self::ONLINE_MEETING_OPTIONS, true ) ) {
            $online_meeting = 'off';
        }

        $limit_per_customer = isset( $data['limit_per_customer'] ) ? sanitize_key( (string) $data['limit_per_customer'] ) : 'off';

        if ( ! in_array( $limit_per_customer, self::LIMIT_PER_CUSTOMER_OPTIONS, true ) ) {
            $limit_per_customer = 'off';
        }

        $final_step_url_enabled = isset( $data['final_step_url_enabled'] ) ? rest_sanitize_boolean( $data['final_step_url_enabled'] ) : false;
        $final_step_url         = isset( $data['final_step_url'] ) ? esc_url_raw( (string) $data['final_step_url'] ) : '';

        if ( ! $final_step_url_enabled ) {
            $final_step_url = '';
        }

        $min_time_booking = isset( $data['min_time_prior_booking_key'] ) ? sanitize_key( (string) $data['min_time_prior_booking_key'] ) : 'default';

        if ( ! in_array( $min_time_booking, self::MIN_TIME_OPTIONS, true ) ) {
            $min_time_booking = 'default';
        }

        $min_time_cancel = isset( $data['min_time_prior_cancel_key'] ) ? sanitize_key( (string) $data['min_time_prior_cancel_key'] ) : 'default';

        if ( ! in_array( $min_time_cancel, self::MIN_TIME_OPTIONS, true ) ) {
            $min_time_cancel = 'default';
        }

        $info = isset( $data['info'] ) ? wp_kses_post( (string) $data['info'] ) : '';

        $category_ids = isset( $data['category_ids'] ) ? array_map( 'absint', (array) $data['category_ids'] ) : [];
        $new_categories = isset( $data['new_categories'] ) ? $this->sanitize_new_terms( (string) $data['new_categories'] ) : [];

        $tag_ids     = isset( $data['tag_ids'] ) ? array_map( 'absint', (array) $data['tag_ids'] ) : [];
        $new_tags    = isset( $data['new_tags'] ) ? $this->sanitize_new_terms( (string) $data['new_tags'] ) : [];

        $providers_input = isset( $data['providers'] ) ? (array) $data['providers'] : [];
        $providers       = $this->validate_providers( $providers_input );

        if ( is_wp_error( $providers ) ) {
            return $providers;
        }

        $record = [
            'name'                         => $name,
            'profile_image_id'             => $image_id > 0 ? $image_id : null,
            'default_color'                => $color,
            'visibility'                   => $visibility,
            'price'                        => $price,
            'payment_methods_mode'         => $payment_method,
            'info'                         => $info,
            'providers_preference'         => $providers_preference,
            'providers_random_tie'         => $providers_random ? 1 : 0,
            'occupancy_period_before'      => $occupancy_before,
            'occupancy_period_after'       => $occupancy_after,
            'duration_key'                 => $duration_key,
            'slot_length_key'              => $slot_length_key,
            'padding_before_key'           => $padding_before,
            'padding_after_key'            => $padding_after,
            'online_meeting_provider'      => $online_meeting,
            'limit_per_customer'           => $limit_per_customer,
            'final_step_url_enabled'       => $final_step_url_enabled ? 1 : 0,
            'final_step_url'               => $final_step_url ?: null,
            'min_time_prior_booking_key'   => $min_time_booking,
            'min_time_prior_cancel_key'    => $min_time_cancel,
        ];

        return [
            'record'         => $record,
            'category_ids'   => array_values( array_unique( array_filter( $category_ids ) ) ),
            'new_categories' => $new_categories,
            'tag_ids'        => array_values( array_unique( array_filter( $tag_ids ) ) ),
            'new_tags'       => $new_tags,
            'providers'      => $providers,
        ];
    }

    /**
     * Normalize free-form term input.
     *
     * @return string[]
     */
    private function sanitize_new_terms( string $raw ): array {
        $fragments  = preg_split( '/[\r\n,;]+/', wp_unslash( $raw ) ) ?: [];
        $normalized = [];

        foreach ( $fragments as $fragment ) {
            $value = sanitize_text_field( $fragment );

            if ( '' === $value ) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * Attach categories, tags, and providers.
     *
     * @param Service[] $services Services to enrich.
     *
     * @return Service[]
     */
    private function attach_relations( array $services ): array {
        if ( empty( $services ) ) {
            return $services;
        }

        $ids = array_map(
            static function ( Service $service ): int {
                return $service->get_id();
            },
            $services
        );

        $categories = $this->repository->get_categories_for_services( $ids );
        $tags       = $this->repository->get_tags_for_services( $ids );
        $providers  = $this->repository->get_providers_for_services( $ids );

        foreach ( $services as $index => $service ) {
            $services[ $index ] = $service
                ->with_categories( $categories[ $service->get_id() ] ?? [] )
                ->with_tags( $tags[ $service->get_id() ] ?? [] )
                ->with_providers( $providers[ $service->get_id() ] ?? [] );
        }

        return $services;
    }

    /**
     * Attach relations to a single service.
     */
    private function enrich_service( Service $service ): Service {
        return $service
            ->with_categories( $this->repository->get_service_categories( $service->get_id() ) )
            ->with_tags( $this->repository->get_service_tags( $service->get_id() ) )
            ->with_providers( $this->repository->get_service_providers( $service->get_id() ) );
    }

    /**
     * Resolve category ids.
     *
     * @param int[]    $category_ids Existing ids.
     * @param string[] $new_categories New names.
     *
     * @return int[]
     */
    private function resolve_category_ids( array $category_ids, array $new_categories ): array {
        $ids = [];

        foreach ( $category_ids as $category_id ) {
            $category = $this->category_repository->find( $category_id );

            if ( $category ) {
                $ids[] = $category->get_id();
            }
        }

        foreach ( $new_categories as $name ) {
            $category = $this->category_repository->find_by_name( $name );

            if ( ! $category ) {
                $category = $this->category_repository->create( $name );
            }

            if ( $category instanceof ServiceCategory ) {
                $ids[] = $category->get_id();
            } elseif ( is_wp_error( $category ) ) {
                $this->logger->error( sprintf( 'Failed creating service category "%s": %s', $name, $category->get_error_message() ) );
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Resolve tags.
     *
     * @param int[]    $tag_ids Existing ids.
     * @param string[] $new_tags New names.
     *
     * @return int[]
     */
    private function resolve_tag_ids( array $tag_ids, array $new_tags ): array {
        $ids = [];

        foreach ( $tag_ids as $tag_id ) {
            $tag = $this->tag_repository->find( $tag_id );

            if ( $tag ) {
                $ids[] = $tag->get_id();
            }
        }

        foreach ( $new_tags as $name ) {
            $tag = $this->tag_repository->find_by_name( $name );

            if ( ! $tag ) {
                $tag = $this->tag_repository->create( $name );
            }

            if ( $tag instanceof ServiceTag ) {
                $ids[] = $tag->get_id();
            } elseif ( is_wp_error( $tag ) ) {
                $this->logger->error( sprintf( 'Failed creating service tag "%s": %s', $name, $tag->get_error_message() ) );
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Validate provider assignments.
     *
     * @param array<int, mixed> $providers Raw providers.
     *
     * @return array<int, array{employee_id:int, order:int}>|WP_Error
     */
    private function validate_providers( array $providers ) {
        $validated = [];

        foreach ( $providers as $provider ) {
            if ( ! is_array( $provider ) ) {
                $provider = [ 'employee_id' => $provider, 'order' => 0 ];
            }

            $employee_id = isset( $provider['employee_id'] ) ? absint( $provider['employee_id'] ) : 0;

            if ( $employee_id <= 0 ) {
                continue;
            }

            $order = isset( $provider['order'] ) ? (int) $provider['order'] : 0;

            $employee = $this->employee_repository->find( $employee_id );

            if ( null === $employee ) {
                return new WP_Error(
                    'smooth_booking_service_invalid_provider',
                    __( 'Selected provider could not be found.', 'smooth-booking' )
                );
            }

            $validated[ $employee_id ] = [
                'employee_id' => $employee_id,
                'order'       => $order,
            ];
        }

        $order_index = 1;

        foreach ( $validated as $employee_id => $assignment ) {
            if ( $assignment['order'] <= 0 ) {
                $validated[ $employee_id ]['order'] = $order_index;
            }

            ++$order_index;
        }

        return array_values( $validated );
    }
}
